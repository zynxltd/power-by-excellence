<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageShortLink extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'message_send_id',
        'automation_sequence_step_id',
        'slug',
        'destination_url',
        'click_count',
    ];

    public function messageSend(): BelongsTo
    {
        return $this->belongsTo(MessageSend::class);
    }
}
