<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionConfig extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'config',
        'is_active',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** Scope to configs whose campaign belongs to the active tenant. */
    public function scopeForTenant(Builder $query): Builder
    {
        return $query->whereHas('campaign');
    }
}
