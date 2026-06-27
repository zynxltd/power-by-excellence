<?php

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\Buyer;

class AccountBillingService
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_LOCKED = 'locked';

    public function resolveStatus(Account $account): string
    {
        if (! $account->is_active) {
            return self::STATUS_LOCKED;
        }

        $settings = $account->settings ?? [];
        $explicit = $settings['billing_status'] ?? self::STATUS_ACTIVE;

        if ($explicit === self::STATUS_LOCKED) {
            return self::STATUS_LOCKED;
        }

        if ($explicit === self::STATUS_PAST_DUE) {
            return self::STATUS_PAST_DUE;
        }

        return self::STATUS_ACTIVE;
    }

    public function isOperational(Account $account): bool
    {
        return $this->resolveStatus($account) === self::STATUS_ACTIVE;
    }

    public function canAcceptLeads(Account $account): bool
    {
        return $this->resolveStatus($account) !== self::STATUS_LOCKED;
    }

    public function canProcessLeads(Account $account): bool
    {
        return $this->resolveStatus($account) !== self::STATUS_LOCKED;
    }

    public function lock(Account $account, ?string $reason = null): void
    {
        $settings = $account->settings ?? [];
        $settings['billing_status'] = self::STATUS_LOCKED;
        $settings['billing_locked_at'] = now()->toIso8601String();
        if ($reason) {
            $settings['billing_lock_reason'] = $reason;
        }

        $account->update(['settings' => $settings, 'is_active' => false]);
    }

    public function unlock(Account $account): void
    {
        $settings = $account->settings ?? [];
        $settings['billing_status'] = self::STATUS_ACTIVE;
        unset($settings['billing_locked_at'], $settings['billing_lock_reason']);

        $account->update(['settings' => $settings, 'is_active' => true]);
    }

    public function isBuyerOperational(Buyer $buyer): bool
    {
        if ($buyer->status !== 'active') {
            return false;
        }

        $account = $buyer->account;
        if (! $account || ! $this->canProcessLeads($account)) {
            return false;
        }

        $requirePrepay = $account->settings['require_buyer_prepay'] ?? false;
        if ($requirePrepay && (float) $buyer->credit_balance <= 0) {
            return false;
        }

        return true;
    }

    public function summary(Account $account): array
    {
        $settings = $account->settings ?? [];
        $status = $this->resolveStatus($account);

        return [
            'status' => $status,
            'due_at' => $settings['billing_due_at'] ?? null,
            'locked_at' => $settings['billing_locked_at'] ?? null,
            'lock_reason' => $settings['billing_lock_reason'] ?? null,
            'require_buyer_prepay' => $settings['require_buyer_prepay'] ?? false,
            'is_operational' => $this->isOperational($account),
            'can_accept_leads' => $this->canAcceptLeads($account),
            'can_process_leads' => $this->canProcessLeads($account),
        ];
    }
}
