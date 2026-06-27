<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallDeliveryLog extends Model
{
    protected $fillable = [
        'call_session_id',
        'delivery_id',
        'buyer_id',
        'status',
        'skipped_reason',
        'revenue',
        'duration_ms',
        'ping_request',
        'ping_response',
        'transfer_response',
        'tier',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'ping_request' => 'array',
            'ping_response' => 'array',
            'transfer_response' => 'array',
        ];
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }
}
