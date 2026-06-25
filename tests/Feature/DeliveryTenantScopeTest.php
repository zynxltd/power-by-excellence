<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\User;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AccountContext::clear();
    }

    public function test_delivery_show_404_when_wrong_tenant_context(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $uk = Account::where('slug', 'excellence-uk')->firstOrFail();
        $otherAccount = Account::where('id', '!=', $uk->id)->firstOrFail();
        $otherCampaign = Campaign::withoutGlobalScopes()->where('account_id', $otherAccount->id)->firstOrFail();
        $otherDelivery = Delivery::where('campaign_id', $otherCampaign->id)->firstOrFail();

        AccountContext::set($uk);
        session(['current_account_id' => $uk->id]);

        $admin = User::where('email', 'admin@powerbyexcellence.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('deliveries.show', $otherDelivery))
            ->assertNotFound();
    }

    public function test_delivery_show_ok_in_matching_tenant(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $uk = Account::where('slug', 'excellence-uk')->firstOrFail();
        $ukDelivery = Delivery::whereHas('campaign', fn ($q) => $q->where('account_id', $uk->id))->firstOrFail();

        AccountContext::set($uk);
        session(['current_account_id' => $uk->id]);

        $admin = User::where('email', 'admin@powerbyexcellence.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('deliveries.show', $ukDelivery))
            ->assertOk();
    }
}
