<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Services\Webhooks\BuyerWebhookService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webhook extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'buyer_id',
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'approval_status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'submission_notes',
        'rejection_reason',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'config' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isBuyerOwned(): bool
    {
        return $this->buyer_id !== null && $this->approval_status !== null;
    }

    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->approval_status === null) {
            return true;
        }

        return $this->approval_status === BuyerWebhookService::STATUS_APPROVED;
    }

    public function scopeLive($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                    ->orWhere('approval_status', BuyerWebhookService::STATUS_APPROVED);
            });
    }
}
