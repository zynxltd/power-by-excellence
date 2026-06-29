<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRecording extends Model
{
    protected $fillable = [
        'call_session_id',
        'provider_recording_sid',
        'url',
        'storage_path',
        'duration_seconds',
        'retention_expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'retention_expires_at' => 'datetime',
        ];
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    /**
     * Seconds alias for API/UI consistency.
     */
    protected function duration(): Attribute
    {
        return Attribute::get(fn () => $this->duration_seconds);
    }

    public function hasPlayback(): bool
    {
        if ($this->retention_expires_at?->isPast()) {
            return false;
        }

        return $this->storage_path !== null || $this->url !== null;
    }
}
