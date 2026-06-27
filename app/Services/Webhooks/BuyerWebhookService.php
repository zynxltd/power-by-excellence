<?php

namespace App\Services\Webhooks;

use App\Models\Buyer;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Integrations\BuyerWebhookSync;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BuyerWebhookService
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
            'lead.accepted',
            'lead.sold',
            'lead.rejected',
            'lead.unsold',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requestsForBuyer(Buyer $buyer): array
    {
        return Webhook::withoutGlobalScopes()
            ->where('buyer_id', $buyer->id)
            ->whereNotNull('approval_status')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Webhook $webhook) => $this->formatRequest($webhook))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function liveForBuyer(Buyer $buyer): array
    {
        return Webhook::withoutGlobalScopes()
            ->where('account_id', $buyer->account_id)
            ->live()
            ->where(function ($query) use ($buyer) {
                $query->where('buyer_id', $buyer->id)
                    ->orWhereNull('buyer_id');
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Webhook $webhook) => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'events' => $webhook->events ?? [],
                'scoped_to_you' => $webhook->buyer_id === $buyer->id,
                'managed_by_admin' => $webhook->buyer_id === $buyer->id
                    ? $webhook->approval_status === null
                    : true,
                'url_host' => parse_url($webhook->url, PHP_URL_HOST) ?: $webhook->url,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatRequest(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'events' => $webhook->events ?? [],
            'approval_status' => $webhook->approval_status,
            'is_live' => $webhook->isLive(),
            'submitted_at' => $webhook->submitted_at?->toDateTimeString(),
            'reviewed_at' => $webhook->reviewed_at?->toDateTimeString(),
            'submission_notes' => $webhook->submission_notes,
            'rejection_reason' => $webhook->rejection_reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Buyer $buyer, array $data): Webhook
    {
        return Webhook::create([
            'account_id' => $buyer->account_id,
            'buyer_id' => $buyer->id,
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => $data['events'],
            'is_active' => false,
            'approval_status' => self::STATUS_DRAFT,
            'config' => ['created_by' => 'buyer_portal'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Buyer $buyer, Webhook $webhook, array $data): Webhook
    {
        $this->assertOwnedByBuyer($buyer, $webhook);
        $this->assertEditableByBuyer($webhook);

        $webhook->update([
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => $data['events'],
            'is_active' => false,
            'approval_status' => self::STATUS_DRAFT,
            'submitted_at' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'rejection_reason' => null,
        ]);

        return $webhook->fresh();
    }

    public function submitForApproval(Buyer $buyer, Webhook $webhook, ?User $actor, ?string $notes = null): Webhook
    {
        $this->assertOwnedByBuyer($buyer, $webhook);
        $this->assertEditableByBuyer($webhook);

        $webhook->update([
            'approval_status' => self::STATUS_PENDING,
            'submitted_at' => now(),
            'submission_notes' => $notes ?: $webhook->submission_notes,
            'rejection_reason' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
        ]);

        $webhook->loadMissing(['account', 'buyer']);

        app(PlatformNotificationService::class)->notifyTenantWebhookApprovalRequest(
            $webhook->account,
            $actor,
            $webhook,
        );

        return $webhook->fresh();
    }

    public function requestDeletion(Buyer $buyer, Webhook $webhook, ?User $actor, ?string $notes = null): Webhook
    {
        $this->assertOwnedByBuyer($buyer, $webhook);

        if ($webhook->approval_status !== self::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'webhook' => 'Only approved webhooks can be submitted for deletion review.',
            ]);
        }

        $webhook->update([
            'approval_status' => self::STATUS_PENDING_DELETION,
            'submitted_at' => now(),
            'submission_notes' => $notes ?: $webhook->submission_notes,
            'rejection_reason' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
        ]);

        $webhook->loadMissing(['account', 'buyer']);

        app(PlatformNotificationService::class)->notifyTenantWebhookDeletionRequest(
            $webhook->account,
            $actor,
            $webhook,
        );

        return $webhook->fresh();
    }

    public function deleteDraft(Buyer $buyer, Webhook $webhook): void
    {
        $this->assertOwnedByBuyer($buyer, $webhook);

        if (! in_array($webhook->approval_status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'webhook' => 'Only draft or rejected webhooks can be deleted directly.',
            ]);
        }

        $webhook->delete();
    }

    public function approve(Webhook $webhook, User $reviewer): Webhook
    {
        abort_unless($webhook->approval_status === self::STATUS_PENDING, 422, 'Only pending webhooks can be approved.');

        $webhook->update([
            'approval_status' => self::STATUS_APPROVED,
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->id,
            'rejection_reason' => null,
        ]);

        return $webhook->fresh();
    }

    public function reject(Webhook $webhook, User $reviewer, string $reason): Webhook
    {
        abort_unless($webhook->approval_status === self::STATUS_PENDING, 422, 'Only pending webhooks can be rejected.');

        $webhook->update([
            'approval_status' => self::STATUS_REJECTED,
            'is_active' => false,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->id,
            'rejection_reason' => $reason,
        ]);

        return $webhook->fresh();
    }

    public function approveDeletion(Webhook $webhook, User $reviewer): void
    {
        abort_unless($webhook->approval_status === self::STATUS_PENDING_DELETION, 422, 'Only webhooks pending deletion can be removed.');

        if (($webhook->config['synced_from'] ?? null) === BuyerWebhookSync::SYNC_KEY) {
            abort(422, 'Synced buyer webhooks must be changed from the buyer admin form.');
        }

        $webhook->delete();
    }

    public function rejectDeletion(Webhook $webhook, User $reviewer, ?string $reason = null): Webhook
    {
        abort_unless($webhook->approval_status === self::STATUS_PENDING_DELETION, 422, 'Only webhooks pending deletion can be restored.');

        $webhook->update([
            'approval_status' => self::STATUS_APPROVED,
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->id,
            'rejection_reason' => $reason,
        ]);

        return $webhook->fresh();
    }

    /**
     * @return array{pending: int, pending_deletion: int, draft: int, rejected: int}
     */
    public function adminStats(): array
    {
        $query = Webhook::query()->whereNotNull('approval_status');

        return [
            'pending' => (clone $query)->where('approval_status', self::STATUS_PENDING)->count(),
            'pending_deletion' => (clone $query)->where('approval_status', self::STATUS_PENDING_DELETION)->count(),
            'draft' => (clone $query)->where('approval_status', self::STATUS_DRAFT)->count(),
            'rejected' => (clone $query)->where('approval_status', self::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * @return Collection<int, Webhook>
     */
    public function pendingForAdmin(): Collection
    {
        return Webhook::query()
            ->whereIn('approval_status', [self::STATUS_PENDING, self::STATUS_PENDING_DELETION])
            ->with(['buyer:id,name,reference'])
            ->orderBy('submitted_at')
            ->get();
    }

    protected function assertOwnedByBuyer(Buyer $buyer, Webhook $webhook): void
    {
        if ($webhook->buyer_id !== $buyer->id || $webhook->approval_status === null) {
            abort(403, 'This webhook does not belong to your buyer account.');
        }
    }

    protected function assertEditableByBuyer(Webhook $webhook): void
    {
        if (! in_array($webhook->approval_status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'webhook' => 'This webhook cannot be edited while it is pending review or already approved.',
            ]);
        }
    }
}
