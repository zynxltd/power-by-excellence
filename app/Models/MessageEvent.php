<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageEvent extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'message_send_id', 'account_id', 'type', 'url', 'meta', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function messageSend(): BelongsTo
    {
        return $this->belongsTo(MessageSend::class);
    }
}
