<?php

namespace App\Services\Postbacks;

use App\Models\Campaign;
use App\Models\Postback;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Integrations\SupplierPostbackSync;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SupplierPostbackService
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PENDING_DELETION = 'pending_deletion';

    /**
     * @return list<string>
     */
    public static function eventOptions(): array
    {
        return [
            'lead.sold',
            'lead.accepted',
            'lead.rejected',
            'lead.unsold',
            'lead.contacted',
            'lead.converted',
            'lead.funded',
            'lead.returned',
            'delivery.success',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requestsForSupplier(Supplier $supplier): array
    {
        return Postback::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->whereNotNull('approval_status')
            ->with('campaign:id,name,reference')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Postback $postback) => $this->formatRequest($postback))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatRequest(Postback $postback): array
    {
        return [
            'id' => $postback->id,
            'name' => $postback->name,
            'url' => $postback->url,
            'method' => $postback->method,
            'events' => $postback->events ?? [],
            'approval_status' => $postback->approval_status,
            'is_live' => $postback->isLive(),
            'submitted_at' => $postback->submitted_at?->toDateTimeString(),
            'reviewed_at' => $postback->reviewed_at?->toDateTimeString(),
            'submission_notes' => $postback->submission_notes,
            'rejection_reason' => $postback->rejection_reason,
            'campaign' => $postback->campaign?->only(['id', 'name', 'reference']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Supplier $supplier, array $data): Postback
    {
        $campaign = $this->resolveCampaignForSupplier($supplier, $data['campaign_id'] ?? null);

        return Postback::create([
            'account_id' => $supplier->account_id,
            'supplier_id' => $supplier->id,
            'campaign_id' => $campaign?->id,
            'name' => $data['name'],
            'url' => $data['url'],
            'method' => $data['method'] ?? 'get',
            'events' => $data['events'],
            'is_active' => false,
            'approval_status' => self::STATUS_DRAFT,
            'config' => ['created_by' => 'supplier_portal'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, Postback $postback, array $data): Postback
    {
        $this->assertOwnedBySupplier($supplier, $postback);
        $this->assertEditableBySupplier($postback);

        $campaign = $this->resolveCampaignForSupplier($supplier, $data['campaign_id'] ?? null);

        $postback->update([
            'campaign_id' => $campaign?->id,
            'name' => $data['name'],
            'url' => $data['url'],
            'method' => $data['method'] ?? 'get',
            'events' => $data['events'],
            'is_active' => false,
            'approval_status' => self::STATUS_DRAFT,
            'submitted_at' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'rejection_reason' => null,
        ]);

        return $postback->fresh();
    }

    public function submitForApproval(Supplier $supplier, Postback $postback, ?User $actor, ?string $notes = null): Postback
    {
        $this->assertOwnedBySupplier($supplier, $postback);
        $this->assertEditableBySupplier($postback);

        $postback->update([
            'approval_status' => self::STATUS_PENDING,
            'submitted_at' => now(),
            'submission_notes' => $notes ?: $postback->submission_notes,
            'rejection_reason' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
        ]);

        $postback->loadMissing(['campaign', 'account', 'supplier']);

        app(PlatformNotificationService::class)->notifyTenantPostbackApprovalRequest(
            $postback->account,
            $actor,
            $postback,
        );

        return $postback->fresh();
    }

    public function requestDeletion(Supplier $supplier, Postback $postback, ?User $actor, ?string $notes = null): Postback
    {
        $this->assertOwnedBySupplier($supplier, $postback);

        if ($postback->approval_status !== self::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'postback' => 'Only approved postbacks can be submitted for deletion review.',
            ]);
        }

        $postback->update([
            'approval_status' => self::STATUS_PENDING_DELETION,
            'submitted_at' => now(),
            'submission_notes' => $notes ?: $postback->submission_notes,
            'rejection_reason' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
        ]);

        $postback->loadMissing(['account', 'supplier']);

        app(PlatformNotificationService::class)->notifyTenantPostbackDeletionRequest(
            $postback->account,
            $actor,
            $postback,
        );

        return $postback->fresh();
    }

    public function deleteDraft(Supplier $supplier, Postback $postback): void
    {
        $this->assertOwnedBySupplier($supplier, $postback);

        if (! in_array($postback->approval_status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'postback' => 'Only draft or rejected postbacks can be deleted directly.',
            ]);
        }

        if (($postback->config['synced_from'] ?? null) === SupplierPostbackSync::SYNC_KEY) {
            throw ValidationException::withMessages([
                'postback' => 'This postback is managed by your platform administrator.',
            ]);
        }

        $postback->delete();
    }

    public function approve(Postback $postback, User $reviewer): Postback
    {
        abort_unless($postback->approval_status === self::STATUS_PENDING, 422, 'Only pending postbacks can be approved.');

        $postback->update([
            'approval_status' => self::STATUS_APPROVED,
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->id,
            'rejection_reason' => null,
        ]);

        return $postback->fresh();
    }

    public function reject(Postback $postback, User $reviewer, string $reason): Postback
    {
        abort_unless($postback->approval_status === self::STATUS_PENDING, 422, 'Only pending postbacks can be rejected.');

        $postback->update([
            'approval_status' => self::STATUS_REJECTED,
            'is_active' => false,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->id,
            'rejection_reason' => $reason,
        ]);

        return $postback->fresh();
    }

    public function approveDeletion(Postback $postback, User $reviewer): void
    {
        abort_unless($postback->approval_status === self::STATUS_PENDING_DELETION, 422, 'Only postbacks pending deletion can be removed.');

        if (($postback->config['synced_from'] ?? null) === SupplierPostbackSync::SYNC_KEY) {
            abort(422, 'Synced default postbacks must be changed from the supplier admin form.');
        }

        $postback->delete();
    }

    public function rejectDeletion(Postback $postback, User $reviewer, ?string $reason = null): Postback
    {
        abort_unless($postback->approval_status === self::STATUS_PENDING_DELETION, 422, 'Only postbacks pending deletion can be restored.');

        $postback->update([
            'approval_status' => self::STATUS_APPROVED,
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->id,
            'rejection_reason' => $reason,
        ]);

        return $postback->fresh();
    }

    /**
     * @return array{pending: int, pending_deletion: int, draft: int, rejected: int}
     */
    public function adminStats(): array
    {
        $query = Postback::query()->whereNotNull('approval_status');

        return [
            'pending' => (clone $query)->where('approval_status', self::STATUS_PENDING)->count(),
            'pending_deletion' => (clone $query)->where('approval_status', self::STATUS_PENDING_DELETION)->count(),
            'draft' => (clone $query)->where('approval_status', self::STATUS_DRAFT)->count(),
            'rejected' => (clone $query)->where('approval_status', self::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * @return Collection<int, Postback>
     */
    public function pendingForAdmin(): Collection
    {
        return Postback::query()
            ->whereIn('approval_status', [self::STATUS_PENDING, self::STATUS_PENDING_DELETION])
            ->with(['campaign:id,name,reference', 'supplier:id,name,reference'])
            ->orderBy('submitted_at')
            ->get();
    }

    protected function resolveCampaignForSupplier(Supplier $supplier, mixed $campaignId): ?Campaign
    {
        if ($campaignId === null || $campaignId === '') {
            return null;
        }

        $campaign = Campaign::query()
            ->where('account_id', $supplier->account_id)
            ->whereKey((int) $campaignId)
            ->whereHas('campaignSuppliers', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->first();

        if (! $campaign) {
            throw ValidationException::withMessages([
                'campaign_id' => 'You are not linked to this campaign.',
            ]);
        }

        return $campaign;
    }

    protected function assertOwnedBySupplier(Supplier $supplier, Postback $postback): void
    {
        if ($postback->supplier_id !== $supplier->id) {
            abort(403, 'This postback does not belong to your supplier account.');
        }
    }

    protected function assertEditableBySupplier(Postback $postback): void
    {
        if (! in_array($postback->approval_status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'postback' => 'This postback cannot be edited while it is pending review, approved, or awaiting deletion.',
            ]);
        }
    }
}
