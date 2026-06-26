<?php

namespace App\Services\Platform;

use App\Models\Account;
use App\Models\PlatformNotification;
use App\Models\PlatformNotificationRead;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlatformNotificationService
{
    public const ALERT_HERD_TENANT_LINKING = 'herd_tenant_linking';

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
            'audience' => 'tenant',
            'type' => 'broadcast',
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'expires_at' => $expiresAt,
        ]);
    }

    public function notifySupportStaffReply(User $staff, SupportTicket $ticket, string $body): PlatformNotification
    {
        return PlatformNotification::create([
            'account_id' => $ticket->account_id,
            'created_by_user_id' => $staff->id,
            'audience' => 'tenant',
            'type' => 'support',
            'severity' => 'info',
            'title' => 'Support replied: '.$ticket->subject,
            'body' => Str::limit(trim($body), 200),
            'metadata' => [
                'action' => 'support.staff_reply',
                'support_ticket_id' => $ticket->id,
                'subject' => $ticket->subject,
            ],
        ]);
    }

    public function notifySupportTenantMessage(
        Account $account,
        User $tenantUser,
        SupportTicket $ticket,
        string $action,
        string $body,
    ): PlatformNotification {
        $title = $action === 'support.ticket_created'
            ? 'New support ticket: '.$ticket->subject
            : 'Support ticket reply: '.$ticket->subject;

        return $this->logTenantActivity(
            $account,
            $tenantUser,
            $action,
            $title,
            Str::limit(trim($body), 200),
            [
                'support_ticket_id' => $ticket->id,
                'subject' => $ticket->subject,
            ],
        );
    }

    public function hrefFor(User $user, PlatformNotification $notification): ?string
    {
        $ticketId = $notification->metadata['support_ticket_id'] ?? null;

        if (! $ticketId) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return route('support.admin.show', $ticketId);
        }

        return route('support.show', $ticketId);
    }

    /**
     * @param  array{linked: list<string>, missing: list<string>, commands: list<string>, shell_script: string, needs_linking: bool}  $herd
     */
    public function syncHerdLinkingAlert(array $herd): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('platform_notifications')) {
            return;
        }

        if (! ($herd['needs_linking'] ?? false)) {
            $this->clearSystemAlert(self::ALERT_HERD_TENANT_LINKING);

            return;
        }

        $missing = $herd['missing'] ?? [];
        $count = count($missing);
        $isLocal = app()->environment('local');

        $title = $isLocal
            ? 'Laravel Herd — link tenant subdomains'
            : 'Tenant subdomains not resolving';

        $hostList = implode(', ', array_slice($missing, 0, 5));
        if ($count > 5) {
            $hostList .= ' …';
        }

        $body = $isLocal
            ? "{$count} subdomain(s) not resolving locally ({$hostList}). Link them in Herd or run php artisan platform:link-tenants."
            : "{$count} subdomain(s) failed DNS resolution ({$hostList}). Configure DNS for tenant portals.";

        $this->upsertSystemAlert(
            self::ALERT_HERD_TENANT_LINKING,
            $title,
            $body,
            'warning',
            [
                'missing_hosts' => $missing,
                'shell_script' => $herd['shell_script'] ?? '',
                'artisan_command' => 'php artisan platform:link-tenants',
            ],
        );
    }

    public function clearSystemAlert(string $alertKey): void
    {
        PlatformNotification::query()
            ->where('audience', 'super_admin')
            ->where('type', 'system')
            ->where('metadata->alert_key', $alertKey)
            ->delete();
    }

    protected function upsertSystemAlert(
        string $alertKey,
        string $title,
        ?string $body,
        string $severity = 'warning',
        array $metadata = [],
    ): PlatformNotification {
        $existing = PlatformNotification::query()
            ->where('audience', 'super_admin')
            ->where('type', 'system')
            ->where('metadata->alert_key', $alertKey)
            ->first();

        $payload = [
            'title' => $title,
            'body' => $body,
            'severity' => $severity,
            'metadata' => array_merge($metadata, ['alert_key' => $alertKey]),
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return PlatformNotification::create([
            ...$payload,
            'account_id' => null,
            'created_by_user_id' => null,
            'audience' => 'super_admin',
            'type' => 'system',
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
