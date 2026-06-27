<?php

namespace App\Models;

use App\Enums\CallStatus;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CallSession extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'uuid',
        'account_id',
        'campaign_id',
        'tracking_number_id',
        'ivr_flow_id',
        'sold_to_buyer_id',
        'winning_delivery_id',
        'lead_id',
        'status',
        'caller_number',
        'caller_city',
        'caller_state',
        'caller_country',
        'sid',
        'ssid',
        'provider_call_sid',
        'revenue',
        'duration_seconds',
        'billable_seconds',
        'min_duration_seconds',
        'ivr_data',
        'metadata',
        'disposition',
        'answered_at',
        'transferred_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CallStatus::class,
            'revenue' => 'decimal:2',
            'ivr_data' => 'array',
            'metadata' => 'array',
            'answered_at' => 'datetime',
            'transferred_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CallSession $session): void {
            if (! $session->uuid) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function trackingNumber(): BelongsTo
    {
        return $this->belongsTo(TrackingNumber::class);
    }

    public function ivrFlow(): BelongsTo
    {
        return $this->belongsTo(IvrFlow::class);
    }

    public function soldToBuyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class, 'sold_to_buyer_id');
    }

    public function winningDelivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'winning_delivery_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function events(): HasMany
    {
        return $this->hasMany(CallEvent::class)->orderBy('created_at');
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(CallDeliveryLog::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(CallRecording::class);
    }

    public function callAttributes(): array
    {
        return array_filter([
            'call_uuid' => $this->uuid,
            'caller_number' => $this->caller_number,
            'caller_city' => $this->caller_city,
            'caller_state' => $this->caller_state,
            'caller_country' => $this->caller_country,
            'sid' => $this->sid,
            'ssid' => $this->ssid,
            'campaign_id' => $this->campaign_id,
            'campaign_reference' => $this->campaign?->reference,
            ...($this->ivr_data ?? []),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
