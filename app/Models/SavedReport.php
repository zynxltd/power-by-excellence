<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class SavedReport extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'name',
        'filters',
        'columns',
        'schedule_cron',
        'email_recipients',
        'last_run_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'columns' => 'array',
            'email_recipients' => 'array',
            'last_run_at' => 'datetime',
        ];
    }
}
