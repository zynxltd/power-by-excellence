<?php

namespace Tests\Feature;

use App\Models\AutoResponder;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Services\Automation\AutoResponderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoResponderDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_email_auto_responder_dispatches_on_lead_sold(): void
    {
        $campaign = Campaign::first();
        $lead = Lead::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($lead);

        AutoResponder::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'name' => 'Sold thank you email',
            'channel' => 'email',
            'trigger_event' => 'on_lead_sold',
            'status' => 'active',
            'config' => [
                'subject' => 'Thanks {{firstname}}',
                'body' => 'We received your enquiry.',
                'to_field' => 'email',
                'provider' => 'smtp',
            ],
        ]);

        app(AutoResponderService::class)->dispatchForLead($lead, 'on_lead_sold');

        $this->assertTrue(
            LeadEvent::where('lead_id', $lead->id)->where('event_type', 'auto_responder.sent')->exists()
        );
    }

    public function test_sms_auto_responder_skips_when_phone_missing(): void
    {
        $campaign = Campaign::first();
        $lead = Lead::where('campaign_id', $campaign->id)->first();
        $lead->update(['field_data' => array_merge($lead->field_data ?? [], ['phone1' => ''])]);

        AutoResponder::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'name' => 'Sold SMS',
            'channel' => 'sms',
            'trigger_event' => 'on_lead_sold',
            'status' => 'active',
            'config' => [
                'body' => 'Thanks for your call.',
                'to_field' => 'phone1',
            ],
        ]);

        app(AutoResponderService::class)->dispatchForLead($lead->fresh(), 'on_lead_sold');

        $this->assertFalse(
            LeadEvent::where('lead_id', $lead->id)->where('event_type', 'auto_responder.sent')->exists()
        );
    }
}
