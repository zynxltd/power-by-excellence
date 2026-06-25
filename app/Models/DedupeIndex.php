<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DedupeIndex extends Model
{
    protected $table = 'dedupe_index';

    protected $fillable = [
        'account_id',
        'campaign_id',
        'field_key',
        'field_value_hash',
        'lead_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
