<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MessageSend extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'lead_id', 'bulk_sms_campaign_id', 'token', 'channel', 'provider',
        'source_type', 'source_id', 'recipient', 'subject', 'body', 'ab_variant', 'status', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MessageSend $send): void {
            if (! $send->token) {
                $send->token = (string) Str::uuid();
            }
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function bulkCampaign(): BelongsTo
    {
        return $this->belongsTo(BulkSmsCampaign::class, 'bulk_sms_campaign_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MessageEvent::class);
    }
}
