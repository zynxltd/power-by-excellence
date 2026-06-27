<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuarantineAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_quarantine_index_lists_held_leads_with_reason(): void
    {
        $this->withoutVite();

        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'q@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => [
                'quarantine_reason' => 'out_of_hours',
                'quarantine_message' => 'Out of hours - held for next delivery window',
            ],
        ]);

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->get('/quarantine')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Quarantine/Index')
                ->has('stats')
                ->has('filters')
                ->where('stats.total', fn ($v) => $v >= 1)
            );
    }

    public function test_quarantine_filter_by_reason(): void
    {
        $this->withoutVite();

        $campaign = Campaign::where('reference', 'loans-uk')->first();

        Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'ooh@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => ['quarantine_reason' => 'out_of_hours'],
        ]);

        Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'val@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => ['email_validation' => ['status' => 'invalid']],
        ]);

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $host = $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);

        $host->actingAs($admin)
            ->get('/quarantine?reason=out_of_hours')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.reason', 'out_of_hours')
                ->has('leads.data', 1)
            );
    }

    public function test_bulk_reject_quarantined_leads(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'bulk@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
        ]);

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->post('/quarantine/bulk-reject', ['lead_ids' => [$lead->id]])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame(LeadStatus::Rejected, $lead->status);
        $this->assertNull($lead->quarantined_until);
    }

    public function test_extend_quarantine_hold(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $until = now()->addHours(6);

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'extend@test.test'],
            'received_at' => now(),
            'quarantined_until' => $until,
        ]);

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->post(route('quarantine.extend', $lead, absolute: false), ['hours' => 24])
            ->assertRedirect();

        $lead->refresh();
        $this->assertTrue($lead->quarantined_until->greaterThan($until));
    }
}
