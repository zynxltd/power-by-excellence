<?php

namespace App\Services\Calls;

use App\Enums\LeadStatus;
use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\Lead;
use App\Support\Queue\LeadJobDispatcher;

class CallHybridService
{
    public function createLeadFromCall(CallSession $session): ?Lead
    {
        $campaign = $session->campaign;

        if (! $campaign) {
            return null;
        }

        $fallbackCampaignId = $campaign->call_settings['fallback_campaign_id'] ?? null;

        if (! $fallbackCampaignId) {
            return null;
        }

        $targetCampaign = Campaign::find($fallbackCampaignId);

        if (! $targetCampaign || $targetCampaign->account_id !== $session->account_id) {
            return null;
        }

        $fieldData = [
            'phone1' => $session->caller_number,
            'city' => $session->caller_city,
            'state' => $session->caller_state,
            'country' => $session->caller_country,
            ...($session->ivr_data ?? []),
        ];

        $lead = Lead::create([
            'account_id' => $session->account_id,
            'campaign_id' => $targetCampaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => $fieldData,
            'metadata' => ['source_call_uuid' => $session->uuid],
            'sid' => $session->sid,
            'ssid' => $session->ssid,
            'received_at' => now(),
        ]);

        $session->update(['lead_id' => $lead->id]);

        LeadJobDispatcher::dispatch($lead->id);

        return $lead;
    }
}
