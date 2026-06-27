<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledExport extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'buyer_id',
        'name',
        'format',
        'delivery_method',
        'cron',
        'config',
        'status',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }
}
