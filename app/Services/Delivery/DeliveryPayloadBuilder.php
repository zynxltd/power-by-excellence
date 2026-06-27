<?php

namespace App\Services\Delivery;

use App\Models\Lead;

class DeliveryPayloadBuilder
{
    public function __construct(
        protected TagInterpolator $interpolator,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $pingFields
     * @return array<string, mixed>
     */
    public function buildPingPayload(array $config, array $pingFields, float $floor, ?Lead $lead = null): array
    {
        $systemFields = $this->systemFields($pingFields, $config, $floor, $lead);

        $payload = $this->interpolator->buildPayload(
            [
                'custom_post_data' => $config['ping_payload'] ?? null,
                'custom_data_mappings' => $config['ping_field_mappings'] ?? $config['ping_mappings'] ?? [],
            ],
            $systemFields,
        );

        if ($this->shouldInjectFloor($config, $payload)) {
            $field = (string) ($config['ping_floor_field'] ?? 'floor');
            data_set($payload, $field, $floor);
        }

        if ($this->shouldInjectBidHint($config, $payload)) {
            $field = (string) ($config['ping_bid_hint_field'] ?? 'bid_hint');
            data_set($payload, $field, $config['bid_hint']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $fullFields
     * @param  array<string, mixed>  $pingBody
     * @return array<string, mixed>
     */
    public function buildPostPayload(array $config, array $fullFields, array $pingBody, ?Lead $lead = null): array
    {
        $systemFields = $this->systemFields($fullFields, $config, null, $lead);

        return $this->interpolator->buildPayload(
            [
                'custom_post_data' => $config['post_payload'] ?? null,
                'custom_data_mappings' => $config['post_field_mappings'] ?? $config['post_mappings'] ?? [],
            ],
            $systemFields,
            $pingBody,
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function systemFields(array $fields, array $config, ?float $floor, ?Lead $lead): array
    {
        return array_merge($fields, array_filter([
            'floor' => $floor,
            'bid_hint' => $config['bid_hint'] ?? null,
            'lead_uuid' => $lead?->uuid,
            'lead_id' => $lead?->uuid,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     */
    protected function shouldInjectFloor(array $config, array $payload): bool
    {
        if (array_key_exists('ping_include_floor', $config)) {
            return (bool) $config['ping_include_floor'];
        }

        return empty($config['ping_payload']) && empty($config['ping_field_mappings']) && empty($config['ping_mappings']);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     */
    protected function shouldInjectBidHint(array $config, array $payload): bool
    {
        if (($config['bid_hint'] ?? '') === '' && ($config['bid_hint'] ?? null) !== 0) {
            return false;
        }

        if (array_key_exists('ping_include_bid_hint', $config)) {
            return (bool) $config['ping_include_bid_hint'];
        }

        return empty($config['ping_payload']) && empty($config['ping_field_mappings']) && empty($config['ping_mappings']);
    }
}
