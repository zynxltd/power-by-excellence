<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryMethod;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Services\Logging\PlatformLogger;
use App\Services\Rules\RuleEngine;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliveryExecutor
{
    public function __construct(
        protected TagInterpolator $interpolator,
        protected RuleEngine $ruleEngine,
    ) {}

    public function execute(Lead $lead, Delivery $delivery, array $pingFields = []): DeliveryResult
    {
        $start = microtime(true);
        $fields = $lead->allFields();
        $config = $delivery->config ?? [];

        $log = DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'pending',
        ]);

        try {
            if (! $this->isEligible($lead, $delivery, $fields)) {
                $log->update(['status' => 'skipped', 'skipped_reason' => 'eligibility_rules']);

                return DeliveryResult::skipped('eligibility_rules');
            }

            $result = match ($delivery->method) {
                DeliveryMethod::DirectPost => $this->directPost($fields, $config, $log, $delivery),
                DeliveryMethod::PingPost => $this->pingPost($fields, $pingFields ?: $fields, $config, $log, $lead, $delivery),
                DeliveryMethod::StoreLead => $this->storeLead($delivery, $log, $fields),
                DeliveryMethod::Email => $this->sendEmail($fields, $config, $log, $delivery),
                DeliveryMethod::EmailPingPost => $this->emailPingPost($fields, $pingFields ?: $fields, $config, $log, $delivery),
                DeliveryMethod::Sms => $this->sendSms($fields, $config, $log, $delivery),
                default => DeliveryResult::failed('Unsupported delivery method'),
            };

            $log->update([
                'status' => $result->success ? 'success' : ($result->skipped ? 'skipped' : 'failed'),
                'skipped_reason' => $result->skipReason,
                'revenue' => $result->revenue,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'http_status' => $result->httpStatus,
            ]);

            return $result;
        } catch (Throwable $e) {
            PlatformLogger::error('Delivery execution failed', [
                'delivery_id' => $delivery->id,
                'lead_id' => $lead->id,
            ], $lead, $e);

            $log->update([
                'status' => 'failed',
                'skipped_reason' => 'exception',
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'post_response' => ['error' => $e->getMessage()],
            ]);

            return DeliveryResult::failed($e->getMessage());
        }
    }

    protected function isEligible(Lead $lead, Delivery $delivery, array $fields): bool
    {
        if (! $delivery->isActive()) {
            return false;
        }

        if (! empty($delivery->eligibility_rules) && ! $this->ruleEngine->matches($delivery->eligibility_rules, $fields)) {
            return false;
        }

        if (! empty($delivery->location_filter)) {
            $state = $fields['state'] ?? null;
            $states = $delivery->location_filter['states'] ?? [];
            if ($states && $state && ! in_array($state, $states, true)) {
                return false;
            }
        }

        return true;
    }

    protected function directPost(array $fields, array $config, DeliveryLog $log, Delivery $delivery): DeliveryResult
    {
        $url = $config['url'] ?? null;
        if (! $url) {
            return DeliveryResult::failed('Missing delivery URL');
        }

        $payload = $this->interpolator->buildPayload($config, $fields);
        $method = strtoupper($config['http_method'] ?? 'POST');
        $headers = $config['headers'] ?? [];

        $log->update(['post_request' => ['url' => $url, 'method' => $method, 'body' => $payload]]);

        $response = Http::timeout($config['timeout'] ?? config('performance.delivery_timeout_seconds', 5))
            ->withHeaders($headers)
            ->send($method, $url, ['json' => $payload]);

        $body = $response->json() ?? ['raw' => $response->body()];
        $log->update(['post_response' => $body, 'http_status' => $response->status()]);

        if ($this->matchesSuccess($config, $response->status(), $body)) {
            $revenue = $this->resolveRevenue($config, $body, [], $delivery, $fields);

            return DeliveryResult::success($revenue, $response->status());
        }

        return DeliveryResult::failed('Remote system rejected lead', $response->status());
    }

    protected function pingPost(array $fullFields, array $pingFields, array $config, DeliveryLog $log, Lead $lead, Delivery $delivery): DeliveryResult
    {
        $floor = (float) ($lead->campaign->floor_price ?? 0);
        $ping = $this->pingOnly($lead, $delivery, $pingFields, $floor, $log);

        if ($ping->skipped) {
            return DeliveryResult::skipped($ping->skipReason ?? 'ping_rejected');
        }

        if (! $ping->success) {
            return DeliveryResult::failed('Ping rejected');
        }

        return $this->completePingPost($lead, $delivery, $fullFields, $pingFields, $ping->pingBody, $log);
    }

    public function pingOnly(Lead $lead, Delivery $delivery, array $pingFields, ?float $floor = null, ?DeliveryLog $existingLog = null): PingResult
    {
        $fields = $lead->allFields();
        $config = $delivery->config ?? [];
        $floor ??= (float) ($lead->campaign->floor_price ?? 0);

        $log = $existingLog ?? DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'pending',
        ]);

        if (! $this->isEligible($lead, $delivery, $fields)) {
            $log->update(['status' => 'skipped', 'skipped_reason' => 'eligibility_rules']);

            return PingResult::skipped('eligibility_rules', $log);
        }

        if ($delivery->method === DeliveryMethod::StoreLead) {
            $revenue = app(\App\Services\Billing\RevenueCalculator::class)->calculate($delivery, $fields);
            $log->update(['status' => 'ping_ok', 'revenue' => $revenue, 'ping_response' => ['mode' => 'store_lead', 'Cost' => $revenue]]);

            return PingResult::success($revenue, ['Cost' => $revenue, 'Success' => true], $log);
        }

        if ($delivery->method !== DeliveryMethod::PingPost) {
            $revenue = app(\App\Services\Billing\RevenueCalculator::class)->calculate($delivery, $fields);
            $log->update(['status' => 'ping_ok', 'revenue' => $revenue, 'ping_response' => ['mode' => 'fixed_bid', 'Cost' => $revenue]]);

            return PingResult::success($revenue, ['Cost' => $revenue, 'Success' => true], $log);
        }

        $pingUrl = $config['ping_url'] ?? null;
        if (! $pingUrl) {
            $log->update(['status' => 'failed', 'skipped_reason' => 'missing_ping_url']);

            return PingResult::failed($log);
        }

        $pingPayload = $this->interpolator->buildPayload(
            array_merge($config, ['custom_post_data' => $config['ping_payload'] ?? null]),
            $pingFields
        );

        if (! empty($config['bid_hint'])) {
            $pingPayload['bid_hint'] = $config['bid_hint'];
        }
        $pingPayload['floor'] = $floor;

        $log->update(['ping_request' => ['url' => $pingUrl, 'body' => $pingPayload]]);

        $pingResponse = Http::timeout($config['ping_timeout'] ?? config('performance.ping_timeout_seconds', 2))->post($pingUrl, $pingPayload);
        $pingBody = $pingResponse->json() ?? ['raw' => $pingResponse->body()];
        $log->update(['ping_response' => $pingBody, 'http_status' => $pingResponse->status()]);

        if (! $this->matchesPingSuccess($config, $pingBody, $floor)) {
            $log->update(['status' => 'skipped', 'skipped_reason' => 'ping_rejected']);

            return PingResult::skipped('ping_rejected', $log);
        }

        $revenue = app(\App\Services\Billing\RevenueCalculator::class)->calculate($delivery, $fields, [], $pingBody);
        $log->update(['status' => 'ping_ok', 'revenue' => $revenue]);

        return PingResult::success($revenue, $pingBody, $log);
    }

    /**
     * @return array{delivery: Delivery, log: DeliveryLog, config: array, fields: array, floor: float, pingUrl: string, pingPayload: array}|null
     */
    public function preparePingRequest(Lead $lead, Delivery $delivery, array $pingFields, float $floor): ?array
    {
        $fields = $lead->allFields();
        $config = $delivery->config ?? [];

        $log = DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'pending',
        ]);

        if (! $this->isEligible($lead, $delivery, $fields)) {
            $log->update(['status' => 'skipped', 'skipped_reason' => 'eligibility_rules']);

            return null;
        }

        if ($delivery->method !== DeliveryMethod::PingPost) {
            return null;
        }

        $pingUrl = $config['ping_url'] ?? null;
        if (! $pingUrl) {
            $log->update(['status' => 'failed', 'skipped_reason' => 'missing_ping_url']);

            return null;
        }

        $pingPayload = $this->interpolator->buildPayload(
            array_merge($config, ['custom_post_data' => $config['ping_payload'] ?? null]),
            $pingFields
        );

        if (! empty($config['bid_hint'])) {
            $pingPayload['bid_hint'] = $config['bid_hint'];
        }
        $pingPayload['floor'] = $floor;

        $log->update(['ping_request' => ['url' => $pingUrl, 'body' => $pingPayload]]);

        return [
            'delivery' => $delivery,
            'log' => $log,
            'config' => $config,
            'fields' => $fields,
            'floor' => $floor,
            'pingUrl' => $pingUrl,
            'pingPayload' => $pingPayload,
        ];
    }

    /**
     * @param  array{delivery: Delivery, log: DeliveryLog, config: array, fields: array, floor: float}  $prepared
     */
    public function finalizePingFromResponse(array $prepared, ?Response $response, float $startedAt): PingResult
    {
        $log = $prepared['log'];
        $delivery = $prepared['delivery'];
        $config = $prepared['config'];
        $fields = $prepared['fields'];
        $floor = $prepared['floor'];

        if (! $response) {
            $log->update([
                'status' => 'failed',
                'skipped_reason' => 'timeout',
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            return PingResult::failed($log);
        }

        $pingBody = $response->json() ?? ['raw' => $response->body()];
        $log->update([
            'ping_response' => $pingBody,
            'http_status' => $response->status(),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        if (! $this->matchesPingSuccess($config, $pingBody, $floor)) {
            $log->update(['status' => 'skipped', 'skipped_reason' => 'ping_rejected']);

            return PingResult::skipped('ping_rejected', $log);
        }

        $revenue = app(\App\Services\Billing\RevenueCalculator::class)->calculate($delivery, $fields, [], $pingBody);
        $log->update(['status' => 'ping_ok', 'revenue' => $revenue]);

        return PingResult::success($revenue, $pingBody, $log);
    }

    public function completePingPost(Lead $lead, Delivery $delivery, array $fullFields, array $pingFields, array $pingBody, DeliveryLog $log): DeliveryResult
    {
        $config = $delivery->config ?? [];

        if ($delivery->method === DeliveryMethod::StoreLead) {
            $revenue = app(\App\Services\Billing\RevenueCalculator::class)->calculate($delivery, $fullFields);
            $log->update(['status' => 'success', 'revenue' => $revenue]);

            return DeliveryResult::success($revenue);
        }

        if ($delivery->method !== DeliveryMethod::PingPost) {
            return $this->execute($lead, $delivery, $pingFields);
        }

        $postUrl = $config['post_url'] ?? null;
        if (! $postUrl) {
            $log->update(['status' => 'failed', 'skipped_reason' => 'missing_post_url']);

            return DeliveryResult::failed('Post URL not configured');
        }

        $postPayload = $this->interpolator->buildPayload(
            array_merge($config, ['custom_post_data' => $config['post_payload'] ?? null]),
            $fullFields,
            $pingBody
        );

        $log->update(['post_request' => ['url' => $postUrl, 'body' => $postPayload]]);

        $postResponse = Http::timeout($config['timeout'] ?? config('performance.post_timeout_seconds', 3))->post($postUrl, $postPayload);
        $postBody = $postResponse->json() ?? ['raw' => $postResponse->body()];
        $log->update(['post_response' => $postBody, 'http_status' => $postResponse->status()]);

        if ($this->matchesSuccess($config, $postResponse->status(), $postBody)) {
            $revenue = $this->resolveRevenue($config, $postBody, $pingBody, $delivery, $fullFields);
            $log->update(['status' => 'success', 'revenue' => $revenue]);

            return DeliveryResult::success($revenue, $postResponse->status());
        }

        $log->update(['status' => 'failed', 'skipped_reason' => 'post_rejected']);

        return DeliveryResult::failed('Post rejected', $postResponse->status());
    }

    protected function storeLead(Delivery $delivery, DeliveryLog $log, array $fields = []): DeliveryResult
    {
        $revenue = app(\App\Services\Billing\RevenueCalculator::class)->calculate($delivery, $fields);

        return DeliveryResult::success($revenue);
    }

    protected function sendEmail(array $fields, array $config, DeliveryLog $log, Delivery $delivery): DeliveryResult
    {
        $to = $config['to'] ?? null;
        if (! $to) {
            return DeliveryResult::failed('Email recipient not configured');
        }

        $subject = $this->interpolator->interpolate($config['subject'] ?? 'New Lead', $fields);
        $body = $this->interpolator->interpolate($config['body'] ?? json_encode($fields), $fields);

        $log->update(['post_request' => ['to' => $to, 'subject' => $subject]]);

        try {
            \Illuminate\Support\Facades\Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));

            $revenue = app(\App\Services\Billing\RevenueCalculator::class)->calculate($delivery, $fields);

            return DeliveryResult::success($revenue);
        } catch (Throwable $e) {
            return DeliveryResult::failed('Email delivery failed: '.$e->getMessage());
        }
    }

    protected function emailPingPost(array $fields, array $pingFields, array $config, DeliveryLog $log, Delivery $delivery): DeliveryResult
    {
        $to = $config['to'] ?? null;
        if (! $to) {
            return DeliveryResult::failed('Email recipient not configured');
        }

        $subject = $this->interpolator->interpolate($config['ping_subject'] ?? 'Lead opportunity', $pingFields);
        $body = $this->interpolator->interpolate($config['ping_body'] ?? json_encode($pingFields), $pingFields);
        $acceptUrl = $config['accept_url'] ?? null;
        $rejectUrl = $config['reject_url'] ?? null;

        if ($acceptUrl) {
            $body .= "\n\nAccept: ".$this->interpolator->interpolate($acceptUrl, array_merge($pingFields, ['lead_id' => $fields['lead_id'] ?? '']));
        }
        if ($rejectUrl) {
            $body .= "\nReject: ".$this->interpolator->interpolate($rejectUrl, $pingFields);
        }

        $log->update([
            'ping_request' => ['to' => $to, 'subject' => $subject, 'fields' => array_keys($pingFields)],
            'ping_response' => ['mode' => 'email_ping_post', 'awaiting' => true],
        ]);

        try {
            \Illuminate\Support\Facades\Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));

            $revenue = app(\App\Services\Billing\RevenueCalculator::class)->calculate($delivery, $fields);

            return DeliveryResult::success($revenue);
        } catch (Throwable $e) {
            return DeliveryResult::failed('Email ping-post failed: '.$e->getMessage());
        }
    }

    protected function sendSms(array $fields, array $config, DeliveryLog $log, Delivery $delivery): DeliveryResult
    {
        $to = $config['to'] ?? $fields['phone1'] ?? null;
        if (! $to) {
            return DeliveryResult::failed('SMS recipient not configured');
        }

        $message = $this->interpolator->interpolate($config['message'] ?? 'New lead received', $fields);
        $log->update(['post_request' => ['to' => $to, 'message' => $message]]);

        PlatformLogger::info('SMS delivery queued', ['to' => $to, 'message' => $message]);

        $revenue = app(\App\Services\Billing\RevenueCalculator::class)->calculate($delivery, $fields);

        return DeliveryResult::success($revenue);
    }

    protected function matchesPingSuccess(array $config, array $body, float $floor): bool
    {
        $rules = $config['ping_success_rules'] ?? [
            ['field' => 'Success', 'op' => 'eq', 'value' => true],
        ];

        foreach ($rules as $rule) {
            $actual = data_get($body, $rule['field']);
            $expected = $rule['value'];
            $op = $rule['op'] ?? 'eq';

            if ($op === 'gte' && ! ((float) $actual >= (float) $expected)) {
                return false;
            }
            if ($op === 'eq' && $actual != $expected) {
                return false;
            }
        }

        $cost = (float) data_get($body, $config['price_field'] ?? 'Cost', 0);

        return $cost >= $floor;
    }

    protected function matchesSuccess(array $config, int $status, array $body): bool
    {
        $rules = $config['response_rules'] ?? [['match_by' => 'http_status', 'value' => '200']];

        foreach ($rules as $rule) {
            if (($rule['match_by'] ?? '') === 'http_status' && (string) $status === (string) ($rule['value'] ?? '200')) {
                return ($rule['label'] ?? 'success') === 'success';
            }
            if (($rule['match_by'] ?? '') === 'keyword') {
                $raw = json_encode($body);
                if (str_contains($raw, (string) $rule['value'])) {
                    return ($rule['label'] ?? 'success') === 'success';
                }
            }
        }

        return $status >= 200 && $status < 300;
    }

    protected function resolveRevenue(array $config, array $postBody, array $pingBody = [], ?Delivery $delivery = null, array $leadFields = []): float
    {
        if ($delivery) {
            return app(\App\Services\Billing\RevenueCalculator::class)->calculate(
                $delivery,
                $leadFields,
                $postBody,
                $pingBody,
            );
        }

        return match ($config['revenue_type'] ?? 'fixed') {
            'dynamic' => (float) data_get($postBody, $config['revenue_field'] ?? 'Cost', data_get($pingBody, 'Cost', 0)),
            default => (float) ($config['revenue_amount'] ?? 0),
        };
    }
}
