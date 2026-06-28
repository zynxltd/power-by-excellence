<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'name', 'channel', 'subject', 'body', 'html_body', 'preview_data',
    ];

    protected function casts(): array
    {
        return [
            'preview_data' => 'array',
        ];
    }
}
