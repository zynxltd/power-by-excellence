<?php

namespace App\Models;

use App\Enums\CallEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallEvent extends Model
{
    protected $fillable = [
        'call_session_id',
        'event_type',
        'level',
        'message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => CallEventType::class,
            'payload' => 'array',
        ];
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }
}
