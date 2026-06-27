<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingNumber extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'campaign_id',
        'phone_number',
        'friendly_name',
        'provider',
        'provider_sid',
        'dni_pool',
        'dni_rules',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'dni_rules' => 'array',
            'metadata' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function callSessions(): HasMany
    {
        return $this->hasMany(CallSession::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
