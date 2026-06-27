<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class VerticalFieldTemplate extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'vertical_id',
        'name',
        'fields',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_system' => 'boolean',
        ];
    }
}
