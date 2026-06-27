<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class SendingProfile extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'name', 'provider', 'domain_match', 'from_name',
        'from_email', 'reply_to', 'config', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_default' => 'boolean',
        ];
    }
}
