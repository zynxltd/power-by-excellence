<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\BuyerFeedback;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Buyers\BuyerConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerFeedbackReportTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected User $admin;

    protected Campaign $campaign;

    protected Buyer $buyer;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $this->account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();
        $this->campaign = Campaign::where('account_id', $this->account->id)->firstOrFail();
        $this->buyer = Buyer::where('account_id', $this->account->id)->firstOrFail();
        $this->supplier = Supplier::where('account_id', $this->account->id)->firstOrFail();
    }

    protected function host()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    protected function makeSoldLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'sold_to_buyer_id' => $this->buyer->id,
            'sid' => 'google_search',
            'status' => 'sold',
            'field_data' => ['email' => 'feedback-'.uniqid().'@test.test', 'firstname' => 'Test', 'lastname' => 'Lead'],
            'received_at' => now(),
            'distributed_at' => now(),
        ], $overrides));
    }

    public function test_buyer_feedback_index_page_lists_invalid_feedback_with_trail(): void
    {
        $lead = $this->makeSoldLead();

        app(BuyerConversionService::class)->recordFeedback(
            $this->buyer,
            $lead,
            'invalid',
            false,
            'Wrong number',
        );

        $this->host()
            ->actingAs($this->admin)
            ->get(route('buyer-feedback.index', ['status' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/BuyerFeedback/Index')
                ->where('summary.invalid', 1)
                ->has('feedback.data', 1)
                ->where('feedback.data.0.status', 'invalid')
                ->where('feedback.data.0.lead.supplier.name', $this->supplier->name)
                ->where('feedback.data.0.lead.sid', 'google_search')
            );
    }

    public function test_buyer_feedback_drill_down_by_supplier(): void
    {
        $lead = $this->makeSoldLead();

        BuyerFeedback::create([
            'lead_id' => $lead->id,
            'buyer_id' => $this->buyer->id,
            'status' => 'invalid',
            'converted' => false,
            'notes' => 'Bad data',
        ]);

        $this->host()
            ->actingAs($this->admin)
            ->get(route('buyer-feedback.index', ['supplier_id' => $this->supplier->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.supplier_id', (string) $this->supplier->id)
                ->has('feedback.data', 1)
            );
    }

    public function test_lead_pipeline_filters_by_invalid_buyer_feedback(): void
    {
        $flagged = $this->makeSoldLead();
        $this->makeSoldLead(['field_data' => ['email' => 'clean@test.test']]);

        BuyerFeedback::create([
            'lead_id' => $flagged->id,
            'buyer_id' => $this->buyer->id,
            'status' => 'invalid',
            'converted' => false,
        ]);

        $this->host()
            ->actingAs($this->admin)
            ->get(route('leads.index', [
                'buyer_feedback' => 'invalid',
                'campaign_id' => $this->campaign->id,
                'search' => substr($flagged->uuid, 0, 8),
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.buyer_feedback', 'invalid')
                ->has('leads.data', 1)
                ->where('leads.data.0.id', $flagged->id)
            );
    }

    public function test_lead_show_includes_buyer_feedback(): void
    {
        $lead = $this->makeSoldLead(['sid' => 'facebook_leads']);

        BuyerFeedback::create([
            'lead_id' => $lead->id,
            'buyer_id' => $this->buyer->id,
            'status' => 'invalid',
            'converted' => false,
            'notes' => 'Disconnected number',
        ]);

        $this->host()
            ->actingAs($this->admin)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('lead.buyer_feedback', 1)
                ->where('lead.buyer_feedback.0.status', 'invalid')
            );
    }
}
