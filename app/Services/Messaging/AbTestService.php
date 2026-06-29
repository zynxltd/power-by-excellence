<?php

namespace App\Services\Messaging;

use App\Models\BulkSmsCampaign;
use App\Models\MessageSend;

class AbTestService
{
    public function shouldRunAbTest(BulkSmsCampaign $campaign): bool
    {
        $config = $campaign->ab_test ?? [];

        return filled($config['variant_a'] ?? null)
            && filled($config['variant_b'] ?? null)
            && ($config['status'] ?? 'pending') === 'pending';
    }

    public function runInitialSplit(BulkSmsCampaign $campaign, iterable $leads): void
    {
        $config = $campaign->ab_test ?? [];
        $splitPercent = (int) ($config['split_percent'] ?? 20);
        $variantA = $config['variant_a'] ?? [];
        $variantB = $config['variant_b'] ?? [];
        $leads = collect($leads);
        $sampleSize = (int) ceil($leads->count() * ($splitPercent / 100));
        $sample = $leads->shuffle()->take(max($sampleSize, 2));

        $half = (int) ceil($sample->count() / 2);
        $groupA = $sample->take($half);
        $groupB = $sample->skip($half);

        foreach ($groupA as $lead) {
            $this->sendVariant($campaign, $lead, 'A', $variantA);
        }

        foreach ($groupB as $lead) {
            $this->sendVariant($campaign, $lead, 'B', $variantB);
        }

        $campaign->update([
            'ab_test' => array_merge($config, [
                'status' => 'evaluating',
                'sample_sent_at' => now()->toIso8601String(),
                'sample_a' => $groupA->pluck('id')->all(),
                'sample_b' => $groupB->pluck('id')->all(),
            ]),
        ]);
    }

    public function evaluateAndSendWinner(BulkSmsCampaign $campaign): void
    {
        $config = $campaign->ab_test ?? [];

        if (($config['status'] ?? '') !== 'evaluating') {
            return;
        }

        $metric = $config['winner_metric'] ?? 'open';
        $eventType = $metric === 'click' ? 'click' : 'open';

        $rateA = $this->variantRate($campaign->id, 'A', $eventType);
        $rateB = $this->variantRate($campaign->id, 'B', $eventType);
        $winner = $rateA >= $rateB ? 'A' : 'B';
        $winningVariant = $winner === 'A' ? ($config['variant_a'] ?? []) : ($config['variant_b'] ?? []);

        $sampleIds = array_merge($config['sample_a'] ?? [], $config['sample_b'] ?? []);

        $campaign->update([
            'ab_test' => array_merge($config, [
                'status' => 'completed',
                'winner' => $winner,
                'rate_a' => $rateA,
                'rate_b' => $rateB,
            ]),
        ]);

        app(BulkCampaignSender::class)->sendRemainder($campaign, $winningVariant, $sampleIds);
    }

    protected function variantRate(int $campaignId, string $variant, string $eventType): float
    {
        $sends = MessageSend::withoutGlobalScopes()
            ->where('bulk_sms_campaign_id', $campaignId)
            ->where('ab_variant', $variant)
            ->count();

        if ($sends === 0) {
            return 0;
        }

        $events = MessageSend::withoutGlobalScopes()
            ->where('bulk_sms_campaign_id', $campaignId)
            ->where('ab_variant', $variant)
            ->whereHas('events', fn ($q) => $q->where('type', $eventType))
            ->count();

        return $events / $sends;
    }

    protected function sendVariant(BulkSmsCampaign $campaign, $lead, string $variant, array $variantConfig): void
    {
        app(BulkCampaignSender::class)->sendVariantToLead($campaign, $lead, $variant, $variantConfig);
    }
}
