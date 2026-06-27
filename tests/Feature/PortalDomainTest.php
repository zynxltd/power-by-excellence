<?php

namespace Tests\Feature;

use App\Models\Account;
use App\PlatformFeatureParity\PortalDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_host_prefers_settings_custom_portal_domain(): void
    {
        $account = Account::create([
            'name' => 'White Label',
            'slug' => 'white-label',
            'domain' => 'legacy.white-label.test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'settings' => ['custom_portal_domain' => 'portal.whitelabel.test'],
        ]);

        $this->assertSame('portal.whitelabel.test', PortalDomain::portalHost($account));
    }

    public function test_portal_host_falls_back_to_legacy_domain(): void
    {
        $account = Account::create([
            'name' => 'Legacy Domain',
            'slug' => 'legacy-domain',
            'domain' => 'leads.legacy.test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        $this->assertSame('leads.legacy.test', PortalDomain::portalHost($account));
    }

    public function test_custom_portal_domain_matches_host(): void
    {
        $account = Account::create([
            'name' => 'Branded',
            'slug' => 'branded',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'settings' => ['custom_portal_domain' => 'leads.branded.test'],
        ]);

        $this->assertTrue(PortalDomain::matches($account, 'leads.branded.test'));
        $this->assertFalse(PortalDomain::matches($account, 'other.example.test'));
    }
}
