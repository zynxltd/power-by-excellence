<?php

namespace App\Services\Delivery;

class DeliveryResponseMatcher
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $body
     */
    public function matchesPingSuccess(array $config, array $body, float $floor): bool
    {
        $rules = $config['ping_success_rules'] ?? [
            ['field' => 'Success', 'op' => 'eq', 'value' => true],
        ];

        foreach ($rules as $rule) {
            if (! $this->matchesRule($body, $rule)) {
                return false;
            }
        }

        if (($config['ping_enforce_floor'] ?? true) === false) {
            return true;
        }

        $priceField = $config['ping_price_field'] ?? $config['price_field'] ?? 'Cost';
        $cost = (float) data_get($body, $priceField, 0);

        return $cost >= $floor;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $body
     */
    public function matchesPostSuccess(array $config, int $status, array $body): bool
    {
        $rules = $config['post_success_rules'] ?? $config['response_rules'] ?? [
            ['match_by' => 'http_status', 'value' => '200', 'label' => 'success'],
        ];

        foreach ($rules as $rule) {
            if ($this->matchesResponseRule($rule, $status, $body)) {
                return ($rule['label'] ?? 'success') === 'success';
            }
        }

        return $status >= 200 && $status < 300;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $rule
     */
    protected function matchesRule(array $body, array $rule): bool
    {
        $field = (string) ($rule['field'] ?? '');
        $actual = data_get($body, $field);
        $expected = $rule['value'] ?? null;
        $op = $rule['op'] ?? $rule['operator'] ?? 'eq';

        return match ($op) {
            'gte' => (float) $actual >= (float) $expected,
            'gt' => (float) $actual > (float) $expected,
            'lte' => (float) $actual <= (float) $expected,
            'lt' => (float) $actual < (float) $expected,
            'neq' => $actual != $expected,
            'exists' => $actual !== null && $actual !== '',
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            default => $actual == $expected,
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $body
     */
    protected function matchesResponseRule(array $rule, int $status, array $body): bool
    {
        $matchBy = $rule['match_by'] ?? '';

        if ($matchBy === 'http_status') {
            return (string) $status === (string) ($rule['value'] ?? '200');
        }

        if ($matchBy === 'keyword') {
            return str_contains(json_encode($body) ?: '', (string) ($rule['value'] ?? ''));
        }

        if ($matchBy === 'json_path') {
            $path = (string) ($rule['field'] ?? $rule['path'] ?? '');
            $actual = data_get($body, $path);
            $expected = $rule['value'] ?? true;

            return $actual == $expected;
        }

        return false;
    }
}
