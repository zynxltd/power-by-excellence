<?php

namespace App\Services\Validation;

use App\Models\Account;
use App\Services\Billing\FraudProtectionService;
use App\Services\Validation\Contracts\ValidationProvider;

class ValidationProviderResolver
{
    public function __construct(
        protected FraudProtectionService $fraudProtection,
    ) {}

    /**
     * @return list<string>
     */
    public static function ipqsConfigKeys(): array
    {
        return array_keys(config('validation.ipqs', []));
    }

    public function forAccount(?Account $account): ValidationProvider
    {
        $integration = $account?->settings['validation_integration'] ?? [];
        $driver = $integration['provider'] ?? config('validation.driver', 'demo');

        if ($driver === 'ipqs' && $this->fraudProtection->isEntitled($account)) {
            $config = $this->ipqsConfig($account);
            if (filled($config['api_key'] ?? null)) {
                return new IpqsValidationProvider($config);
            }
        }

        return app(DemoValidationProvider::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function ipqsConfig(?Account $account): array
    {
        $integration = $account?->settings['validation_integration'] ?? [];
        $stored = $integration['ipqs'] ?? [];

        $apiKey = $stored['api_key'] ?? null;
        if (is_string($apiKey) && str_starts_with($apiKey, 'eyJpdiI6')) {
            try {
                $apiKey = decrypt($apiKey);
            } catch (\Throwable) {
                $apiKey = null;
            }
        }

        $defaults = config('validation.ipqs', []);
        $merged = ['api_key' => $apiKey ?: ($defaults['api_key'] ?? null)];

        foreach (self::ipqsConfigKeys() as $key) {
            if ($key === 'api_key') {
                continue;
            }

            $default = $defaults[$key] ?? null;
            $value = $stored[$key] ?? $default;

            if (is_bool($default)) {
                $merged[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $default;
            } elseif (is_int($default)) {
                $merged[$key] = (int) ($value ?? $default);
            } else {
                $merged[$key] = $value ?? $default;
            }
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function ipqsSettingsForUi(?Account $account): array
    {
        $config = $this->ipqsConfig($account);
        unset($config['api_key']);

        return $config;
    }
}
