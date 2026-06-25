<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformStatusSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'status',
        'payload',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'payload' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
