<?php

namespace App\Services\Messaging;

use App\Services\Logging\PlatformLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class MessagingGateway
{
    /**
     * @param  array{provider?: string, subject?: string, from?: string, reply_to?: string, html?: string, credentials?: array<string, mixed>}  $options
     */
    public function sendEmail(string $to, string $subject, string $body, array $options = []): bool
    {
        $provider = $options['provider'] ?? config('messaging.email_provider', 'smtp');
        $credentials = $options['credentials'] ?? [];

        try {
            return match ($provider) {
                'sendgrid' => $this->sendViaSendGrid($to, $subject, $body, $options, $credentials),
                'mailgun' => $this->sendViaMailgun($to, $subject, $body, $options, $credentials),
                'postmark' => $this->sendViaPostmark($to, $subject, $body, $options, $credentials),
                'resend' => $this->sendViaResend($to, $subject, $body, $options, $credentials),
                default => $this->sendViaSmtp($to, $subject, $body, $options),
            };
        } catch (\Throwable $e) {
            PlatformLogger::error('Email send failed', ['to' => $to, 'provider' => $provider], null, $e);

            return false;
        }
    }

    /**
     * @param  array{provider?: string, from?: string, credentials?: array<string, mixed>}  $options
     */
    public function sendSms(string $to, string $message, array $options = []): bool
    {
        $provider = $options['provider'] ?? config('messaging.sms_provider', 'log');
        $credentials = $options['credentials'] ?? [];

        try {
            return match ($provider) {
                'twilio' => $this->sendViaTwilio($to, $message, $options, $credentials),
                'vonage' => $this->sendViaVonage($to, $message, $options, $credentials),
                default => $this->sendViaLog($to, $message, $options),
            };
        } catch (\Throwable $e) {
            PlatformLogger::error('SMS send failed', ['to' => $to, 'provider' => $provider], null, $e);

            return false;
        }
    }

    public function sendWebhook(string $url, string $payload, array $headers = []): bool
    {
        $response = Http::timeout(10)
            ->withHeaders($headers)
            ->post($url, ['text' => $payload, 'message' => $payload]);

        return $response->successful();
    }

    protected function sendViaSmtp(string $to, string $subject, string $body, array $options): bool
    {
        $html = $options['html'] ?? null;

        if ($html) {
            Mail::html($html, function ($m) use ($to, $subject, $options, $body) {
                $m->to($to)->subject($subject);
                if (! empty($options['from'])) {
                    [$email, $name] = $this->parseFromAddress($options['from']);
                    $m->from($email, $name);
                }
                if (! empty($options['reply_to'])) {
                    $m->replyTo($options['reply_to']);
                }
                $m->text($body);
            });
        } else {
            Mail::raw($body, function ($m) use ($to, $subject, $options) {
                $m->to($to)->subject($subject);
                if (! empty($options['from'])) {
                    [$email, $name] = $this->parseFromAddress($options['from']);
                    $m->from($email, $name);
                }
                if (! empty($options['reply_to'])) {
                    $m->replyTo($options['reply_to']);
                }
            });
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $credentials
     */
    protected function sendViaSendGrid(string $to, string $subject, string $body, array $options, array $credentials): bool
    {
        $key = $credentials['key'] ?? config('services.sendgrid.key');
        if (! $key) {
            return $this->sendViaSmtp($to, $subject, $body, $options);
        }

        $content = [['type' => 'text/plain', 'value' => $body]];
        if (! empty($options['html'])) {
            $content[] = ['type' => 'text/html', 'value' => $options['html']];
        }

        $payload = [
            'personalizations' => [['to' => [['email' => $to]]]],
            'from' => ['email' => $this->extractEmail($options['from'] ?? config('mail.from.address'))],
            'subject' => $subject,
            'content' => $content,
        ];

        if (! empty($options['reply_to'])) {
            $payload['reply_to'] = ['email' => $options['reply_to']];
        }

        $response = Http::withToken($key)->post('https://api.sendgrid.com/v3/mail/send', $payload);

        return $response->successful();
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $credentials
     */
    protected function sendViaMailgun(string $to, string $subject, string $body, array $options, array $credentials): bool
    {
        $domain = $credentials['domain'] ?? config('services.mailgun.domain');
        $secret = $credentials['secret'] ?? config('services.mailgun.secret');
        if (! $domain || ! $secret) {
            return $this->sendViaSmtp($to, $subject, $body, $options);
        }

        $form = [
            'from' => $options['from'] ?? config('mail.from.address'),
            'to' => $to,
            'subject' => $subject,
            'text' => $body,
        ];

        if (! empty($options['html'])) {
            $form['html'] = $options['html'];
        }

        $response = Http::withBasicAuth('api', $secret)
            ->asForm()
            ->post("https://api.mailgun.net/v3/{$domain}/messages", $form);

        return $response->successful();
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $credentials
     */
    protected function sendViaPostmark(string $to, string $subject, string $body, array $options, array $credentials): bool
    {
        $key = $credentials['key'] ?? config('services.postmark.key');
        if (! $key) {
            return $this->sendViaSmtp($to, $subject, $body, $options);
        }

        $payload = [
            'From' => $options['from'] ?? config('mail.from.address'),
            'To' => $to,
            'Subject' => $subject,
            'TextBody' => $body,
        ];

        if (! empty($options['html'])) {
            $payload['HtmlBody'] = $options['html'];
        }

        $response = Http::withToken($key)->post('https://api.postmarkapp.com/email', $payload);

        return $response->successful();
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $credentials
     */
    protected function sendViaResend(string $to, string $subject, string $body, array $options, array $credentials): bool
    {
        $key = $credentials['key'] ?? config('services.resend.key');
        if (! $key) {
            return $this->sendViaSmtp($to, $subject, $body, $options);
        }

        $payload = [
            'from' => $options['from'] ?? config('mail.from.address'),
            'to' => [$to],
            'subject' => $subject,
            'text' => $body,
        ];

        if (! empty($options['html'])) {
            $payload['html'] = $options['html'];
        }

        $response = Http::withToken($key)->post('https://api.resend.com/emails', $payload);

        return $response->successful();
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $credentials
     */
    protected function sendViaTwilio(string $to, string $message, array $options, array $credentials): bool
    {
        $sid = $credentials['sid'] ?? config('messaging.twilio.sid');
        $token = $credentials['token'] ?? config('messaging.twilio.token');
        $from = $options['from'] ?? $credentials['from'] ?? config('messaging.twilio.from');
        if (! $sid || ! $token || ! $from) {
            return $this->sendViaLog($to, $message, $options);
        }

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $to,
                'Body' => $message,
            ]);

        return $response->successful();
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $credentials
     */
    protected function sendViaVonage(string $to, string $message, array $options, array $credentials): bool
    {
        $key = $credentials['key'] ?? config('messaging.vonage.key');
        $secret = $credentials['secret'] ?? config('messaging.vonage.secret');
        $from = $options['from'] ?? $credentials['from'] ?? config('messaging.vonage.from');
        if (! $key || ! $secret) {
            return $this->sendViaLog($to, $message, $options);
        }

        $response = Http::post('https://rest.nexmo.com/sms/json', [
            'api_key' => $key,
            'api_secret' => $secret,
            'from' => $from,
            'to' => $to,
            'text' => $message,
        ]);

        return $response->json('messages.0.status') === '0';
    }

    protected function sendViaLog(string $to, string $message, array $options): bool
    {
        PlatformLogger::info('SMS (log driver)', [
            'to' => $to,
            'message' => $message,
            'provider' => $options['provider'] ?? 'log',
        ]);

        return true;
    }

    protected function extractEmail(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return $matches[1];
        }

        return $from;
    }

    /**
     * @return array{0: string, 1?: string|null}
     */
    protected function parseFromAddress(string $from): array
    {
        if (preg_match('/^(.+?)\s*<([^>]+)>$/', $from, $matches)) {
            return [$matches[2], trim($matches[1])];
        }

        return [$from, null];
    }
}
