<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryMethod;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeliveryTestHarnessService
{
    public const MODE_ACCEPT = 'accept';

    public const MODE_REJECT = 'reject';

    public const MODE_TIMEOUT = 'timeout';

    public const MODE_CUSTOM = 'custom';

    /**
     * @param  array<string, mixed>|null  $customResponse
     * @return array<string, mixed>
     */
    public function run(Delivery $delivery, string $mode = self::MODE_ACCEPT, ?array $customResponse = null): array
    {
        $delivery->loadMissing(['campaign.fields', 'buyer']);
        $mode = $this->normalizeMode($mode);

        Http::fake($this->httpFakesFor($delivery, $mode, $customResponse));

        $lead = $this->createSyntheticLead($delivery);
        $samplePayload = $this->samplePayloadPreview($delivery, $lead);

        $result = app(DeliveryExecutor::class)->execute($lead, $delivery);

        $log = DeliveryLog::query()
            ->where('lead_id', $lead->id)
            ->where('delivery_id', $delivery->id)
            ->latest('id')
            ->first();

        if ($log) {
            $this->markLogAsTest($log, $mode);
        }

        return [
            'mode' => $mode,
            'sold' => $result->success,
            'skipped' => $result->skipped,
            'outcome' => $this->parseOutcome($result),
            'http_status' => $log?->http_status ?? $result->httpStatus,
            'ping_response' => $log?->ping_response,
            'post_response' => $log?->post_response,
            'body' => $log?->post_response ?? $log?->ping_response,
            'revenue' => $result->revenue,
            'log_id' => $log?->id,
            'lead_uuid' => $lead->uuid,
            'lead_id' => $lead->id,
            'sample_payload' => $samplePayload,
            'error' => $result->error,
            'skip_reason' => $result->skipReason,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function sampleFieldsFor(Delivery $delivery): array
    {
        $delivery->loadMissing('campaign.fields');

        return $this->defaultSampleFields($delivery->campaign);
    }

    protected function normalizeMode(string $mode): string
    {
        return in_array($mode, [
            self::MODE_ACCEPT,
            self::MODE_REJECT,
            self::MODE_TIMEOUT,
            self::MODE_CUSTOM,
        ], true) ? $mode : self::MODE_ACCEPT;
    }

    protected function createSyntheticLead(Delivery $delivery): Lead
    {
        $campaign = $delivery->campaign;
        $fields = $this->defaultSampleFields($campaign);

        return Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => $fields,
            'metadata' => [
                'is_test' => true,
                'delivery_test' => true,
                'delivery_id' => $delivery->id,
            ],
            'received_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function defaultSampleFields(Campaign $campaign): array
    {
        $samples = [
            'firstname' => 'Test',
            'lastname' => 'Lead',
            'email' => 'delivery-test@example.com',
            'phone1' => '07700900123',
            'zipcode' => 'SW1A 1AA',
            'state' => 'London',
            'vehicle_year' => '2020',
        ];

        $fields = [];

        foreach ($campaign->fields ?? [] as $field) {
            $name = $field->name;
            $fields[$name] = $samples[$name] ?? 'sample-'.$name;
        }

        if ($fields === []) {
            return array_intersect_key($samples, array_flip([
                'firstname', 'lastname', 'email', 'phone1', 'zipcode',
            ]));
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    protected function samplePayloadPreview(Delivery $delivery, Lead $lead): array
    {
        $config = $delivery->config ?? [];
        $fields = $lead->allFields();
        $builder = app(DeliveryPayloadBuilder::class);
        $floor = (float) ($delivery->campaign->floor_price ?? 0);

        $preview = [
            'fields' => $fields,
        ];

        if (in_array($delivery->method, [DeliveryMethod::PingPost, DeliveryMethod::PingOnly, DeliveryMethod::TwoStepAuth], true)
            && filled($config['ping_url'] ?? null)) {
            $preview['ping'] = [
                'url' => $config['ping_url'],
                'body' => $builder->buildPingPayload($config, $fields, $floor, $lead),
            ];
        }

        if (in_array($delivery->method, [DeliveryMethod::PingPost, DeliveryMethod::DirectPost, DeliveryMethod::TwoStepAuth], true)) {
            $postUrl = $config['post_url'] ?? $config['url'] ?? $config['auth_url'] ?? null;
            if ($postUrl) {
                $preview['post'] = [
                    'url' => $postUrl,
                    'body' => $builder->buildPostPayload($config, $fields, ['Success' => true, 'Cost' => 25, 'PingID' => 'preview'], $lead),
                ];
            }
        }

        return $preview;
    }

    /**
     * @param  array<string, mixed>|null  $customResponse
     * @return array<string, mixed>
     */
    protected function httpFakesFor(Delivery $delivery, string $mode, ?array $customResponse): array
    {
        $config = $delivery->config ?? [];
        $urls = array_values(array_unique(array_filter([
            $config['ping_url'] ?? null,
            $config['post_url'] ?? null,
            $config['url'] ?? null,
            $config['auth_url'] ?? null,
        ])));

        if ($urls === []) {
            return [];
        }

        return match ($mode) {
            self::MODE_REJECT => $this->rejectFakes($config, $urls),
            self::MODE_TIMEOUT => $this->timeoutFakes($urls),
            self::MODE_CUSTOM => $this->customFakes($config, $urls, $customResponse ?? []),
            default => $this->acceptFakes($config, $urls),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $urls
     * @return array<string, mixed>
     */
    protected function acceptFakes(array $config, array $urls): array
    {
        $fakes = [];

        if ($pingUrl = $config['ping_url'] ?? null) {
            $fakes[$pingUrl] = Http::response([
                'Success' => true,
                'Cost' => 25.00,
                'PingID' => 'test_ping_'.Str::random(8),
            ], 200);
        }

        foreach (['post_url', 'url', 'auth_url'] as $key) {
            if ($url = $config[$key] ?? null) {
                $fakes[$url] = Http::response([
                    'Success' => true,
                    'Approved' => true,
                    'LeadID' => 'test_lead_'.Str::random(8),
                ], 200);
            }
        }

        foreach ($urls as $url) {
            $fakes[$url] ??= Http::response(['Success' => true, 'Approved' => true], 200);
        }

        return $fakes;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $urls
     * @return array<string, mixed>
     */
    protected function rejectFakes(array $config, array $urls): array
    {
        $fakes = [];

        if ($pingUrl = $config['ping_url'] ?? null) {
            $fakes[$pingUrl] = Http::response([
                'Success' => false,
                'Reason' => 'Mock buyer rejected ping',
            ], 200);
        }

        foreach (['post_url', 'url', 'auth_url'] as $key) {
            if ($url = $config[$key] ?? null) {
                $fakes[$url] = Http::response([
                    'Success' => false,
                    'Approved' => false,
                    'Reason' => 'Mock buyer rejected post',
                ], 200);
            }
        }

        foreach ($urls as $url) {
            $fakes[$url] ??= Http::response(['Success' => false, 'Approved' => false], 200);
        }

        return $fakes;
    }

    /**
     * @param  list<string>  $urls
     * @return array<string, mixed>
     */
    protected function timeoutFakes(array $urls): array
    {
        $fakes = [];

        foreach ($urls as $url) {
            $fakes[$url] = function () {
                throw new ConnectionException('cURL error 28: Connection timed out after 1 ms');
            };
        }

        return $fakes;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $urls
     * @param  array<string, mixed>  $customResponse
     * @return array<string, mixed>
     */
    protected function customFakes(array $config, array $urls, array $customResponse): array
    {
        $status = (int) ($customResponse['http_status'] ?? 200);
        $body = $customResponse['body'] ?? $customResponse;

        if (! is_array($body)) {
            $body = ['raw' => (string) $body];
        }

        $fakes = [];

        if ($pingUrl = $config['ping_url'] ?? null) {
            $fakes[$pingUrl] = Http::response(
                $customResponse['ping'] ?? $body,
                (int) ($customResponse['ping_http_status'] ?? $status),
            );
        }

        $postBody = $customResponse['post'] ?? $body;
        foreach (['post_url', 'url', 'auth_url'] as $key) {
            if ($url = $config[$key] ?? null) {
                $fakes[$url] = Http::response($postBody, $status);
            }
        }

        foreach ($urls as $url) {
            $fakes[$url] ??= Http::response($body, $status);
        }

        return $fakes;
    }

    protected function markLogAsTest(DeliveryLog $log, string $mode): void
    {
        if (is_array($log->ping_request)) {
            $log->update([
                'ping_request' => array_merge($log->ping_request, [
                    '_meta' => ['is_test' => true, 'mode' => $mode],
                ]),
            ]);

            return;
        }

        if (is_array($log->post_request)) {
            $log->update([
                'post_request' => array_merge($log->post_request, [
                    '_meta' => ['is_test' => true, 'mode' => $mode],
                ]),
            ]);

            return;
        }

        $log->update([
            'post_response' => array_merge($log->post_response ?? [], [
                '_meta' => ['is_test' => true, 'mode' => $mode],
            ]),
        ]);
    }

    protected function parseOutcome(DeliveryResult $result): string
    {
        if ($result->success) {
            return 'sold';
        }

        if ($result->skipped) {
            return match ($result->skipReason) {
                'ping_rejected', 'post_rejected', 'eligibility_rules' => 'reject',
                'timeout' => 'timeout',
                default => 'skipped',
            };
        }

        if ($result->error && (str_contains(strtolower($result->error), 'timeout') || str_contains(strtolower($result->error), 'timed out'))) {
            return 'timeout';
        }

        return match ($result->skipReason) {
            'ping_rejected', 'post_rejected' => 'reject',
            'timeout' => 'timeout',
            default => 'failed',
        };
    }
}
