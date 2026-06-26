<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessLeadJob;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

class QuarantineController extends Controller
{
    public function index(): JsonResponse
    {
        $leads = Lead::where('status', LeadStatus::Quarantined)
            ->orderByDesc('received_at')
            ->paginate(25);

        return response()->json($leads);
    }

    public function release(string $uuid): JsonResponse
    {
        $lead = Lead::where('uuid', $uuid)->where('status', LeadStatus::Quarantined)->firstOrFail();

        if ($this->isValidationHold($lead)) {
            return response()->json([
                'error' => 'Validation holds must be rejected — they cannot be released back into distribution.',
            ], 422);
        }

        $lead->update(['status' => LeadStatus::Accepted, 'quarantined_until' => null]);
        ProcessLeadJob::dispatch($lead->id);

        return response()->json(['status' => 'queued', 'queue_id' => $lead->queue_id]);
    }

    public function reject(string $uuid): JsonResponse
    {
        $lead = Lead::where('uuid', $uuid)->where('status', LeadStatus::Quarantined)->firstOrFail();
        $lead->update([
            'status' => LeadStatus::Rejected,
            'reject_reason' => 'Quarantine rejected',
            'quarantined_until' => null,
        ]);

        return response()->json(['status' => 'rejected']);
    }

    protected function isValidationHold(Lead $lead): bool
    {
        $meta = $lead->metadata ?? [];
        $reason = $meta['quarantine_reason'] ?? null;

        return $reason === 'validation'
            || ! empty($meta['email_validation'])
            || ! empty($meta['hlr_validation'])
            || ! empty($meta['field_validation']);
    }
}
