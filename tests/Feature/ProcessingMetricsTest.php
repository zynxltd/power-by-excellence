<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Services\Platform\ProcessingMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessingMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_avg_processing_uses_lead_processing_ms_not_delivery_logs(): void
    {
        $campaign = \App\Models\Campaign::where('reference', 'loans-uk')->first();

        Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => 'sold',
            'field_data' => ['email' => 'fast@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
            'processing_ms' => 120,
        ]);

        Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => 'sold',
            'field_data' => ['email' => 'fast2@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
            'processing_ms' => 160,
        ]);

        $metrics = app(ProcessingMetrics::class);

        $this->assertSame(140.0, $metrics->avgProcessingMs($campaign->account_id));
        $this->assertTrue($metrics->withinTarget($campaign->account_id));
    }
}
