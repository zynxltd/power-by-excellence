<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Lead;
use App\Models\LeadReturn;
use App\Models\User;
use App\Services\Platform\PlatformNotificationService;
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
                ->has('suppliers')
                ->has('sids')
            );
    }

    public function test_buyer_can_filter_leads_by_supplier_and_sid(): void
    {
        $lead = Lead::where('sold_to_buyer_id', $this->buyer->id)
            ->whereNotNull('supplier_id')
            ->whereNotNull('sid')
            ->firstOrFail();

        $totalForSid = Lead::where('sold_to_buyer_id', $this->buyer->id)
            ->where('sid', $lead->sid)
            ->count();

        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.buyer.leads', [
                'supplier_id' => $lead->supplier_id,
                'sid' => $lead->sid,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.supplier_id', (string) $lead->supplier_id)
                ->where('filters.sid', $lead->sid)
                ->where('leads.total', $totalForSid)
            );
    }

    public function test_single_feedback_notification_links_to_highlighted_row(): void
    {
        $lead = Lead::where('sold_to_buyer_id', $this->buyer->id)->firstOrFail();

        $this->host()
            ->actingAs($this->portalUser)
            ->post(route('portal.buyer.feedback'), [
                'lead_uuid' => $lead->uuid,
                'status' => 'invalid',
                'converted' => false,
                'notes' => 'Fraudulent lead',
            ])
            ->assertRedirect();

        $feedback = \App\Models\BuyerFeedback::where('lead_id', $lead->id)->where('buyer_id', $this->buyer->id)->firstOrFail();
        $notification = \App\Models\PlatformNotification::query()
            ->where('title', 'Buyer feedback recorded')
            ->where('account_id', $this->account->id)
            ->latest('id')
            ->firstOrFail();

        $tenantAdmin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();

        $this->assertSame(
            route('buyers.show', ['buyer' => $this->buyer->id, 'feedback' => $feedback->id]),
            app(PlatformNotificationService::class)->hrefFor($tenantAdmin, $notification),
        );
    }

    public function test_buyer_can_submit_bulk_feedback(): void
    {
        $leads = Lead::where('sold_to_buyer_id', $this->buyer->id)->limit(2)->get();
        $this->assertGreaterThanOrEqual(2, $leads->count());

        $this->host()
            ->actingAs($this->portalUser)
            ->post(route('portal.buyer.feedback.bulk'), [
                'lead_uuids' => $leads->pluck('uuid')->all(),
                'status' => 'contacted',
                'converted' => false,
                'notes' => 'Bulk update',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($leads as $lead) {
            $this->assertDatabaseHas('buyer_feedback', [
                'lead_id' => $lead->id,
                'buyer_id' => $this->buyer->id,
                'status' => 'contacted',
            ]);
        }

        $this->assertDatabaseHas('platform_notifications', [
            'account_id' => $this->account->id,
            'audience' => 'tenant',
            'title' => 'Buyer feedback on 2 leads',
        ]);

        $tenantAdmin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();
        $notification = \App\Models\PlatformNotification::query()
            ->where('title', 'Buyer feedback on 2 leads')
            ->where('account_id', $this->account->id)
            ->firstOrFail();

        $this->assertSame(
            route('buyers.show', $this->buyer->id),
            app(PlatformNotificationService::class)->hrefFor($tenantAdmin, $notification),
        );
    }

    public function test_buyer_show_includes_feedback_and_returns(): void
    {
        $lead = Lead::where('sold_to_buyer_id', $this->buyer->id)->firstOrFail();

        \App\Models\BuyerFeedback::create([
            'lead_id' => $lead->id,
            'buyer_id' => $this->buyer->id,
            'status' => 'invalid',
            'converted' => false,
            'notes' => 'Wrong number',
        ]);

        \App\Models\LeadReturn::create([
            'lead_id' => $lead->id,
            'buyer_id' => $this->buyer->id,
            'reason' => 'Duplicate in CRM',
            'status' => 'pending',
        ]);

        $admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->get(route('buyers.show', $this->buyer->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Buyers/Show')
                ->has('recentFeedback', 1)
                ->has('pendingReturns', 1)
                ->where('activityStats.pending_returns', 1)
            );
    }

    public function test_buyer_can_filter_leads_by_pending_return(): void
    {
        $lead = Lead::where('sold_to_buyer_id', $this->buyer->id)->firstOrFail();

        LeadReturn::create([
            'lead_id' => $lead->id,
            'buyer_id' => $this->buyer->id,
            'reason' => 'Wrong number',
            'status' => 'pending',
        ]);

        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.buyer.leads', ['return' => 'pending']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.return', 'pending')
                ->where('leads.total', 1)
                ->where('leads.data.0.uuid', $lead->uuid)
            );
    }

    public function test_buyer_can_export_selected_leads(): void
    {
        $leads = Lead::where('sold_to_buyer_id', $this->buyer->id)->limit(2)->get();
        $this->assertGreaterThanOrEqual(1, $leads->count());

        $response = $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.buyer.leads.download', [
                'uuids' => $leads->pluck('uuid')->all(),
            ]))
            ->assertOk();

        $body = $response->getContent();
        $this->assertStringContainsString($leads->first()->uuid, $body);
        if ($leads->count() > 1) {
            $this->assertStringContainsString($leads->last()->uuid, $body);
        }
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
