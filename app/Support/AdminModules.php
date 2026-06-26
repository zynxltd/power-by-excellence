<?php

namespace App\Support;

class AdminModules
{
    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function all(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'description' => 'Home dashboard and overview'],
            ['key' => 'tenant', 'label' => 'Tenant', 'description' => 'Buyers, suppliers, and users'],
            ['key' => 'campaigns', 'label' => 'Campaigns', 'description' => 'Campaigns, forms, and API specs'],
            ['key' => 'operations', 'label' => 'Operations', 'description' => 'Live ops, leads, and quarantine'],
            ['key' => 'reports', 'label' => 'Reports', 'description' => 'Revenue, EPL/EPC, and delivery analytics'],
            ['key' => 'routing', 'label' => 'Routing', 'description' => 'Deliveries and ping trees'],
            ['key' => 'logs', 'label' => 'Logs', 'description' => 'System and delivery logs'],
            ['key' => 'tools', 'label' => 'Tools', 'description' => 'API keys, integrations, and utilities'],
            ['key' => 'billing', 'label' => 'Billing', 'description' => 'Account billing and invoices'],
            ['key' => 'finance', 'label' => 'Finance', 'description' => 'Buyer credits, supplier payouts, and margin'],
            ['key' => 'settings', 'label' => 'Settings', 'description' => 'Branding and account settings'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    /**
     * @return list<string>
     */
    public static function defaultsForStaff(): array
    {
        return ['dashboard', 'operations', 'reports', 'campaigns'];
    }

    public static function moduleForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        $map = [
            'dashboard' => 'dashboard',
            'live-stats' => 'operations',
            'command-center.*' => 'dashboard',
            'platform-events.*' => 'logs',
            'accounts.*' => 'tenant',
            'buyers.*' => 'tenant',
            'suppliers.*' => 'tenant',
            'users.*' => 'tenant',
            'campaigns.*' => 'campaigns',
            'forms.*' => 'campaigns',
            'operations.*' => 'operations',
            'leads.*' => 'operations',
            'quarantine.*' => 'operations',
            'reports.*' => 'reports',
            'deliveries.*' => 'routing',
            'distribution.*' => 'routing',
            'logs.*' => 'logs',
            'api-keys.*' => 'tools',
            'integrations.*' => 'tools',
            'imports.*' => 'tools',
            'help.*' => 'tools',
            'notifications.admin.*' => 'tools',
            'billing.*' => 'billing',
            'finance.*' => 'finance',
            'settings.*' => 'settings',
            'branding.*' => 'settings',
            'profile.*' => 'settings',
        ];

        foreach ($map as $pattern => $module) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -2);
                if (str_starts_with($routeName, $prefix)) {
                    return $module;
                }
            } elseif ($routeName === $pattern) {
                return $module;
            }
        }

        return null;
    }
}
