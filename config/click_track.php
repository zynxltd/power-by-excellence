<?php

return [
    'plans' => [
        'starter' => [
            'label' => 'Starter',
            'included' => false,
            'addon_available' => false,
            'clicks_cap' => null,
            'conversions_cap' => null,
            'overage_click' => null,
            'overage_conversion' => null,
        ],
        'growth' => [
            'label' => 'Growth',
            'included' => true,
            'addon_available' => true,
            'clicks_cap' => 100000,
            'conversions_cap' => 25000,
            'overage_click' => 0.001,
            'overage_conversion' => 0.02,
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'included' => true,
            'addon_available' => true,
            'clicks_cap' => null,
            'conversions_cap' => null,
            'overage_click' => null,
            'overage_conversion' => null,
        ],
    ],

    'unique_click_window_hours' => 24,

    'goal_options' => [
        'lead',
        'sale',
        'insurance',
        'loan',
        'payday',
        'mortgage',
        'custom',
    ],
];
