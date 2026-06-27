<?php

namespace App\ClickTrack;

/**
 * Single source of truth for Integration Lead wiring (nav, modules, Inertia, API keys).
 * Do not register routes here — see routes/click-track-*.php.
 */
final class IntegrationManifest
{
    public const MODULE_KEY = 'click_track';

    public const PRODUCT_NAME = 'Click Track';

    public const INERTIA_PROP = 'clickTrack';

    public const ENTITLEMENT_SERVICE = \App\Services\ClickTrack\ClickTrackEntitlementService::class;

    /**
     * @return list<string>
     */
    public static function apiPermissions(): array
    {
        return ['clicks.read', 'conversions.manage'];
    }

    /**
     * AdminModules.php — add to all() and moduleForRoute map:
     *
     * @return array{key: string, label: string, description: string}
     */
    public static function adminModuleDefinition(): array
    {
        return [
            'key' => self::MODULE_KEY,
            'label' => self::PRODUCT_NAME,
            'description' => 'Affiliate links, clicks, and conversions',
        ];
    }

    /**
     * AdminModules.php — moduleForRoute entries:
     *
     * @return array<string, string>
     */
    public static function routeModuleMap(): array
    {
        return ['click-track.*' => self::MODULE_KEY];
    }

    /**
     * AdminTopNav.vue — new dropdown section (canAccess('click_track')):
     *
     * @return list<array{label: string, route: string}>
     */
    public static function adminNavLinks(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'click-track.dashboard'],
            ['label' => 'Tracking links', 'route' => 'click-track.links.index'],
            ['label' => 'Clicks', 'route' => 'click-track.clicks.index'],
            ['label' => 'Conversions', 'route' => 'click-track.conversions.index'],
            ['label' => 'Reports', 'route' => 'click-track.reports.index'],
            ['label' => 'Settings', 'route' => 'click-track.settings.edit'],
        ];
    }

    /**
     * TenantHub.php — Finance & tools link:
     *
     * @return array{label: string, route: string, description: string}
     */
    public static function tenantHubLink(): array
    {
        return [
            'label' => self::PRODUCT_NAME,
            'route' => 'click-track.dashboard',
            'description' => 'Affiliate links, clicks, conversions',
        ];
    }

    /**
     * HandleInertiaRequests.php — share under auth:
     *   self::INERTIA_PROP => fn () => app(self::ENTITLEMENT_SERVICE)->summary($account)
     *
     * @return list<string>
     */
    public static function supplierPortalNavLinks(): array
    {
        return [
            ['id' => 'supplier-clicks', 'label' => 'Click stats', 'route' => 'portal.supplier.clicks'],
        ];
    }

    /**
     * HandleInertiaRequests / Marketing pricing page — do not edit Pricing.vue directly:
     *
     * @return array<string, mixed>
     */
    public static function pricingModuleFlags(): array
    {
        return PricingModuleFlags::forPage();
    }

    /**
     * PostbackDispatcher / SupplierPostbackService event names added by this feature.
     *
     * @return list<string>
     */
    public static function postbackEvents(): array
    {
        return ['conversion.approved', 'conversion.rejected'];
    }

    /**
     * Owned paths (feature boundary) for code review.
     *
     * @return list<string>
     */
    public static function ownedPaths(): array
    {
        return [
            'app/ClickTrack/',
            'app/Http/Controllers/Admin/ClickTrack/',
            'app/Http/Controllers/ClickRedirectController.php',
            'app/Http/Controllers/ImpressionPixelController.php',
            'app/Http/Controllers/Api/ClickTrackConversionController.php',
            'app/Http/Controllers/Api/ClickTrackReportController.php',
            'app/Models/TrackingLink.php',
            'app/Models/TrackingClick.php',
            'app/Models/TrackingConversion.php',
            'app/Models/TrackingImpression.php',
            'app/Services/ClickTrack/',
            'config/click_track.php',
            'database/migrations/*click_track*',
            'resources/js/Pages/Admin/ClickTrack/',
            'resources/js/Pages/Portal/Supplier/Clicks.vue',
            'routes/click-track-public.php',
            'routes/click-track-admin.php',
            'routes/click-track-supplier.php',
            'routes/click-track-api.php',
            'tests/Feature/ClickTrackTest.php',
        ];
    }
}
