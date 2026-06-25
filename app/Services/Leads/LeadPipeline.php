<?php

namespace App\Services\Leads;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Caps\CapService;
use App\Jobs\DispatchLeadAutomationJob;
use App\Jobs\EvaluateEventAlertsJob;
use App\Services\Distribution\DistributionEngine;
use App\Services\Logging\PlatformLogger;
use Throwable;

class LeadPipeline
{
    public function __construct(
        protected LeadValidator $validator,
        protected DedupeService $dedupe,
        protected CapService $capService,
        protected DistributionEngine $distribution,
    ) {}

    public function process(Lead $lead): Lead
    {
        $campaign = $lead->campaign;
        $startedAt = microtime(true);

        try {
            PlatformLogger::leadEvent($lead, 'pipeline.started', 'Lead processing started');

            if (! $campaign->isActive()) {
                return $this->reject($lead, 'Campaign inactive');
            }

            $account = $campaign->account;
            if ($account && ! app(\App\Services\Billing\AccountBillingService::class)->canProcessLeads($account)) {
                return $this->reject($lead, 'Account billing locked or past due');
            }

            if (! $this->capService->hasCapacity('campaign', $campaign->id, $campaign->caps)) {
                return $this->reject($lead, 'Campaign cap reached');
            }

            if ($lead->source_id) {
                $source = $lead->source;
                if ($source && ! $this->capService->hasCapacity('source', $source->id, $source->caps)) {
                    return $this->reject($lead, 'Supplier source cap reached');
                }
            }

            if ($suppression = $this->dedupe->checkSuppression($lead)) {
                return $this->reject($lead, $suppression);
            }

            if ($campaign->isSuppression()) {
                $this->dedupe->index($lead, $campaign);
                $lead->update(['status' => LeadStatus::Accepted]);

                return $lead;
            }

            $lead->update(['status' => LeadStatus::Validating]);

            if ($error = $this->validator->validate($lead, $campaign)) {
                $validationService = app(\App\Services\Validation\ValidationService::class);
                if ($validationService->shouldQuarantineOnValidationFail($campaign)) {
                    $lead->metadata = array_merge($lead->metadata ?? [], [
                        'field_validation' => ['passed' => false, 'error' => $error],
                    ]);
                    app(\App\Services\Leads\QuarantineService::class)
                        ->quarantineForValidation($lead, $campaign, $error);

                    return $lead->fresh();
                }

                return $this->reject($lead, $error);
            }

            $validationService = app(\App\Services\Validation\ValidationService::class);
            if ($validationError = $validationService->validateLead($lead, $campaign)) {
                if ($validationService->shouldQuarantineOnValidationFail($campaign)) {
                    app(\App\Services\Leads\QuarantineService::class)
                        ->quarantineForValidation($lead, $campaign, $validationError);

                    return $lead->fresh();
                }

                return $this->reject($lead, $validationError);
            }

            $lead->save();

            if (app(\App\Services\Leads\QuarantineService::class)->applyRules($lead, $campaign)) {
                return $lead->fresh();
            }

            if ($dup = $this->dedupe->isDuplicate($lead, $campaign)) {
                return $this->reject($lead, $dup);
            }

            $lead->update(['status' => LeadStatus::Accepted]);
            $lead->metadata = array_merge($lead->metadata ?? [], [
                'quality_score' => \App\Services\Buyers\BuyerEligibilityService::computeQualityScore($lead),
            ]);
            $lead->save();
            $this->dedupe->index($lead, $campaign);

            app(\App\Services\Postbacks\PostbackDispatcher::class)->dispatch($lead->fresh(), 'lead.accepted');

            if ($lead->quarantined_until && $lead->quarantined_until->isFuture()) {
                $lead->update(['status' => LeadStatus::Quarantined]);
                PlatformLogger::leadEvent($lead, 'lead.quarantined', 'Lead quarantined until '.$lead->quarantined_until);
                $this->recordDuration($lead, $startedAt);

                return $lead;
            }

            $this->distribution->distribute($lead);

            DispatchLeadAutomationJob::dispatch($lead->id);
            EvaluateEventAlertsJob::dispatch($lead->account_id);

            $durationMs = $this->recordDuration($lead, $startedAt);
            PlatformLogger::leadEvent($lead, 'pipeline.completed', "Processed in {$durationMs}ms", [
                'duration_ms' => $durationMs,
            ]);

            return $lead->fresh(['financials', 'deliveryLogs', 'soldToBuyer']);
        } catch (Throwable $e) {
            PlatformLogger::error('Lead pipeline failed', ['lead_id' => $lead->id], $lead, $e);
            $lead->update(['status' => LeadStatus::Rejected, 'reject_reason' => 'Internal processing error']);

            throw $e;
        }
    }

    protected function reject(Lead $lead, string $reason): Lead
    {
        $status = str_starts_with($reason, 'Duplicate')
            ? LeadStatus::Duplicate
            : LeadStatus::Rejected;

        $lead->update([
            'status' => $status,
            'reject_reason' => $reason,
            'metadata' => array_merge($lead->metadata ?? [], [
                'rejection' => [
                    'reason' => $reason,
                    'rejected_at' => now()->toIso8601String(),
                ],
            ]),
        ]);
        PlatformLogger::leadEvent($lead, 'lead.rejected', $reason, [], 'warning');

        app(\App\Services\Postbacks\PostbackDispatcher::class)->dispatch($lead->fresh(), 'lead.rejected');

        return $lead;
    }

    protected function recordDuration(Lead $lead, float $startedAt): int
    {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $lead->update(['processing_ms' => $durationMs]);

        return $durationMs;
    }
}
