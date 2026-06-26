<?php

return [
    'plans' => [
        'starter' => [
            'label' => 'Starter',
            'fraud_included' => false,
            'addon_price' => 29,
            'validated_leads_cap' => 5000,
            'url_scanner' => false,
        ],
        'growth' => [
            'label' => 'Growth',
            'fraud_included' => true,
            'validated_leads_cap' => 25000,
            'url_scanner' => true,
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'fraud_included' => true,
            'validated_leads_cap' => null,
            'url_scanner' => true,
        ],
    ],
];
