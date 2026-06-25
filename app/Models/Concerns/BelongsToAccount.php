<?php

namespace App\Models\Concerns;

use App\Models\Account;
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
