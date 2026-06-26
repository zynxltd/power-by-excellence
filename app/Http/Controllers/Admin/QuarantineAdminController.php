<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Support\Queue\LeadJobDispatcher;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuarantineAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $reason = $request->string('reason')->toString();

        $query = Lead::with(['campaign:id,name,reference', 'supplier:id,name', 'account:id,name,brand_name'])
            ->where('status', LeadStatus::Quarantined);

        if ($reason === 'out_of_hours') {
            $query->where('metadata->quarantine_reason', 'out_of_hours');
        } elseif ($reason === 'validation') {
            $query->where(function ($q) {
                $q->where('metadata->quarantine_reason', 'validation')
                    ->orWhereNotNull('metadata->email_validation')
                    ->orWhereNotNull('metadata->hlr_validation')
                    ->orWhereNotNull('metadata->field_validation');
            });
        } elseif ($reason === 'unsold') {
            $query->where(function ($q) {
                $q->where('metadata->quarantine_reason', 'unsold')
                    ->orWhere('metadata->quarantine_message', 'like', '%unsold%');
            });
        } elseif ($reason === 'hold') {
            $query->where(function ($q) {
                $q->where('metadata->quarantine_reason', 'hold')
                    ->orWhereNull('metadata->quarantine_reason');
            })->where(function ($q) {
                $q->whereNull('metadata->email_validation')
                    ->whereNull('metadata->hlr_validation')
                    ->whereNull('metadata->field_validation');
            });
        } elseif ($reason === 'expiring') {
            $query->whereDate('quarantined_until', '<=', today());
        }

        $leads = $query
            ->orderBy('quarantined_until')
            ->orderByDesc('received_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                ...$lead->toArray(),
                'quarantine_reason' => $this->resolveReason($lead),
                'quarantine_message' => $lead->metadata['quarantine_message'] ?? null,
            ]);

        return Inertia::render('Admin/Quarantine/Index', [
            'leads' => $leads,
            'stats' => $this->stats(),
            'filters' => ['reason' => $reason ?: null],
            'policy' => [
                'default_hours' => (int) config('validation.quarantine_hours', 48),
                'expire_action' => config('validation.quarantine_expire_action', 'release'),
                'validation_rejects_on_expire' => true,
            ],
        ]);
    }

    public function release(Lead $lead): RedirectResponse
    {
        abort_unless($lead->status === LeadStatus::Quarantined, 422);

        $reason = $lead->metadata['quarantine_reason'] ?? null;
        if ($reason === 'validation'
            || ! empty($lead->metadata['email_validation'])
            || ! empty($lead->metadata['hlr_validation'])
            || ! empty($lead->metadata['field_validation'])) {
            abort(422, 'Validation holds must be rejected — they cannot be released back into distribution.');
        }

        $lead->update([
            'status' => LeadStatus::Accepted,
            'quarantined_until' => null,
        ]);

        LeadJobDispatcher::dispatch($lead->id);

        return back()->with('success', 'Lead released from quarantine and queued for distribution.');
    }

    public function reject(Lead $lead): RedirectResponse
    {
        abort_unless($lead->status === LeadStatus::Quarantined, 422);

        $lead->update([
            'status' => LeadStatus::Rejected,
            'reject_reason' => 'Quarantine rejected by admin',
            'quarantined_until' => null,
        ]);

        return back()->with('success', 'Quarantined lead rejected.');
    }

    public function extend(Lead $lead, Request $request): RedirectResponse
    {
        abort_unless($lead->status === LeadStatus::Quarantined, 422);

        $hours = (int) $request->validate(['hours' => 'nullable|integer|min:1|max:168'])['hours'] ?? 24;
        $until = ($lead->quarantined_until && $lead->quarantined_until->isFuture())
            ? $lead->quarantined_until->copy()->addHours($hours)
            : now()->addHours($hours);

        $lead->update(['quarantined_until' => $until]);

        return back()->with('success', "Hold extended by {$hours}h until {$until->toDateTimeString()}.");
    }

    public function bulkRelease(Request $request): RedirectResponse
    {
        $ids = $request->validate(['lead_ids' => 'required|array', 'lead_ids.*' => 'integer'])['lead_ids'];

        $leads = Lead::whereIn('id', $ids)->where('status', LeadStatus::Quarantined)->get();
        $released = 0;
        foreach ($leads as $lead) {
            $reason = $lead->metadata['quarantine_reason'] ?? null;
            if ($reason === 'validation'
                || ! empty($lead->metadata['email_validation'])
                || ! empty($lead->metadata['hlr_validation'])
                || ! empty($lead->metadata['field_validation'])) {
                continue;
            }
            $lead->update(['status' => LeadStatus::Accepted, 'quarantined_until' => null]);
            LeadJobDispatcher::dispatch($lead->id);
            $released++;
        }

        return back()->with('success', $released.' lead(s) released from quarantine.');
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $ids = $request->validate(['lead_ids' => 'required|array', 'lead_ids.*' => 'integer'])['lead_ids'];

        $count = Lead::whereIn('id', $ids)
            ->where('status', LeadStatus::Quarantined)
            ->update([
                'status' => LeadStatus::Rejected,
                'reject_reason' => 'Quarantine bulk rejected by admin',
                'quarantined_until' => null,
            ]);

        return back()->with('success', "{$count} lead(s) rejected from quarantine.");
    }

    protected function stats(): array
    {
        $base = Lead::where('status', LeadStatus::Quarantined);

        return [
            'total' => (clone $base)->count(),
            'expiring_today' => (clone $base)->whereDate('quarantined_until', '<=', today())->count(),
            'out_of_hours' => (clone $base)->where('metadata->quarantine_reason', 'out_of_hours')->count(),
            'validation' => (clone $base)->where(function ($q) {
                $q->where('metadata->quarantine_reason', 'validation')
                    ->orWhereNotNull('metadata->email_validation')
                    ->orWhereNotNull('metadata->hlr_validation')
                    ->orWhereNotNull('metadata->field_validation');
            })->count(),
            'unsold' => (clone $base)->where(function ($q) {
                $q->where('metadata->quarantine_reason', 'unsold')
                    ->orWhere('metadata->quarantine_message', 'like', '%unsold%');
            })->count(),
            'hold' => (clone $base)->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->where('metadata->quarantine_reason', 'hold')
                        ->orWhereNull('metadata->quarantine_reason');
                })->where(function ($inner) {
                    $inner->whereNull('metadata->email_validation')
                        ->whereNull('metadata->hlr_validation')
                        ->whereNull('metadata->field_validation');
                });
            })->where(function ($q) {
                $q->where('metadata->quarantine_reason', '!=', 'out_of_hours')
                    ->orWhereNull('metadata->quarantine_reason');
            })->count(),
        ];
    }

    protected function resolveReason(Lead $lead): string
    {
        $meta = $lead->metadata ?? [];

        if (($meta['quarantine_reason'] ?? null) === 'out_of_hours') {
            return 'out_of_hours';
        }
        if (($meta['quarantine_reason'] ?? null) === 'validation'
            || ! empty($meta['email_validation'])
            || ! empty($meta['hlr_validation'])
            || ! empty($meta['field_validation'])) {
            return 'validation';
        }
        if (($meta['quarantine_reason'] ?? null) === 'unsold'
            || str_contains(strtolower($meta['quarantine_message'] ?? ''), 'unsold')) {
            return 'unsold';
        }

        return 'hold';
    }
}
