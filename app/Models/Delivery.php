<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'campaign_id',
        'buyer_id',
        'name',
        'method',
        'trigger_type',
        'status',
        'advanced_distribution_only',
        'priority',
        'weight',
        'tier',
        'routing_mode',
        'revenue_type',
        'revenue_amount',
        'revenue_rules',
        'cap_type',
        'caps',
        'config',
        'eligibility_rules',
        'schedule',
        'location_filter',
        'on_success_delivery_id',
        'on_failure_delivery_id',
    ];

    protected function casts(): array
    {
        return [
            'method' => DeliveryMethod::class,
            'advanced_distribution_only' => 'boolean',
            'revenue_amount' => 'decimal:2',
            'revenue_rules' => 'array',
            'caps' => 'array',
            'config' => 'array',
            'eligibility_rules' => 'array',
            'schedule' => 'array',
            'location_filter' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function scopeForTenant(Builder $query): Builder
    {
        return $query->whereHas('campaign');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeliveryLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
