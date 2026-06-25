<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'name',
        'url',
        'events',
        'secret',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
