<?php

namespace App\Services\Automation;

use App\Jobs\RunAutomationStepJob;
use App\Models\AutomationSequence;
use App\Models\Lead;
use App\Services\Delivery\TagInterpolator;
use App\Services\Logging\PlatformLogger;
use Illuminate\Support\Facades\Mail;

class AutomationSequenceService
{
    public function __construct(
        protected TagInterpolator $interpolator,
    ) {}

    public function dispatchForLead(Lead $lead, string $triggerEvent): void
    {
        $sequences = AutomationSequence::query()
            ->where('status', 'active')
            ->where('trigger_event', $triggerEvent)
            ->where(function ($q) use ($lead) {
                $q->whereNull('campaign_id')->orWhere('campaign_id', $lead->campaign_id);
            })
            ->with('steps')
            ->get();

        foreach ($sequences as $sequence) {
            foreach ($sequence->steps as $step) {
                if ($step->delay_minutes > 0) {
                    RunAutomationStepJob::dispatch($lead->id, $step->id)->delay(now()->addMinutes($step->delay_minutes));
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

        if ($step->channel === 'email') {
            $to = $fields[$config['to_field'] ?? 'email'] ?? null;
            if (! $to) {
                return;
            }
            $subject = $this->interpolator->interpolate($config['subject'] ?? 'Follow up', $fields);
            $body = $this->interpolator->interpolate($config['body'] ?? '', $fields);
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } elseif ($step->channel === 'sms') {
            $to = $fields[$config['to_field'] ?? 'phone1'] ?? null;
            if (! $to) {
                return;
            }
            $message = $this->interpolator->interpolate($config['body'] ?? $config['message'] ?? '', $fields);
            PlatformLogger::info('Automation SMS queued', ['lead_id' => $lead->id, 'to' => $to, 'message' => $message]);
        }

        PlatformLogger::leadEvent($lead, 'automation.step_sent', "Sequence step: {$step->channel}", [
            'step_id' => $step->id,
            'delay_minutes' => $step->delay_minutes,
        ]);
    }
}
