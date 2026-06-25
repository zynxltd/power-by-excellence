<?php

namespace App\Services\Platform;

use App\Models\Account;
use App\Models\PlatformNotification;
use App\Models\PlatformNotificationRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PlatformNotificationService
{
    public function logTenantActivity(
        Account $account,
        ?User $actor,
        string $action,
        string $title,
        ?string $body = null,
        array $metadata = [],
    ): PlatformNotification {
        return PlatformNotification::create([
            'account_id' => $account->id,
            'created_by_user_id' => $actor?->id,
            'audience' => 'super_admin',
            'type' => 'activity',
            'severity' => 'info',
            'title' => $title,
            'body' => $body,
            'metadata' => array_merge($metadata, ['action' => $action]),
        ]);
    }

    public function broadcast(
        User $createdBy,
        string $title,
        ?string $body = null,
        ?int $accountId = null,
        string $severity = 'info',
        ?\DateTimeInterface $expiresAt = null,
    ): PlatformNotification {
        return PlatformNotification::create([
            'account_id' => $accountId,
            'created_by_user_id' => $createdBy->id,
            'audience' => $accountId ? 'tenant' : 'tenant',
            'type' => 'broadcast',
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'expires_at' => $expiresAt,
        ]);
    }

    public function unreadCount(User $user): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('platform_notifications')) {
            return 0;
        }

        return $this->visibleQuery($user)
            ->whereDoesntHave('reads', fn (Builder $q) => $q->where('user_id', $user->id))
            ->count();
    }

    /**
     * @return Collection<int, PlatformNotification>
     */
    public function recentForUser(User $user, int $limit = 8): Collection
    {
        return $this->visibleQuery($user)
            ->with(['account:id,name,brand_name', 'createdBy:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (PlatformNotification $n) use ($user) {
                $n->setAttribute('is_read', $n->reads()->where('user_id', $user->id)->exists());

                return $n;
            });
    }

    public function markRead(User $user, PlatformNotification $notification): void
    {
        abort_unless($this->canView($user, $notification), 403);

        PlatformNotificationRead::firstOrCreate(
            [
                'user_id' => $user->id,
                'platform_notification_id' => $notification->id,
            ],
            ['read_at' => now()]
        );
    }

    public function markAllRead(User $user): int
    {
        $count = 0;

        foreach ($this->visibleQuery($user)->whereDoesntHave('reads', fn (Builder $q) => $q->where('user_id', $user->id))->get() as $notification) {
            $this->markRead($user, $notification);
            $count++;
        }

        return $count;
    }

    protected function visibleQuery(User $user): Builder
    {
        $query = PlatformNotification::query()
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        if ($user->isSuperAdmin()) {
            return $query->where('audience', 'super_admin');
        }

        $accountId = $user->resolveAccount()?->id;

        return $query->where('audience', 'tenant')
            ->where(function (Builder $q) use ($accountId) {
                $q->whereNull('account_id');
                if ($accountId) {
                    $q->orWhere('account_id', $accountId);
                }
            });
    }

    protected function canView(User $user, PlatformNotification $notification): bool
    {
        return $this->visibleQuery($user)->where('id', $notification->id)->exists();
    }
}
