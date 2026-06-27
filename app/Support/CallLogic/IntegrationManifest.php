<?php

namespace App\Support\CallLogic;

/**
 * Integration manifest for Call Logic — copy values into Integration Lead owned files.
 */
class IntegrationManifest
{
    public const MODULE_KEY = 'call_logic';

    public const PRODUCT_KEY = 'call_logic';

    public const MIDDLEWARE_ALIAS = 'product.enabled';

    /**
     * Register in bootstrap/app.php middleware alias:
     * 'product.enabled' => \App\Http\Middleware\EnsureProductEnabled::class,
     */
    public static function middlewareAlias(): array
    {
        return [
            self::MIDDLEWARE_ALIAS => \App\Http\Middleware\EnsureProductEnabled::class,
        ];
    }

    /**
     * Add to AdminModules::all().
     */
    public static function adminModule(): array
    {
        return [
            'key' => self::MODULE_KEY,
            'label' => 'Call Logic',
            'description' => 'Call tracking, IVR, and pay-per-call',
        ];
    }

    /**
     * Add to AdminModules::moduleForRoute() map.
     */
    public static function routeModuleMap(): array
    {
        return [
            'call-logic.*' => self::MODULE_KEY,
        ];
    }

    /**
     * AdminTopNav / TenantHub links (shown when module + product enabled).
     *
     * @return list<array{label: string, href: string, description?: string}>
     */
    public static function navLinks(): array
    {
        return [
            ['label' => 'Settings', 'href' => route('call-logic.settings.edit'), 'description' => 'Enable product and telephony limits'],
            ['label' => 'Calls', 'href' => route('call-logic.calls.index'), 'description' => 'Inbound call sessions'],
            ['label' => 'Tracking numbers', 'href' => route('call-logic.tracking-numbers.index'), 'description' => 'DIDs and DNI pools'],
            ['label' => 'IVR flows', 'href' => route('call-logic.ivr.index'), 'description' => 'Caller journeys'],
            ['label' => 'Reports', 'href' => route('call-logic.reports.index'), 'description' => 'Connect rate and Traffic Flow'],
        ];
    }

    /**
     * Route files to require from web.php / api.php.
     *
     * @return array{web_public: string, web_admin: string, web_portal: string, api: string}
     */
    public static function routeFiles(): array
    {
        return [
            'web_public' => 'routes/call-logic.php',
            'web_admin' => 'callLogicAdminRoutes();',
            'web_portal' => 'routes/call-logic-portal.php',
            'api' => 'routes/call-logic-api.php',
        ];
    }
}
