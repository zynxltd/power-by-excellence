<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\User;
use App\Support\BuyerPortal\BuyerPortalLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerPortalLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = Account::where('slug', 'excellence-uk')->first();
    }

    protected function tenantRequest()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_tenant_can_set_default_buyer_portal_language(): void
    {
        $this->tenantRequest()
            ->actingAs($this->admin)
            ->put(route('settings.update'), [
                'name' => $this->account->name,
                'timezone' => $this->account->timezone,
                'default_country' => $this->account->default_country,
                'default_currency' => $this->account->default_currency,
                'require_buyer_prepay' => false,
                'supplier_iframe_embed' => false,
                'buyer_portal_locale' => 'de',
            ])
            ->assertRedirect();

        $this->account->refresh();
        $this->assertSame('de', $this->account->settings['buyer_portal_locale']);
    }

    public function test_buyer_portal_uses_tenant_default_language(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['buyer_portal_locale'] = 'fr';
        $this->account->update(['settings' => $settings]);

        $buyer = User::where('email', 'buyer-portal@excellence-uk.test')->first();

        $this->tenantRequest()
            ->actingAs($buyer)
            ->get(route('portal.buyer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('buyerPortal.locale', 'fr')
                ->where('buyerPortal.strings.nav.dashboard', 'Tableau de bord'));
    }

    public function test_buyer_can_override_portal_language(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['buyer_portal_locale'] = 'en';
        $this->account->update(['settings' => $settings]);

        $buyerModel = Buyer::where('reference', 'buyer-primary')->where('account_id', $this->account->id)->first();
        $buyerModel->update([
            'settings' => array_merge($buyerModel->settings ?? [], ['portal_locale' => 'es']),
        ]);

        $this->assertSame('es', BuyerPortalLocale::resolve($this->account->fresh(), $buyerModel->fresh()));

        $portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->first();

        $this->tenantRequest()
            ->actingAs($portalUser)
            ->get(route('portal.buyer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('buyerPortal.locale', 'es')
                ->where('buyerPortal.strings.nav.dashboard', 'Panel'));
    }
}
