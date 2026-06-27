<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_buyer_portal_integrations_page(): void
    {
        $user = User::where('email', 'buyer-portal@excellence-uk.test')->firstOrFail();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($user)
            ->get(route('portal.buyer.integrations'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Buyer/Integrations')
                ->has('apiBaseUrl')
                ->has('partner.reference')
                ->has('helpUrls')
                ->has('webhookEventOptions')
                ->has('webhookStats')
                ->has('guides')
            );
    }

    public function test_supplier_portal_integrations_page(): void
    {
        $user = User::where('email', 'supplier-portal@excellence-uk.test')->firstOrFail();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($user)
            ->get(route('portal.supplier.integrations'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Supplier/Integrations')
                ->has('apiBaseUrl')
                ->has('campaigns')
                ->has('helpUrls')
                ->has('postbackStats')
                ->has('postbackRequests')
            );
    }

    public function test_supplier_integrations_campaign_filter(): void
    {
        $account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $campaign = \App\Models\Campaign::where('account_id', $account->id)->firstOrFail();
        $user = User::where('email', 'supplier-portal@excellence-uk.test')->firstOrFail();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($user)
            ->get(route('portal.supplier.integrations', ['campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('selectedCampaign.id', $campaign->id)
                ->has('selectedSpec.fields')
            );
    }
}
