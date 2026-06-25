<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Support\Delivery\DeliveryLogClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryLogClassifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_ping_url_is_internal_failure(): void
    {
        $log = DeliveryLog::make([
            'status' => 'failed',
            'skipped_reason' => 'missing_ping_url',
        ]);

        $this->assertTrue(DeliveryLogClassifier::isInternalFailure($log));
    }

    public function test_post_rejected_is_buyer_failure(): void
    {
        $log = DeliveryLog::make([
            'status' => 'failed',
            'skipped_reason' => 'post_rejected',
            'http_status' => 422,
        ]);

        $this->assertFalse(DeliveryLogClassifier::isInternalFailure($log));
    }

    public function test_exception_response_is_internal_failure(): void
    {
        $log = DeliveryLog::make([
            'status' => 'failed',
            'post_response' => ['error' => 'Connection refused'],
        ]);

        $this->assertTrue(DeliveryLogClassifier::isInternalFailure($log));
    }

    public function test_scope_internal_failures_on_query(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => 'accepted',
            'field_data' => ['email' => 'classifier@test.test'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'status' => 'failed',
            'skipped_reason' => 'missing_post_url',
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'status' => 'failed',
            'skipped_reason' => 'post_rejected',
        ]);

        $internal = DeliveryLogClassifier::scopeInternalFailures(DeliveryLog::query())->count();
        $this->assertSame(1, $internal);
    }
}
