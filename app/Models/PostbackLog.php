<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostbackLog extends Model
{
    protected $fillable = [
        'postback_id',
        'lead_id',
        'event',
        'url_fired',
        'http_status',
        'status',
        'response',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return ['response' => 'array'];
    }

    public function postback(): BelongsTo
    {
        return $this->belongsTo(Postback::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
