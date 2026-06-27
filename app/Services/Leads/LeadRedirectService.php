<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Services\Logging\PlatformLogger;

class LeadRedirectService
{
    public function resolveDeclineDestination(Lead $lead): ?string
    {
        $lead->loadMissing('campaign.distributionConfigs');

        if (! $lead->campaign?->use_advanced_distribution) {
            return null;
        }

        $config = $lead->campaign->distributionConfigs->firstWhere('is_active', true);
        $declineUrl = $config?->config['decline_url'] ?? null;

        return filled($declineUrl) ? $declineUrl : null;
    }

    public function offerDecline(Lead $lead): ?string
    {
        if ($lead->redirect_offered_at) {
            return $this->trackedUrl($lead);
        }

        $destination = $this->resolveDeclineDestination($lead);

        if (! $destination) {
            return null;
        }

        $lead->update([
            'redirect_url' => $destination,
            'redirect_offered_at' => now(),
        ]);

        PlatformLogger::leadEvent($lead, 'decline.offered', 'Consumer decline URL offered', [
            'destination' => $destination,
        ]);

        return $this->trackedUrl($lead);
    }

    public function publicDeclineUrl(Lead $lead): ?string
    {
        if (! in_array($lead->status->value, ['unsold', 'quarantined'], true)) {
            return null;
        }

        if (! $lead->redirect_url) {
            return $this->offerDecline($lead->fresh());
        }

        return $this->trackedUrl($lead);
    }

    public function resolveDestinationUrl(Lead $lead): ?string
    {
        $soldDelivery = $lead->deliveryLogs()
            ->with('delivery:id,config,campaign_id')
            ->where('status', 'success')
            ->latest()
            ->first();

        if (! $soldDelivery) {
            return null;
        }

        $lead->loadMissing('campaign.distributionConfigs');

        if ($lead->campaign?->use_advanced_distribution) {
            $config = $lead->campaign->distributionConfigs->firstWhere('is_active', true);

            foreach ($config?->config['groups'] ?? [] as $group) {
                if (in_array($soldDelivery->delivery_id, $group['delivery_ids'] ?? [], true)) {
                    if (filled($group['redirect_url'] ?? null)) {
                        return $group['redirect_url'];
                    }

                    break;
                }
            }
        }

        return $soldDelivery->delivery?->config['redirect_url']
            ?? $soldDelivery->delivery?->config['accept_url']
            ?? null;
    }

    public function offerRedirect(Lead $lead): ?string
    {
        if ($lead->redirect_offered_at) {
            return $this->trackedUrl($lead);
        }

        $destination = $this->resolveDestinationUrl($lead);

        if (! $destination) {
            return null;
        }

        $winningDeliveryId = $lead->deliveryLogs()
            ->where('status', 'success')
            ->latest()
            ->value('delivery_id');

        $lead->update([
            'redirect_url' => $destination,
            'redirect_offered_at' => now(),
            'winning_delivery_id' => $winningDeliveryId,
        ]);

        PlatformLogger::leadEvent($lead, 'redirect.offered', 'Consumer redirect URL offered', [
            'destination' => $destination,
            'winning_delivery_id' => $winningDeliveryId,
        ]);

        return $this->trackedUrl($lead);
    }

    public function trackedUrl(Lead $lead): string
    {
        return url('/r/'.$lead->uuid);
    }

    public function publicRedirectUrl(Lead $lead): ?string
    {
        if (! $lead->redirect_url) {
            return $this->offerRedirect($lead);
        }

        return $this->trackedUrl($lead);
    }

    public function follow(Lead $lead): ?string
    {
        if (! $lead->redirect_url) {
            return null;
        }

        if (! $lead->redirect_followed_at) {
            $lead->update(['redirect_followed_at' => now()]);

            PlatformLogger::leadEvent($lead, 'redirect.followed', 'Consumer followed redirect', [
                'destination' => $lead->redirect_url,
            ]);
        }

        return $lead->redirect_url;
    }
}
