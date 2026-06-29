<?php

namespace App\Services\Automation;

use App\Models\AutomationSequence;
use App\Models\AutomationSequenceEnrollment;
use App\Models\AutomationSequenceStep;
use App\Models\Lead;
use App\Models\MessageSend;
use App\Models\MessageTemplate;
use App\Models\Segment;
use App\Services\Delivery\TagInterpolator;
use App\Services\Logging\PlatformLogger;
use App\Services\Messaging\MessageSendService;
use App\Services\Messaging\SegmentService;
use App\Services\Messaging\TemplateRenderService;

class AutomationSequenceService
{
    public function __construct(
        protected TagInterpolator $interpolator,
        protected MessageSendService $sender,
        protected TemplateRenderService $templateRender,
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
            $this->enrollLead($lead, $sequence);
        }
    }

    public function dispatchForSegmentEntry(Lead $lead): void
    {
        $sequences = AutomationSequence::query()
            ->where('account_id', $lead->account_id)
            ->where('status', 'active')
            ->where('trigger_event', 'on_segment_entry')
            ->whereNotNull('segment_id')
            ->with('steps')
            ->get();

        $segmentService = app(SegmentService::class);

        foreach ($sequences as $sequence) {
            $segment = Segment::find($sequence->segment_id);
            if (! $segment) {
                continue;
            }

            if ($segmentService->leadsForSegment($segment)->where('leads.id', $lead->id)->exists()) {
                $this->enrollLead($lead, $sequence);
            }
        }
    }

    public function enrollLead(Lead $lead, AutomationSequence $sequence): ?AutomationSequenceEnrollment
    {
        $sequence->loadMissing('steps');
        $firstStep = $sequence->steps->sortBy('sort_order')->first();

        if (! $firstStep) {
            return null;
        }

        $enrollment = AutomationSequenceEnrollment::firstOrCreate(
            [
                'automation_sequence_id' => $sequence->id,
                'lead_id' => $lead->id,
            ],
            [
                'account_id' => $lead->account_id,
                'current_step_order' => 0,
                'status' => 'active',
                'next_run_at' => now()->addMinutes((int) ($firstStep->delay_minutes ?? 0)),
            ],
        );

        if (! $enrollment->wasRecentlyCreated) {
            return null;
        }

        if ($enrollment->next_run_at && $enrollment->next_run_at->lte(now())) {
            $this->processEnrollment($enrollment->fresh(['sequence.steps', 'lead']));
        }

        return $enrollment;
    }

    public function processDueEnrollments(): int
    {
        $processed = 0;

        AutomationSequenceEnrollment::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->with(['sequence.steps', 'lead'])
            ->orderBy('next_run_at')
            ->limit(500)
            ->get()
            ->each(function (AutomationSequenceEnrollment $enrollment) use (&$processed) {
                $this->processEnrollment($enrollment);
                $processed++;
            });

        return $processed;
    }

    public function processEnrollment(AutomationSequenceEnrollment $enrollment): void
    {
        if ($enrollment->status !== 'active') {
            return;
        }

        $enrollment->loadMissing(['sequence.steps', 'lead']);
        $sequence = $enrollment->sequence;
        $lead = $enrollment->lead;

        if (! $sequence || ! $lead || $sequence->status !== 'active') {
            $enrollment->update(['status' => 'cancelled', 'next_run_at' => null]);

            return;
        }

        $steps = $sequence->steps->sortBy('sort_order')->values();
        $index = (int) $enrollment->current_step_order;

        if ($index >= $steps->count()) {
            $enrollment->update(['status' => 'completed', 'next_run_at' => null]);

            return;
        }

        $step = $steps[$index];

        if (! $this->passesBranch($lead, $step, $enrollment)) {
            $this->advanceEnrollment($enrollment, $steps, $index);

            return;
        }

        $action = $step->action ?? 'send';

        if ($action === 'send_template') {
            $this->runTemplateStep($lead, $step);
        } elseif ($action !== 'wait') {
            $this->runStep($lead, $step);
        }

        $this->advanceEnrollment($enrollment, $steps, $index);
    }

    public function runStep(Lead $lead, AutomationSequenceStep $step): void
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
                $htmlBody = filled($config['html_body'] ?? null)
                    ? $this->interpolator->interpolate($config['html_body'], $fields)
                    : null;

                $this->sender->send([
                    'account_id' => $lead->account_id,
                    'lead_id' => $lead->id,
                    'channel' => 'email',
                    'recipient' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'html_body' => $htmlBody,
                    'provider' => $provider,
                    'sending_profile_id' => $config['sending_profile_id'] ?? null,
                    'source_type' => 'automation_sequence_step',
                    'source_id' => $step->id,
                ]);
            } elseif ($step->channel === 'sms') {
                $to = $fields[$config['to_field'] ?? 'phone1'] ?? null;
                if (! $to) {
                    return;
                }

                $message = $this->interpolator->interpolate($config['body'] ?? $config['message'] ?? '', $fields);

                $this->sender->send([
                    'account_id' => $lead->account_id,
                    'lead_id' => $lead->id,
                    'channel' => 'sms',
                    'recipient' => $to,
                    'body' => $message,
                    'provider' => $provider,
                    'source_type' => 'automation_sequence_step',
                    'source_id' => $step->id,
                    'track' => false,
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

    protected function runTemplateStep(Lead $lead, AutomationSequenceStep $step): void
    {
        $config = $step->config ?? [];
        $templateId = $config['message_template_id'] ?? null;

        if (! $templateId) {
            return;
        }

        $template = MessageTemplate::query()
            ->where('account_id', $lead->account_id)
            ->find($templateId);

        if (! $template) {
            return;
        }

        $rendered = $this->templateRender->renderTemplate($template, $lead);
        $fields = $lead->fresh()->allFields();
        $toField = $config['to_field'] ?? ($step->channel === 'email' ? 'email' : 'phone1');
        $recipient = $fields[$toField] ?? null;

        if (! $recipient) {
            return;
        }

        try {
            $this->sender->send([
                'account_id' => $lead->account_id,
                'lead_id' => $lead->id,
                'channel' => $step->channel,
                'recipient' => $recipient,
                'subject' => $rendered['subject'],
                'body' => $rendered['body'] ?? '',
                'html_body' => $rendered['html_body'],
                'provider' => $config['provider'] ?? null,
                'sending_profile_id' => $config['sending_profile_id'] ?? null,
                'source_type' => 'automation_sequence_step',
                'source_id' => $step->id,
                'track' => $step->channel === 'email',
            ]);

            PlatformLogger::leadEvent($lead, 'automation.step_sent', "Sequence template step: {$step->channel}", [
                'step_id' => $step->id,
                'message_template_id' => $template->id,
            ]);
        } catch (\Throwable $e) {
            PlatformLogger::error('Automation sequence template step failed', [
                'step_id' => $step->id,
                'lead_id' => $lead->id,
                'message_template_id' => $templateId,
            ], $lead, $e);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AutomationSequenceStep>  $steps
     */
    protected function advanceEnrollment(
        AutomationSequenceEnrollment $enrollment,
        $steps,
        int $currentIndex,
    ): void {
        $nextIndex = $currentIndex + 1;

        if ($nextIndex >= $steps->count()) {
            $enrollment->update([
                'status' => 'completed',
                'current_step_order' => $nextIndex,
                'next_run_at' => null,
            ]);

            return;
        }

        $nextStep = $steps[$nextIndex];
        $delay = (int) ($nextStep->delay_minutes ?? 0);

        $enrollment->update([
            'current_step_order' => $nextIndex,
            'next_run_at' => now()->addMinutes($delay),
        ]);
    }

    protected function passesBranch(Lead $lead, AutomationSequenceStep $step, AutomationSequenceEnrollment $enrollment): bool
    {
        $branch = $step->config['branch'] ?? null;

        if (! $branch) {
            return true;
        }

        $since = $enrollment->created_at;

        $baseQuery = MessageSend::query()
            ->where('lead_id', $lead->id)
            ->where('source_type', 'automation_sequence_step');

        $hasOpen = (clone $baseQuery)->whereHas('events', fn ($q) => $q
            ->where('type', 'open')
            ->where('occurred_at', '>=', $since))->exists();

        $hasClick = (clone $baseQuery)->whereHas('events', fn ($q) => $q
            ->where('type', 'click')
            ->where('occurred_at', '>=', $since))->exists();

        return match ($branch) {
            'opened' => $hasOpen,
            'clicked' => $hasClick,
            'not_opened' => ! $hasOpen,
            default => true,
        };
    }
}
