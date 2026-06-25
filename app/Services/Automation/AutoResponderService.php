<?php

namespace App\Services\Automation;

use App\Models\AutoResponder;
use App\Models\Lead;
use App\Services\Delivery\TagInterpolator;
use App\Services\Logging\PlatformLogger;
use App\Services\Messaging\MessagingGateway;

class AutoResponderService
{
    public function __construct(
        protected TagInterpolator $interpolator,
        protected MessagingGateway $messaging,
    ) {}

    public function dispatchForLead(Lead $lead, string $triggerEvent): void
    {
        $query = AutoResponder::query()
            ->where('status', 'active')
            ->where('trigger_event', $triggerEvent)
            ->where(function ($q) use ($lead) {
                $q->whereNull('campaign_id')->orWhere('campaign_id', $lead->campaign_id);
            });

        foreach ($query->get() as $responder) {
            $this->send($responder, $lead);
        }
    }

    protected function send(AutoResponder $responder, Lead $lead): void
    {
        $fields = $lead->allFields();
        $config = $responder->config ?? [];
        $provider = $config['provider'] ?? null;

        try {
            if ($responder->channel === 'email') {
                $toField = $config['to_field'] ?? 'email';
                $to = $fields[$toField] ?? null;
                if (! $to) {
                    return;
                }

                $subject = $this->interpolator->interpolate($config['subject'] ?? 'Thank you', $fields);
                $body = $this->interpolator->interpolate($config['body'] ?? '', $fields);

                $this->messaging->sendEmail($to, $subject, $body, [
                    'provider' => $provider,
                ]);
            } elseif ($responder->channel === 'sms') {
                $toField = $config['to_field'] ?? 'phone1';
                $to = $fields[$toField] ?? null;
                if (! $to) {
                    return;
                }

                $message = $this->interpolator->interpolate($config['body'] ?? $config['message'] ?? '', $fields);
                $this->messaging->sendSms($to, $message, [
                    'provider' => $provider,
                ]);
            }

            PlatformLogger::leadEvent($lead, 'auto_responder.sent', "Auto responder: {$responder->name}", [
                'responder_id' => $responder->id,
                'channel' => $responder->channel,
                'provider' => $provider,
            ]);
        } catch (\Throwable $e) {
            PlatformLogger::error('Auto responder failed', [
                'responder_id' => $responder->id,
                'lead_id' => $lead->id,
            ], $lead, $e);
        }
    }
}
