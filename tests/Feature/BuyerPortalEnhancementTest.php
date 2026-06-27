<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Lead;
use App\Models\LeadReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerPortalEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected Buyer $buyer;

    protected User $portalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $this->account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $this->buyer = Buyer::where('account_id', $this->account->id)->firstOrFail();
        $this->portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->firstOrFail();
    }

    protected function host()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_buyer_can_view_lead_detail_page(): void
    {
        $lead = Lead::where('sold_to_buyer_id', $this->buyer->id)->firstOrFail();

        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.buyer.leads.show', $lead->uuid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Buyer/Show')
                ->where('lead.uuid', $lead->uuid)
            );
    }

    public function test_buyer_cannot_view_another_buyers_lead(): void
    {
        $otherBuyer = Buyer::create([
            'account_id' => $this->account->id,
            'reference' => 'other-buyer-portal',
            'name' => 'Other Buyer',
            'email' => 'other-buyer@test.test',
            'status' => 'active',
            'credit_balance' => 100,
        ]);

        $campaign = \App\Models\Campaign::where('account_id', $this->account->id)->firstOrFail();

        $lead = Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'sold_to_buyer_id' => $otherBuyer->id,
            'status' => 'sold',
            'field_data' => ['email' => 'other@test.test', 'firstname' => 'Other', 'lastname' => 'Lead'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.buyer.leads.show', $lead->uuid))
            ->assertNotFound();
    }

    public function test_duplicate_pending_return_is_rejected(): void
    {
        $lead = Lead::where('sold_to_buyer_id', $this->buyer->id)->firstOrFail();

        LeadReturn::create([
            'lead_id' => $lead->id,
            'buyer_id' => $this->buyer->id,
            'reason' => 'Duplicate test',
            'status' => 'pending',
        ]);

        $this->host()
            ->actingAs($this->portalUser)
            ->post(route('portal.buyer.returns'), [
                'lead_uuid' => $lead->uuid,
                'reason' => 'Second request',
            ])
            ->assertSessionHasErrors('lead_uuid');
    }

    public function test_return_with_invalid_lead_uuid_returns_validation_error_not_404(): void
    {
        $this->host()
            ->actingAs($this->portalUser)
            ->from(route('portal.buyer.leads'))
            ->post(route('portal.buyer.returns'), [
                'lead_uuid' => '123r',
                'reason' => 'test',
            ])
            ->assertSessionHasErrors('lead_uuid')
            ->assertRedirect(route('portal.buyer.leads'));
    }

    public function test_leads_page_includes_action_lead_options(): void
    {
        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.buyer.leads'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('actionLeads')
            );
    }

    public function test_dashboard_includes_account_summary(): void
    {
        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.buyer.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Buyer/Dashboard')
                ->has('account.status')
                ->has('recentActivity')
                ->has('stats.conversion_rate')
            );
    }
}
