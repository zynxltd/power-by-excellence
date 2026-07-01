<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadFinancial;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Suppliers\SupplierQualityScorecardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierQualityScorecardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected Campaign $campaign;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = Account::where('slug', 'excellence-uk')->first();
        $this->campaign = Campaign::where('account_id', $this->account->id)->first();
        $this->supplier = Supplier::where('account_id', $this->account->id)->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_scorecard_computes_reject_rate_epl_and_sold_rate(): void
    {
        $this->seedLeadsForScorecard();

        $scorecard = app(SupplierQualityScorecardService::class)->scorecard($this->supplier, 30, $this->account);

        $this->assertSame(10, $scorecard['submitted']);
        $this->assertSame(4, $scorecard['sold']);
        $this->assertSame(2, $scorecard['rejected']);
        $this->assertSame(2, $scorecard['quarantined']);
        $this->assertSame(1, $scorecard['duplicate']);
        $this->assertSame(50.0, $scorecard['reject_rate_pct']);
        $this->assertSame(40.0, $scorecard['sold_rate_pct']);
        $this->assertSame(4.0, $scorecard['epl']);
        $this->assertSame(30.0, $scorecard['revenue_per_sold']);
        $this->assertSame(40.0, $scorecard['total_supplier_payout']);
        $this->assertSame(120.0, $scorecard['total_buyer_revenue']);
        $this->assertNotNull($scorecard['quality_grade']);
        $this->assertArrayHasKey('sparkline', $scorecard);
    }

    public function test_scorecard_respects_date_range_filter(): void
    {
        $this->createLead(LeadStatus::Sold, now()->subDays(5), 10, 30);
        $this->createLead(LeadStatus::Rejected, now()->subDays(40));

        $recent = app(SupplierQualityScorecardService::class)->scorecard($this->supplier, 30, $this->account);
        $this->assertSame(2, $recent['submitted']);

        $narrow = app(SupplierQualityScorecardService::class)->scorecard($this->supplier, 7, $this->account);
        $this->assertSame(1, $narrow['submitted']);
        $this->assertSame(1, $narrow['sold']);
    }

    public function test_zero_submitted_returns_null_rates_and_grade(): void
    {
        Lead::query()->where('supplier_id', $this->supplier->id)->delete();

        $scorecard = app(SupplierQualityScorecardService::class)->scorecard($this->supplier, 30, $this->account);

        $this->assertSame(0, $scorecard['submitted']);
        $this->assertNull($scorecard['reject_rate_pct']);
        $this->assertNull($scorecard['sold_rate_pct']);
        $this->assertNull($scorecard['epl']);
        $this->assertNull($scorecard['quality_grade']);
        $this->assertSame([], $scorecard['warnings']);
    }

    public function test_account_thresholds_trigger_warnings(): void
    {
        $this->account->update([
            'settings' => array_merge($this->account->settings ?? [], [
                'supplier_quality_thresholds' => [
                    'reject_rate_warn_pct' => 10,
                    'min_epl' => 5,
                ],
            ]),
        ]);

        $this->seedLeadsForScorecard();

        $scorecard = app(SupplierQualityScorecardService::class)
            ->scorecard($this->supplier->fresh(), 30, $this->account->fresh());

        $this->assertCount(2, $scorecard['warnings']);
        $this->assertSame('F', $scorecard['quality_grade']);
    }

    public function test_quality_scorecard_json_endpoint(): void
    {
        $this->seedLeadsForScorecard();

        $this->ukHost()
            ->actingAs($this->admin)
            ->getJson(route('suppliers.quality-scorecard', ['supplier' => $this->supplier, 'days' => 30]))
            ->assertOk()
            ->assertJsonPath('scorecard.submitted', 10)
            ->assertJsonPath('scorecard.reject_rate_pct', 50.0)
            ->assertJsonPath('scorecard.epl', 4.0);
    }

    public function test_supplier_show_includes_quality_scorecard_props(): void
    {
        $this->seedLeadsForScorecard();

        $this->ukHost()
            ->actingAs($this->admin)
            ->get(route('suppliers.show', ['supplier' => $this->supplier, 'days' => 7]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Suppliers/Show')
                ->where('scorecardDays', 7)
                ->has('qualityScorecard', fn (Assert $card) => $card
                    ->where('days', 7)
                    ->where('submitted', 10)
                    ->etc()
                )
            );
    }

    protected function seedLeadsForScorecard(): void
    {
        foreach ([LeadStatus::Sold, LeadStatus::Sold, LeadStatus::Sold, LeadStatus::Sold] as $i => $status) {
            $this->createLead($status, now()->subDays($i), 10, 30);
        }

        $this->createLead(LeadStatus::Rejected, now()->subDay());
        $this->createLead(LeadStatus::Rejected, now()->subDays(2));
        $this->createLead(LeadStatus::Quarantined, now()->subDays(3));
        $this->createLead(LeadStatus::Quarantined, now()->subDays(4));
        $this->createLead(LeadStatus::Duplicate, now()->subDays(5));
        $this->createLead(LeadStatus::Accepted, now()->subDays(6));
    }

    protected function createLead(
        LeadStatus $status,
        $receivedAt,
        float $payout = 0,
        float $revenue = 0,
    ): Lead {
        $lead = Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'queue_id' => 'scorecard-'.uniqid(),
            'status' => $status,
            'field_data' => ['email' => uniqid('scorecard', true).'@test.com'],
            'received_at' => $receivedAt,
            'distributed_at' => $status === LeadStatus::Sold ? $receivedAt : null,
        ]);

        if ($payout > 0 || $revenue > 0) {
            LeadFinancial::create([
                'lead_id' => $lead->id,
                'payout' => $payout,
                'revenue' => $revenue,
                'margin' => $revenue - $payout,
                'currency' => 'GBP',
            ]);
        }

        return $lead;
    }
}
