<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Supplier;
use App\Support\Tenancy\AccountContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['account_id', 'buyer_id', 'supplier_id', 'name', 'email', 'avatar_path', 'password', 'role', 'allowed_modules', 'is_suspended', 'suspended_at', 'theme', 'accent_color', 'two_factor_enabled', 'two_factor_secret', 'two_factor_recovery_codes', 'allowed_ips'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'allowed_modules' => 'array',
            'is_suspended' => 'boolean',
            'suspended_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'allowed_ips' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class)->withoutGlobalScopes();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withoutGlobalScopes();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isBuyerPortal(): bool
    {
        return $this->role === UserRole::BuyerPortal;
    }

    public function isSupplierPortal(): bool
    {
        return $this->role === UserRole::SupplierPortal;
    }

    public function hasModuleAccess(string $module): bool
    {
        if ($this->isSuperAdmin() || $this->role === UserRole::AccountAdmin) {
            return true;
        }

        if ($this->role !== UserRole::Staff) {
            return false;
        }

        $allowed = $this->allowed_modules ?? \App\Support\AdminModules::defaultsForStaff();

        return in_array('*', $allowed, true) || in_array($module, $allowed, true);
    }

    /**
     * @return list<string>
     */
    public function resolvedModules(): array
    {
        if ($this->isSuperAdmin() || $this->role === UserRole::AccountAdmin) {
            return \App\Support\AdminModules::keys();
        }

        if ($this->role === UserRole::Staff) {
            return $this->allowed_modules ?? \App\Support\AdminModules::defaultsForStaff();
        }

        return [];
    }

    public function isSuspended(): bool
    {
        return (bool) $this->is_suspended;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();

        $query = static::query()->where($field, $value);

        if ($accountId = static::resolveRouteBindingAccountId()) {
            $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)
                    ->orWhereHas('buyer', fn ($b) => $b->where('account_id', $accountId))
                    ->orWhereHas('supplier', fn ($s) => $s->where('account_id', $accountId));
            });
        }

        return $query->firstOrFail();
    }

    protected static function resolveRouteBindingAccountId(): ?int
    {
        if ($accountId = AccountContext::id()) {
            return $accountId;
        }

        /** @var self|null $user */
        $user = auth()->user();

        if ($user && ! $user->isSuperAdmin()) {
            return $user->resolveAccount()?->id;
        }

        if ($user?->isSuperAdmin()) {
            $hostAccount = request()->attributes->get('host_account');
            if ($hostAccount instanceof Account) {
                return $hostAccount->id;
            }

            if (session()->has('current_account_id')) {
                return (int) session('current_account_id');
            }
        }

        return null;
    }

    public function resolveAccount(): ?Account
    {
        if ($this->account_id) {
            return Account::find($this->account_id);
        }

        if ($this->buyer_id) {
            return Buyer::withoutGlobalScopes()->find($this->buyer_id)?->account;
        }

        if ($this->supplier_id) {
            return Supplier::withoutGlobalScopes()->find($this->supplier_id)?->account;
        }

        return null;
    }

    public function belongsToAccount(Account $account): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->account_id === $account->id) {
            return true;
        }

        if ($this->buyer_id) {
            return Buyer::withoutGlobalScopes()
                ->where('id', $this->buyer_id)
                ->where('account_id', $account->id)
                ->exists();
        }

        if ($this->supplier_id) {
            return Supplier::withoutGlobalScopes()
                ->where('id', $this->supplier_id)
                ->where('account_id', $account->id)
                ->exists();
        }

        return false;
    }

    public function canBeImpersonatedBy(User $actor): bool
    {
        if ($actor->id === $this->id) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return ! $this->isSuperAdmin();
        }

        if (in_array($actor->role, [UserRole::AccountAdmin, UserRole::Staff], true)) {
            $actorAccount = $actor->resolveAccount();
            $targetAccount = $this->resolveAccount();

            return $actorAccount && $targetAccount && $actorAccount->id === $targetAccount->id
                && ! $this->isSuperAdmin();
        }

        return false;
    }
}
