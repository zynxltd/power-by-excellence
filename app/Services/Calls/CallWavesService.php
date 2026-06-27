<?php

namespace App\Services\Calls;

use App\Models\Campaign;

class CallWavesService
{
    public function __construct(
        protected CallAnalyticsService $analytics,
    ) {}

    /**
     * Suggest channel mix changes based on call vs lead performance.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestions(?int $accountId = null): array
    {
        $suggestions = [];
        $summary = $this->analytics->summary($accountId);
        $byCampaign = $this->analytics->byCampaign($accountId);

        if ($summary['connect_rate'] < 30 && $summary['total_calls'] >= 10) {
            $suggestions[] = [
                'type' => 'connect_rate',
                'severity' => 'warning',
                'message' => 'Connect rate is below 30%. Consider tightening IVR qualification or adjusting buyer ping timeouts.',
                'metric' => $summary['connect_rate'],
            ];
        }

        if ($summary['avg_revenue_per_call'] > 0 && $summary['conversion_rate'] < 20) {
            $suggestions[] = [
                'type' => 'conversion',
                'severity' => 'info',
                'message' => 'Low call sell-through despite revenue potential. Try parallel auction routing or lower floor prices.',
                'metric' => $summary['conversion_rate'],
            ];
        }

        foreach ($byCampaign as $row) {
            if ($row['total'] >= 5 && $row['sold'] === 0) {
                $suggestions[] = [
                    'type' => 'campaign_unsold',
                    'severity' => 'warning',
                    'message' => "Campaign \"{$row['campaign_name']}\" has {$row['total']} calls with zero sales. Review buyer destinations and caps.",
                    'campaign_id' => $row['campaign_id'],
                ];
            }
        }

        $hybridCandidates = Campaign::when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->where('channel', 'call')
            ->where('status', 'active')
            ->get()
            ->filter(fn (Campaign $c) => ($c->call_settings['fallback_campaign_id'] ?? null) === null);

        foreach ($hybridCandidates as $campaign) {
            $campaignStats = collect($byCampaign)->firstWhere('campaign_id', $campaign->id);
            if ($campaignStats && $campaignStats['total'] >= 10 && ($campaignStats['sold'] / max(1, $campaignStats['total'])) < 0.5) {
                $suggestions[] = [
                    'type' => 'hybrid',
                    'severity' => 'info',
                    'message' => "Enable hybrid fallback on \"{$campaign->name}\" to route unsold calls into the lead ping tree.",
                    'campaign_id' => $campaign->id,
                ];
            }
        }

        return $suggestions;
    }
}
