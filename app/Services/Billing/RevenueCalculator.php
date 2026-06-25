<?php

namespace App\Services\Billing;

use App\Models\Delivery;
use App\Services\Rules\RuleEngine;

class RevenueCalculator
{
    public function __construct(
        protected RuleEngine $ruleEngine,
    ) {}

    public function calculate(
        Delivery $delivery,
        array $leadFields = [],
        array $postBody = [],
        array $pingBody = [],
        ?float $executorRevenue = null,
    ): float {
        $config = $delivery->config ?? [];

        return match ($delivery->revenue_type) {
            'dynamic' => $executorRevenue ?? $this->dynamicRevenue($delivery, $config, $postBody, $pingBody),
            'rule_based' => $this->ruleBasedRevenue($delivery, $leadFields),
            default => (float) $delivery->revenue_amount,
        };
    }

    protected function dynamicRevenue(Delivery $delivery, array $config, array $postBody, array $pingBody): float
    {
        $field = $config['revenue_field'] ?? 'Cost';

        return (float) data_get($postBody, $field, data_get($pingBody, $field, $delivery->revenue_amount));
    }

    protected function ruleBasedRevenue(Delivery $delivery, array $leadFields): float
    {
        foreach ($delivery->revenue_rules ?? [] as $rule) {
            $conditions = $rule['conditions'] ?? $rule['rules'] ?? null;

            if ($conditions && $this->ruleEngine->matches($conditions, $leadFields)) {
                return (float) ($rule['amount'] ?? $delivery->revenue_amount);
            }

            if (! empty($rule['field']) && ($leadFields[$rule['field']] ?? null) == ($rule['value'] ?? null)) {
                return (float) ($rule['amount'] ?? $delivery->revenue_amount);
            }
        }

        return (float) $delivery->revenue_amount;
    }
}
