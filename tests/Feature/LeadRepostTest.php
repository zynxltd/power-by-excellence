<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadRepostTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        Queue::fake();
    }

    public function test_admin_can_repost_unsold_lead(): void
    {
        $campaign = \App\Models\Campaign::first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'queue_id' => 'repost-test',
            'status' => LeadStatus::Unsold,
            'field_data' => ['email' => 'repost@test.com'],
            'received_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('leads.repost', $lead))
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame(LeadStatus::Accepted, $lead->status);
        $this->assertSame(1, $lead->metadata['repost_attempts'] ?? 0);
        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class);
    }

    public function test_validation_quarantine_cannot_be_reposted(): void
    {
        $campaign = \App\Models\Campaign::first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'queue_id' => 'validation-quarantine',
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'bad@test.com'],
            'metadata' => ['quarantine_reason' => 'validation', 'email_validation' => 'invalid'],
            'quarantined_until' => now()->addDay(),
            'received_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('leads.repost', $lead))
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
