<?php

namespace App\Models\Concerns;

use App\Models\Account;
use App\Models\User;
use App\Support\Tenancy\AccountContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToAccount
{
    public static function bootBelongsToAccount(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->account_id && $accountId = AccountContext::id()) {
                $model->account_id = $accountId;
            }
        });

        static::addGlobalScope('account', function (Builder $builder): void {
            if ($accountId = AccountContext::id()) {
                $builder->where($builder->getModel()->getTable().'.account_id', $accountId);
            }
        });
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();

        $query = static::query()->where($field, $value);

        if ($accountId = static::resolveRouteBindingAccountId()) {
            $query->where($this->getTable().'.account_id', $accountId);
        }

        return $query->firstOrFail();
    }

    protected static function resolveRouteBindingAccountId(): ?int
    {
        if ($accountId = AccountContext::id()) {
            return $accountId;
        }

        $hostAccount = request()->attributes->get('host_account');
        if ($hostAccount instanceof Account) {
            return $hostAccount->id;
        }

        $requestAccount = request()->attributes->get('account');
        if ($requestAccount instanceof Account) {
            return $requestAccount->id;
        }

        /** @var User|null $user */
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
