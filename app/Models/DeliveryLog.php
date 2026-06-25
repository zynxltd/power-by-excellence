<?php

namespace App\Models;

use App\Support\Tenancy\AccountContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryLog extends Model
{
    protected $fillable = [
        'lead_id',
        'delivery_id',
        'buyer_id',
        'status',
        'skipped_reason',
        'revenue',
        'duration_ms',
        'ping_request',
        'ping_response',
        'post_request',
        'post_response',
        'http_status',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'ping_request' => 'array',
            'ping_response' => 'array',
            'post_request' => 'array',
            'post_response' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function scopeForCurrentAccount(Builder $query): Builder
    {
        if ($accountId = AccountContext::id()) {
            $query->whereHas('delivery', function (Builder $q) use ($accountId) {
                $q->whereIn('campaign_id', function ($sub) use ($accountId) {
                    $sub->select('id')
                        ->from('campaigns')
                        ->where('account_id', $accountId);
                });
            });
        }

        return $query;
    }
}
