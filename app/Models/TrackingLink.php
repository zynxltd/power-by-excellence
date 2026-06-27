<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingLink extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'campaign_id',
        'supplier_id',
        'buyer_id',
        'name',
        'token',
        'destination_url',
        'goal',
        'status',
        'payout_amount',
        'revenue_amount',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'payout_amount' => 'decimal:2',
            'revenue_amount' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(TrackingClick::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(TrackingConversion::class);
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(TrackingImpression::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function trackingUrl(): string
    {
        return url('/c/'.$this->token);
    }

    public function impressionUrl(): string
    {
        return url('/i/'.$this->token);
    }
}
