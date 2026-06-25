<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HybridRuleGroup extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'name',
        'rules',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
        ];
    }
}
