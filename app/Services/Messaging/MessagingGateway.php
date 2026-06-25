<?php

namespace App\Services\Messaging;

use App\Services\Logging\PlatformLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class MessagingGateway
{
    /**
     * @param  array{provider?: string, subject?: string, from?: string}  $options
     */
    public function sendEmail(string $to, string $subject, string $body, array $options = []): bool
    {
        $provider = $options['provider'] ?? config('messaging.email_provider', 'smtp');

        try {
            return match ($provider) {
                'sendgrid' => $this->sendViaSendGrid($to, $subject, $body, $options),
                'mailgun' => $this->sendViaMailgun($to, $subject, $body, $options),
                'postmark' => $this->sendViaPostmark($to, $subject, $body, $options),
                'resend' => $this->sendViaResend($to, $subject, $body, $options),
                default => $this->sendViaSmtp($to, $subject, $body, $options),
            };
        } catch (\Throwable $e) {
            PlatformLogger::error('Email send failed', ['to' => $to, 'provider' => $provider], null, $e);

            return false;
        }
    }

    /**
     * @param  array{provider?: string, from?: string}  $options
     */
    public function sendSms(string $to, string $message, array $options = []): bool
    {
        $provider = $options['provider'] ?? config('messaging.sms_provider', 'log');

        try {
            return match ($provider) {
                'twilio' => $this->sendViaTwilio($to, $message, $options),
                'vonage' => $this->sendViaVonage($to, $message, $options),
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
        Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));

        return true;
    }

    protected function sendViaSendGrid(string $to, string $subject, string $body, array $options): bool
    {
        $key = config('services.sendgrid.key');
        if (! $key) {
            return $this->sendViaSmtp($to, $subject, $body, $options);
        }

        $response = Http::withToken($key)->post('https://api.sendgrid.com/v3/mail/send', [
            'personalizations' => [['to' => [['email' => $to]]]],
            'from' => ['email' => $options['from'] ?? config('mail.from.address')],
            'subject' => $subject,
            'content' => [['type' => 'text/plain', 'value' => $body]],
        ]);

        return $response->successful();
    }

    protected function sendViaMailgun(string $to, string $subject, string $body, array $options): bool
    {
        $domain = config('services.mailgun.domain');
        $secret = config('services.mailgun.secret');
        if (! $domain || ! $secret) {
            return $this->sendViaSmtp($to, $subject, $body, $options);
        }

        $response = Http::withBasicAuth('api', $secret)
            ->asForm()
            ->post("https://api.mailgun.net/v3/{$domain}/messages", [
                'from' => $options['from'] ?? config('mail.from.address'),
                'to' => $to,
                'subject' => $subject,
                'text' => $body,
            ]);

        return $response->successful();
    }

    protected function sendViaPostmark(string $to, string $subject, string $body, array $options): bool
    {
        $key = config('services.postmark.key');
        if (! $key) {
            return $this->sendViaSmtp($to, $subject, $body, $options);
        }

        $response = Http::withToken($key)->post('https://api.postmarkapp.com/email', [
            'From' => $options['from'] ?? config('mail.from.address'),
            'To' => $to,
            'Subject' => $subject,
            'TextBody' => $body,
        ]);

        return $response->successful();
    }

    protected function sendViaResend(string $to, string $subject, string $body, array $options): bool
    {
        $key = config('services.resend.key');
        if (! $key) {
            return $this->sendViaSmtp($to, $subject, $body, $options);
        }

        $response = Http::withToken($key)->post('https://api.resend.com/emails', [
            'from' => $options['from'] ?? config('mail.from.address'),
            'to' => [$to],
            'subject' => $subject,
            'text' => $body,
        ]);

        return $response->successful();
    }

    protected function sendViaTwilio(string $to, string $message, array $options): bool
    {
        $sid = config('messaging.twilio.sid');
        $token = config('messaging.twilio.token');
        $from = $options['from'] ?? config('messaging.twilio.from');
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

    protected function sendViaVonage(string $to, string $message, array $options): bool
    {
        $key = config('messaging.vonage.key');
        $secret = config('messaging.vonage.secret');
        $from = $options['from'] ?? config('messaging.vonage.from');
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
}
