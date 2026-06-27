<?php

namespace App\Services\Automation;

use App\Jobs\RunAutomationStepJob;
use App\Models\AutomationSequence;
use App\Models\Lead;
use App\Services\Delivery\TagInterpolator;
use App\Services\Logging\PlatformLogger;
use App\Services\Messaging\MessagingGateway;

class AutomationSequenceService
{
    public function __construct(
        protected TagInterpolator $interpolator,
        protected MessagingGateway $messaging,
    ) {}

    public function dispatchForLead(Lead $lead, string $triggerEvent): void
    {
        $sequences = AutomationSequence::query()
            ->where('account_id', $lead->account_id)
            ->where('status', 'active')
            ->where('trigger_event', $triggerEvent)
            ->where(function ($q) use ($lead) {
                $q->whereNull('campaign_id')->orWhere('campaign_id', $lead->campaign_id);
            })
            ->with('steps')
            ->get();

        foreach ($sequences as $sequence) {
            $cumulativeDelay = 0;

            foreach ($sequence->steps as $step) {
                $cumulativeDelay += (int) ($step->delay_minutes ?? 0);

                if ($cumulativeDelay > 0) {
                    RunAutomationStepJob::dispatch($lead->id, $step->id)
                        ->delay(now()->addMinutes($cumulativeDelay));
                } else {
                    $this->runStep($lead, $step);
                }
            }
        }
    }

    public function runStep(Lead $lead, $step): void
    {
        $fields = $lead->fresh()->allFields();
        $config = $step->config ?? [];
        $provider = $config['provider'] ?? null;

        try {
            if ($step->channel === 'email') {
                $to = $fields[$config['to_field'] ?? 'email'] ?? null;
                if (! $to) {
                    return;
                }

                $subject = $this->interpolator->interpolate($config['subject'] ?? 'Follow up', $fields);
                $body = $this->interpolator->interpolate($config['body'] ?? '', $fields);

                $this->messaging->sendEmail($to, $subject, $body, [
                    'provider' => $provider,
                ]);
            } elseif ($step->channel === 'sms') {
                $to = $fields[$config['to_field'] ?? 'phone1'] ?? null;
                if (! $to) {
                    return;
                }

                $message = $this->interpolator->interpolate($config['body'] ?? $config['message'] ?? '', $fields);

                $this->messaging->sendSms($to, $message, [
                    'provider' => $provider,
                ]);
            }

            PlatformLogger::leadEvent($lead, 'automation.step_sent', "Sequence step: {$step->channel}", [
                'step_id' => $step->id,
                'delay_minutes' => $step->delay_minutes,
                'provider' => $provider,
            ]);
        } catch (\Throwable $e) {
            PlatformLogger::error('Automation sequence step failed', [
                'step_id' => $step->id,
                'lead_id' => $lead->id,
                'channel' => $step->channel,
            ], $lead, $e);
        }
    }
}
