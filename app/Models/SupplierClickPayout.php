<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierClickPayout extends Model
{
    use BelongsToAccount;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'account_id',
        'supplier_id',
        'tracking_conversion_id',
        'amount',
        'revenue',
        'revenue_share_pct',
        'status',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'revenue' => 'decimal:4',
            'revenue_share_pct' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function trackingConversion(): BelongsTo
    {
        return $this->belongsTo(TrackingConversion::class);
    }
}
