<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Lead extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'uuid',
        'queue_id',
        'account_id',
        'campaign_id',
        'supplier_id',
        'source_id',
        'sub_supplier_id',
        'tracking_click_id',
        'sold_to_buyer_id',
        'winning_delivery_id',
        'redirect_url',
        'redirect_offered_at',
        'redirect_followed_at',
        'status',
        'reject_reason',
        'field_data',
        'metadata',
        'sid',
        'ssid',
        'source',
        'ip_address',
        'user_agent',
        'received_at',
        'distributed_at',
        'processing_ms',
        'quarantined_until',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'field_data' => 'array',
            'metadata' => 'array',
            'received_at' => 'datetime',
            'distributed_at' => 'datetime',
            'redirect_offered_at' => 'datetime',
            'redirect_followed_at' => 'datetime',
            'quarantined_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lead $lead): void {
            $lead->uuid ??= (string) Str::uuid();
            $lead->queue_id ??= 'q_'.Str::random(16);
            $lead->received_at ??= now();
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function subSupplier(): BelongsTo
    {
        return $this->belongsTo(SubSupplier::class, 'sub_supplier_id');
    }

    public function trackingClick(): BelongsTo
    {
        return $this->belongsTo(TrackingClick::class);
    }

    public function soldToBuyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class, 'sold_to_buyer_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class);
    }

    public function financials(): HasOne
    {
        return $this->hasOne(LeadFinancial::class);
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(DeliveryLog::class);
    }

    public function buyerFeedback(): HasMany
    {
        return $this->hasMany(BuyerFeedback::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(LeadTag::class);
    }

    public function getField(string $key, mixed $default = null): mixed
    {
        return data_get($this->field_data, $key, $default);
    }

    public function allFields(): array
    {
        return array_merge($this->field_data ?? [], [
            'sid' => $this->sid,
            'ssid' => $this->ssid,
            'source' => $this->source,
            'ip_address' => $this->ip_address,
            'received_at' => $this->received_at?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function consentArtifact(): ?array
    {
        $artifact = $this->metadata['consent'] ?? null;

        return is_array($artifact) ? $artifact : null;
    }
}
