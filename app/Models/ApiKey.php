<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use BelongsToAccount;

    protected $hidden = [
        'key_hash',
    ];

    protected $fillable = [
        'account_id',
        'supplier_id',
        'name',
        'type',
        'key_prefix',
        'key_hash',
        'permissions',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
