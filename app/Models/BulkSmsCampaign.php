<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkSmsCampaign extends Model
{
    use BelongsToAccount;

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_BOTH = 'both';

    /**
     * @return array<int, string>
     */
    public static function channelOptions(): array
    {
        return [self::CHANNEL_SMS, self::CHANNEL_EMAIL, self::CHANNEL_BOTH];
    }

    public function usesEmail(): bool
    {
        return in_array($this->channel, [self::CHANNEL_EMAIL, self::CHANNEL_BOTH], true);
    }

    public function usesSms(): bool
    {
        return in_array($this->channel, [self::CHANNEL_SMS, self::CHANNEL_BOTH], true);
    }

    protected $fillable = [
        'account_id', 'campaign_id', 'name', 'channel', 'subject', 'provider', 'message',
        'html_body', 'filter', 'segment_id', 'sending_profile_id', 'ab_test', 'throttle_per_minute',
        'scheduled_at', 'status', 'sent_count', 'failed_count',
    ];

    protected function casts(): array
    {
        return [
            'filter' => 'array',
            'ab_test' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function sendingProfile(): BelongsTo
    {
        return $this->belongsTo(SendingProfile::class);
    }
}
