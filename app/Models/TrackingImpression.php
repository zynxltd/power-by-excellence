<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingImpression extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'tracking_link_id',
        'impression_uuid',
        'sub1',
        'sub2',
        'sub3',
        'sub4',
        'sub5',
        'source',
        'ip_address',
        'user_agent',
        'impressed_at',
    ];

    protected function casts(): array
    {
        return [
            'impressed_at' => 'datetime',
        ];
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class);
    }
}
