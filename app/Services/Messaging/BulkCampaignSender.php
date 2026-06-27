<?php

namespace App\Services\Messaging;

use App\Models\BulkSmsCampaign;
use App\Models\Lead;
use App\Models\Segment;
use App\Services\Delivery\TagInterpolator;
use App\Services\Logging\PlatformLogger;

class BulkCampaignSender
{
    public function __construct(
        protected TagInterpolator $interpolator,
        protected MessageSendService $sender,
        protected SegmentService $segments,
        protected AbTestService $abTest,
        protected ThrottleGovernor $throttle,
    ) {}

    public function send(BulkSmsCampaign $campaign): BulkSmsCampaign
    {
        $campaign->update(['status' => 'sending']);

        $query = $this->buildQuery($campaign);
        $leads = $query->get();

        if ($this->abTest->shouldRunAbTest($campaign)) {
            $this->abTest->runInitialSplit($campaign, $leads);
            $config = $campaign->fresh()->ab_test ?? [];
            $waitMinutes = (int) ($config['wait_minutes'] ?? 60);
            \App\Jobs\EvaluateAbTestWinnerJob::dispatch($campaign->id)->delay(now()->addMinutes($waitMinutes));

            return $campaign->fresh();
        }

        $sent = 0;
        $failed = 0;
        $channel = $campaign->channel ?? 'sms';
        $chunkDelay = $this->throttle->chunkDelay($campaign->account_id, $campaign->throttle_per_minute);

        foreach ($leads as $lead) {
            if (! $this->throttle->allowSend($campaign->account_id)) {
                PlatformLogger::info('Bulk campaign paused by throttle', ['campaign_id' => $campaign->id]);
                break;
            }

            $ok = $this->sendToLead($campaign, $lead, $channel);
            $ok ? $sent++ : $failed++;

            if ($chunkDelay > 1 && ($sent + $failed) % 10 === 0) {
                sleep(min($chunkDelay, 5));
            }
        }

        $campaign->update([
            'status' => 'completed',
            'sent_count' => $sent,
            'failed_count' => $failed,
        ]);

        return $campaign->fresh();
    }

    /**
     * @param  array<string, mixed>  $variantConfig
     * @param  array<int, int>  $excludeLeadIds
     */
    public function sendRemainder(BulkSmsCampaign $campaign, array $variantConfig, array $excludeLeadIds = []): void
    {
        $query = $this->buildQuery($campaign);

        if ($excludeLeadIds) {
            $query->whereNotIn('id', $excludeLeadIds);
        }

        $channel = $campaign->channel ?? 'sms';
        $sent = (int) $campaign->sent_count;
        $failed = (int) $campaign->failed_count;

        foreach ($query->cursor() as $lead) {
            $fields = $lead->allFields();
            $recipient = $channel === 'email' ? ($fields['email'] ?? null) : ($fields['phone1'] ?? null);

            if (! $recipient) {
                $failed++;

                continue;
            }

            $subject = $this->interpolator->interpolate(
                $variantConfig['subject'] ?? $campaign->subject ?? $campaign->name,
                $fields,
            );
            $body = $this->interpolator->interpolate($variantConfig['body'] ?? $campaign->message, $fields);
            $htmlBody = filled($variantConfig['html_body'] ?? null)
                ? $this->interpolator->interpolate($variantConfig['html_body'], $fields)
                : ($campaign->html_body ? $this->interpolator->interpolate($campaign->html_body, $fields) : null);

            $ok = $this->sender->send([
                'account_id' => $campaign->account_id,
                'lead_id' => $lead->id,
                'bulk_sms_campaign_id' => $campaign->id,
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'html_body' => $htmlBody,
                'provider' => $campaign->provider,
                'source_type' => BulkSmsCampaign::class,
                'source_id' => $campaign->id,
                'ab_variant' => $campaign->ab_test['winner'] ?? null,
                'sending_profile_id' => $campaign->sending_profile_id,
            ]);

            $ok ? $sent++ : $failed++;
        }

        $campaign->update([
            'status' => 'completed',
            'sent_count' => $sent,
            'failed_count' => $failed,
        ]);
    }

    protected function sendToLead(BulkSmsCampaign $campaign, Lead $lead, string $channel): bool
    {
        $fields = $lead->allFields();
        $recipient = $channel === 'email' ? ($fields['email'] ?? null) : ($fields['phone1'] ?? null);

        if (! $recipient) {
            return false;
        }

        $subject = $this->interpolator->interpolate($campaign->subject ?? 'Message from us', $fields);
        $body = $this->interpolator->interpolate($campaign->message, $fields);
        $htmlBody = $campaign->html_body
            ? $this->interpolator->interpolate($campaign->html_body, $fields)
            : null;

        return $this->sender->send([
            'account_id' => $campaign->account_id,
            'lead_id' => $lead->id,
            'bulk_sms_campaign_id' => $campaign->id,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'html_body' => $htmlBody,
            'provider' => $campaign->provider,
            'source_type' => BulkSmsCampaign::class,
            'source_id' => $campaign->id,
            'sending_profile_id' => $campaign->sending_profile_id,
        ]);
    }

    protected function buildQuery(BulkSmsCampaign $campaign): \Illuminate\Database\Eloquent\Builder
    {
        if ($campaign->segment_id) {
            $segment = Segment::withoutGlobalScopes()->find($campaign->segment_id);

            if ($segment) {
                return $this->segments->leadsForSegment($segment);
            }
        }

        $query = Lead::query()->where('account_id', $campaign->account_id);

        if ($campaign->campaign_id) {
            $query->where('campaign_id', $campaign->campaign_id);
        }

        $filter = $campaign->filter ?? [];

        if (! empty($filter['status'])) {
            $query->where('status', $filter['status']);
        }
        if (! empty($filter['days'])) {
            $query->where('received_at', '>=', now()->subDays((int) $filter['days']));
        }
        if (! empty($filter['has_phone'])) {
            $query->whereNotNull('field_data->phone1');
        }
        if (! empty($filter['has_email'])) {
            $query->whereNotNull('field_data->email');
        }
        if (! empty($filter['tags'])) {
            $tags = (array) $filter['tags'];
            $query->whereHas('tags', fn ($q) => $q->whereIn('tag', $tags));
        }

        return $query;
    }
}
