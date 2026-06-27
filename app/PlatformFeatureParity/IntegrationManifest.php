<?php

namespace App\PlatformFeatureParity;

/**
 * Integration handoff for P5–P7 platform feature parity.
 */
final class IntegrationManifest
{
    /**
     * TenantResolver — wire PortalDomain (shared integration file):
     *
     *   public static function portalHost(Account $account): string
     *   {
     *       return PortalDomain::portalHost($account);
     *   }
     *
     *   // resolveFromHost: also match settings->custom_portal_domain
     *
     * @return list<string>
     */
    public static function ownedPaths(): array
    {
        return [
            'app/PlatformFeatureParity/',
            'resources/js/Pages/Admin/Users/Edit.vue',
            'resources/js/Pages/Admin/Webhooks/',
            'resources/js/Pages/Admin/Imports/',
            'resources/js/Pages/Portal/Supplier/ImportLeads.vue',
            'routes/platform-parity-p5.php',
            'routes/platform-parity-p6.php',
            'routes/platform-parity-p7.php',
            'tests/Feature/UserWebhookEditParityTest.php',
            'tests/Feature/SupplierImportTest.php',
            'tests/Feature/PortalDomainTest.php',
        ];
    }
}
