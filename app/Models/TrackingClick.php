<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrackingClick extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'tracking_link_id',
        'campaign_id',
        'supplier_id',
        'click_uuid',
        'sub1',
        'sub2',
        'sub3',
        'sub4',
        'sub5',
        'source',
        'referrer',
        'ip_address',
        'user_agent',
        'country',
        'device',
        'is_unique',
        'lead_id',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_unique' => 'boolean',
            'clicked_at' => 'datetime',
        ];
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function conversion(): HasOne
    {
        return $this->hasOne(TrackingConversion::class);
    }
}
