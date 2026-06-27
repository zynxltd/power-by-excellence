<?php

return [
    'pricing' => [
        'product_key' => 'click_track',
        'display_name' => 'Click Track',
        'tagline' => 'Affiliate link tracking, clicks, and conversions',
        'plans' => [
            'starter' => [
                'show_on_pricing' => false,
                'marketing_label' => 'Not included',
                'feature_bullet' => 'Upgrade to Growth for affiliate click tracking.',
            ],
            'growth' => [
                'show_on_pricing' => true,
                'marketing_label' => 'Included',
                'clicks_display' => '100,000 / month',
                'conversions_display' => '25,000 / month',
                'feature_bullet' => 'Tracking links, click logs, conversion queue, and supplier stats.',
            ],
            'enterprise' => [
                'show_on_pricing' => true,
                'marketing_label' => 'Unlimited',
                'clicks_display' => 'Unlimited',
                'conversions_display' => 'Unlimited',
                'feature_bullet' => 'Custom caps, priority support, and advanced reporting.',
            ],
        ],
    ],

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
