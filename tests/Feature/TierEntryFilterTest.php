<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\DistributionConfig;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Services\Distribution\DistributionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierEntryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_tier_entry_filter_logs_event_and_skips_tier(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->firstOrFail();

        $delivery = $campaign->deliveries()->where('status', 'active')->firstOrFail();

        $campaign->distributionConfigs()->update(['is_active' => false]);

        $config = DistributionConfig::create([
            'campaign_id' => $campaign->id,
            'name' => 'Filtered tree',
            'is_active' => true,
            'config' => [
                'groups' => [
                    [
                        'name' => 'CA only',
                        'mode' => 'waterfall',
                        'delivery_ids' => [$delivery->id],
                        'rules' => [
                            'operator' => 'and',
                            'conditions' => [
                                ['field' => 'state', 'op' => 'eq', 'value' => 'CA'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $campaign->update(['use_advanced_distribution' => true]);

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => [
                'firstname' => 'Filter',
                'lastname' => 'Test',
                'email' => 'filter.test@demo.test',
                'state' => 'TX',
            ],
            'received_at' => now(),
        ]);

        app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertDatabaseHas('lead_events', [
            'lead_id' => $lead->id,
            'event_type' => 'distribution.tier_filtered',
        ]);

        $event = LeadEvent::where('lead_id', $lead->id)
            ->where('event_type', 'distribution.tier_filtered')
            ->first();

        $this->assertStringContainsString('state', $event->message);
        $this->assertSame('TX', $event->payload['lead_value']);
        $this->assertSame('CA only', $event->payload['tier']);
    }
}
