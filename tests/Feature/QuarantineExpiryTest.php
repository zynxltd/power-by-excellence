<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QuarantineExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_unsold_quarantine_is_released(): void
    {
        Queue::fake();

        $account = Account::create([
            'name' => 'Test', 'slug' => 'test', 'default_currency' => 'GBP', 'default_country' => 'GB',
        ]);
        $campaign = Campaign::create([
            'account_id' => $account->id, 'name' => 'C', 'reference' => 'c', 'floor_price' => 5,
        ]);

        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => LeadStatus::Quarantined,
            'quarantined_until' => now()->subHour(),
            'field_data' => ['email' => 'test@example.com'],
            'metadata' => ['quarantine_reason' => 'unsold'],
            'received_at' => now(),
        ]);

        $this->artisan('quarantine:process-expired')->assertSuccessful();

        $lead->refresh();
        $this->assertSame(LeadStatus::Accepted, $lead->status);
        $this->assertNull($lead->quarantined_until);
    }

    public function test_expired_validation_quarantine_is_rejected(): void
    {
        $account = Account::create([
            'name' => 'Test', 'slug' => 'test', 'default_currency' => 'GBP', 'default_country' => 'GB',
        ]);
        $campaign = Campaign::create([
            'account_id' => $account->id, 'name' => 'C', 'reference' => 'c2', 'floor_price' => 5,
        ]);

        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => LeadStatus::Quarantined,
            'quarantined_until' => now()->subHour(),
            'field_data' => ['email' => 'bad@example.com'],
            'metadata' => ['quarantine_reason' => 'validation'],
            'received_at' => now(),
        ]);

        $this->artisan('quarantine:process-expired')->assertSuccessful();

        $lead->refresh();
        $this->assertSame(LeadStatus::Rejected, $lead->status);
    }
}
