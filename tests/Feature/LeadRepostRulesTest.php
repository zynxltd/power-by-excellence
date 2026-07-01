<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadRepostRulesTest extends TestCase
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

    public function test_campaign_max_attempts_lower_than_global_blocks_second_repost(): void
    {
        $campaign = Campaign::first();
        $campaign->update(['repost_config' => ['max_attempts' => 1]]);

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'queue_id' => 'repost-max-1',
            'status' => LeadStatus::Unsold,
            'field_data' => ['email' => 'max@test.com'],
            'received_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('leads.repost', $lead))
            ->assertRedirect()
            ->assertSessionHas('success');

        $lead->update(['status' => LeadStatus::Unsold]);

        $this->actingAs($this->admin)
            ->post(route('leads.repost', $lead))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_cooldown_blocks_immediate_repost(): void
    {
        $campaign = Campaign::first();
        $campaign->update(['repost_config' => ['cooldown_minutes' => 60]]);

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'queue_id' => 'repost-cooldown',
            'status' => LeadStatus::Unsold,
            'field_data' => ['email' => 'cooldown@test.com'],
            'received_at' => now()->subHours(2),
            'metadata' => [
                'repost_attempts' => 0,
                'last_reposted_at' => now()->toIso8601String(),
            ],
        ]);

        $this->actingAs($this->admin)
            ->post(route('leads.repost', $lead))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_min_age_blocks_young_lead(): void
    {
        $campaign = Campaign::first();
        $campaign->update(['repost_config' => ['min_age_minutes' => 30]]);

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'queue_id' => 'repost-min-age',
            'status' => LeadStatus::Unsold,
            'field_data' => ['email' => 'young@test.com'],
            'received_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('leads.repost', $lead))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_repost_succeeds_when_campaign_rules_allow(): void
    {
        $campaign = Campaign::first();
        $campaign->update([
            'repost_config' => [
                'enabled' => true,
                'max_attempts' => 2,
                'min_age_minutes' => 5,
                'cooldown_minutes' => 10,
            ],
        ]);

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'queue_id' => 'repost-eligible',
            'status' => LeadStatus::Unsold,
            'field_data' => ['email' => 'eligible@test.com'],
            'received_at' => now()->subMinutes(30),
            'metadata' => ['repost_attempts' => 0],
        ]);

        $this->actingAs($this->admin)
            ->post(route('leads.repost', $lead))
            ->assertRedirect()
            ->assertSessionHas('success');

        $lead->refresh();
        $this->assertSame(LeadStatus::Accepted, $lead->status);
        $this->assertSame(1, $lead->metadata['repost_attempts'] ?? 0);
        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class);
    }

    public function test_admin_can_update_campaign_repost_rules(): void
    {
        $campaign = Campaign::first();

        $this->actingAs($this->admin)
            ->patch(route('campaigns.repost-rules.update', $campaign), [
                'repost_config' => [
                    'enabled' => true,
                    'max_attempts' => 5,
                    'min_age_minutes' => 15,
                    'cooldown_minutes' => 20,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $campaign->refresh();
        $this->assertSame(5, $campaign->repost_config['max_attempts']);
        $this->assertSame(15, $campaign->repost_config['min_age_minutes']);
        $this->assertSame(20, $campaign->repost_config['cooldown_minutes']);
    }
}
