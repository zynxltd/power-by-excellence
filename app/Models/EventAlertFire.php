<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAlertFire extends Model
{
    protected $fillable = [
        'event_alert_id',
        'account_id',
        'metric',
        'value',
        'threshold',
        'channel',
        'status',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'threshold' => 'decimal:2',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(EventAlert::class, 'event_alert_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
