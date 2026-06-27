<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingConversion extends Model
{
    use BelongsToAccount;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'account_id',
        'tracking_link_id',
        'tracking_click_id',
        'lead_id',
        'campaign_id',
        'supplier_id',
        'buyer_id',
        'conversion_uuid',
        'goal',
        'status',
        'payout',
        'revenue',
        'sale_amount',
        'external_id',
        'approved_at',
        'rejected_at',
        'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'payout' => 'decimal:2',
            'revenue' => 'decimal:2',
            'sale_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class);
    }

    public function trackingClick(): BelongsTo
    {
        return $this->belongsTo(TrackingClick::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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
}
