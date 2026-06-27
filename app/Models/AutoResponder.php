<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoResponder extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'campaign_id',
        'name',
        'channel',
        'trigger_event',
        'delay_minutes',
        'status',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
