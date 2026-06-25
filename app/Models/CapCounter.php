<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapCounter extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'period',
        'period_key',
        'count',
        'spend',
        'reset_at',
    ];

    protected function casts(): array
    {
        return [
            'reset_at' => 'datetime',
            'spend' => 'decimal:2',
        ];
    }
}
