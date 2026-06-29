<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDataExport extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'requested_by',
        'status',
        'storage_path',
        'lead_count',
        'error_message',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready'
            && filled($this->storage_path)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
