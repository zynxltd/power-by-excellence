<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class SendingProfile extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'name', 'provider', 'domain_match', 'sending_domain', 'from_name',
        'from_email', 'reply_to', 'config', 'is_default',
        'warmup_enabled', 'warmup_started_at', 'warmup_day_one_limit',
        'warmup_target_limit', 'warmup_ramp_days',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_default' => 'boolean',
            'warmup_enabled' => 'boolean',
            'warmup_started_at' => 'datetime',
            'warmup_day_one_limit' => 'integer',
            'warmup_target_limit' => 'integer',
            'warmup_ramp_days' => 'integer',
        ];
    }
}
