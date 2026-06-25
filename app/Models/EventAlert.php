<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class EventAlert extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'name', 'metric', 'operator', 'threshold', 'channel', 'status', 'config', 'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'threshold' => 'decimal:2',
            'last_triggered_at' => 'datetime',
        ];
    }
}
