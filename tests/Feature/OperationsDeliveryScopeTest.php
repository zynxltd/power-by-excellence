<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\User;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsDeliveryScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AccountContext::clear();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_operations_delivery_links_match_tenant_context(): void
    {
        $emea = Account::where('slug', 'emea-loans')->firstOrFail();
        $uk = Account::where('slug', 'excellence-uk')->firstOrFail();

        $ukCampaign = Campaign::withoutGlobalScopes()->where('account_id', $uk->id)->firstOrFail();
        $ukDelivery = Delivery::where('campaign_id', $ukCampaign->id)->firstOrFail();
        $ukLead = Lead::withoutGlobalScopes()->where('account_id', $uk->id)->first();

        if (! $ukLead) {
            $ukLead = Lead::withoutGlobalScopes()->create([
                'account_id' => $uk->id,
                'campaign_id' => $ukCampaign->id,
                'status' => 'accepted',
                'field_data' => ['email' => 'ops-uk@test.test'],
                'received_at' => now(),
            ]);
        }

        $ukLog = DeliveryLog::create([
            'lead_id' => $ukLead->id,
            'delivery_id' => $ukDelivery->id,
            'buyer_id' => $ukDelivery->buyer_id,
            'status' => 'success',
            'ping_request' => ['test' => true],
            'duration_ms' => 100,
        ]);

        AccountContext::set($emea);
        session(['current_account_id' => $emea->id]);

        $super = User::where('email', 'admin@powerbyexcellence.test')->firstOrFail();

        $this->actingAs($super)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Operations/Index')
                ->where('deliveryPreview.data', fn ($rows) => collect($rows)->pluck('id')->doesntContain($ukLog->id))
            );

        $this->actingAs($super)
            ->get(route('deliveries.show', $ukDelivery))
            ->assertNotFound();
    }

    public function test_operations_shows_delivery_for_active_tenant(): void
    {
        $emea = Account::where('slug', 'emea-loans')->firstOrFail();
        $emeaCampaign = Campaign::withoutGlobalScopes()->where('account_id', $emea->id)->firstOrFail();
        $emeaDelivery = Delivery::where('campaign_id', $emeaCampaign->id)->firstOrFail();

        $emeaLead = Lead::withoutGlobalScopes()->create([
            'account_id' => $emea->id,
            'campaign_id' => $emeaCampaign->id,
            'status' => 'accepted',
            'field_data' => ['email' => 'ops-emea@test.test'],
            'received_at' => now(),
        ]);

        $log = DeliveryLog::create([
            'lead_id' => $emeaLead->id,
            'delivery_id' => $emeaDelivery->id,
            'buyer_id' => $emeaDelivery->buyer_id,
            'status' => 'success',
            'duration_ms' => 80,
        ]);

        AccountContext::set($emea);
        session(['current_account_id' => $emea->id]);

        $super = User::where('email', 'admin@powerbyexcellence.test')->firstOrFail();

        $this->actingAs($super)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliveryPreview.data.0.id', $log->id)
            );

        $this->actingAs($super)
            ->get(route('deliveries.show', $emeaDelivery))
            ->assertOk();
    }
}
