<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkSmsCampaign extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'campaign_id', 'name', 'channel', 'subject', 'provider', 'message', 'filter', 'scheduled_at', 'status', 'sent_count', 'failed_count',
    ];

    protected function casts(): array
    {
        return [
            'filter' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
