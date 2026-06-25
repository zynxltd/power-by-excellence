<?php

namespace App\Services\Automation;

use App\Models\BulkSmsCampaign;
use App\Models\Lead;
use App\Services\Delivery\TagInterpolator;
use App\Services\Logging\PlatformLogger;
use App\Services\Messaging\MessagingGateway;

class BulkSmsService
{
    public function __construct(
        protected TagInterpolator $interpolator,
        protected MessagingGateway $messaging,
    ) {}

    public function send(BulkSmsCampaign $campaign): BulkSmsCampaign
    {
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

        $channel = $campaign->channel ?? 'sms';
        $sent = 0;
        $failed = 0;

        $query->chunkById(100, function ($leads) use ($campaign, $channel, &$sent, &$failed) {
            foreach ($leads as $lead) {
                $fields = $lead->allFields();

                if ($channel === 'email') {
                    $to = $fields['email'] ?? null;
                    if (! $to) {
                        $failed++;

                        continue;
                    }

                    $subject = $this->interpolator->interpolate($campaign->subject ?? 'Message from us', $fields);
                    $body = $this->interpolator->interpolate($campaign->message, $fields);
                    $ok = $this->messaging->sendEmail($to, $subject, $body, [
                        'provider' => $campaign->provider,
                    ]);
                } else {
                    $to = $fields['phone1'] ?? null;
                    if (! $to) {
                        $failed++;

                        continue;
                    }

                    $message = $this->interpolator->interpolate($campaign->message, $fields);
                    $ok = $this->messaging->sendSms($to, $message, [
                        'provider' => $campaign->provider,
                    ]);
                }

                if ($ok) {
                    $sent++;
                    PlatformLogger::info('Bulk message sent', [
                        'campaign_id' => $campaign->id,
                        'lead_id' => $lead->id,
                        'channel' => $channel,
                        'to' => $to,
                    ]);
                } else {
                    $failed++;
                }
            }
        });

        $campaign->update([
            'status' => 'completed',
            'sent_count' => $sent,
            'failed_count' => $failed,
        ]);

        return $campaign->fresh();
    }
}
