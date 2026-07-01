<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected User $admin;

    protected Buyer $buyer;

    protected User $portalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $this->account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();
        $this->buyer = Buyer::where('account_id', $this->account->id)->firstOrFail();
        $this->portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->firstOrFail();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_admin_can_save_buyer_portal_branding(): void
    {
        $this->ukHost()
            ->actingAs($this->admin)
            ->put(route('buyers.update', $this->buyer), [
                'reference' => $this->buyer->reference,
                'name' => $this->buyer->name,
                'email' => $this->buyer->email,
                'status' => $this->buyer->status,
                'credit_balance' => $this->buyer->credit_balance,
                'settings' => [
                    'portal_logo_url' => 'https://cdn.example.com/buyer-logo.png',
                    'portal_primary_color' => '#ff5500',
                    'portal_welcome_text' => 'Welcome to your branded portal.',
                ],
            ])
            ->assertRedirect(route('buyers.show', $this->buyer));

        $this->buyer->refresh();

        $this->assertSame('https://cdn.example.com/buyer-logo.png', $this->buyer->settings['portal_logo_url']);
        $this->assertSame('#ff5500', $this->buyer->settings['portal_primary_color']);
        $this->assertSame('Welcome to your branded portal.', $this->buyer->settings['portal_welcome_text']);
    }

    public function test_buyer_portal_dashboard_receives_branding_props(): void
    {
        $this->buyer->update([
            'settings' => array_merge($this->buyer->settings ?? [], [
                'portal_logo_url' => 'https://cdn.example.com/acme.png',
                'portal_primary_color' => '#112233',
                'portal_welcome_text' => 'Hello Acme team',
            ]),
        ]);

        $this->ukHost()
            ->actingAs($this->portalUser)
            ->get(route('portal.buyer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Buyer/Dashboard')
                ->where('portalBranding.logo_url', 'https://cdn.example.com/acme.png')
                ->where('portalBranding.primary_color', '#112233')
                ->where('portalBranding.welcome_text', 'Hello Acme team')
                ->where('buyerPortal.branding.logo_url', 'https://cdn.example.com/acme.png')
                ->where('buyerPortal.branding.primary_color', '#112233')
                ->where('buyerPortal.branding.welcome_text', 'Hello Acme team'));
    }

    public function test_admin_buyer_edit_form_includes_branding_fields(): void
    {
        $this->buyer->update([
            'settings' => array_merge($this->buyer->settings ?? [], [
                'portal_logo_url' => 'https://cdn.example.com/saved.png',
                'portal_primary_color' => '#aabbcc',
                'portal_welcome_text' => 'Saved welcome',
            ]),
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->get(route('buyers.edit', $this->buyer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Buyers/Form')
                ->where('buyer.settings.portal_logo_url', 'https://cdn.example.com/saved.png')
                ->where('buyer.settings.portal_primary_color', '#aabbcc')
                ->where('buyer.settings.portal_welcome_text', 'Saved welcome'));
    }

    public function test_admin_rejects_invalid_portal_primary_color(): void
    {
        $this->ukHost()
            ->actingAs($this->admin)
            ->put(route('buyers.update', $this->buyer), [
                'reference' => $this->buyer->reference,
                'name' => $this->buyer->name,
                'status' => 'active',
                'settings' => [
                    'portal_primary_color' => 'not-a-color',
                ],
            ])
            ->assertSessionHasErrors('settings.portal_primary_color');
    }
}
