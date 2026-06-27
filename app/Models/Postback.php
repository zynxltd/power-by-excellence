<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Services\Postbacks\SupplierPostbackService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Postback extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'supplier_id',
        'campaign_id',
        'name',
        'url',
        'method',
        'events',
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
            'config' => 'array',
            'is_active' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PostbackLog::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isSupplierOwned(): bool
    {
        return $this->supplier_id !== null && $this->approval_status !== null;
    }

    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->approval_status === null) {
            return true;
        }

        return $this->approval_status === SupplierPostbackService::STATUS_APPROVED;
    }

    public function scopeLive($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                    ->orWhere('approval_status', SupplierPostbackService::STATUS_APPROVED);
            });
    }
}
