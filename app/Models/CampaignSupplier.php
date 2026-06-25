<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignSupplier extends Model
{
    protected $fillable = [
        'campaign_id',
        'supplier_id',
        'caps',
        'payout_amount',
    ];

    protected function casts(): array
    {
        return [
            'caps' => 'array',
            'payout_amount' => 'decimal:2',
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
}
