<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Postback extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'supplier_id',
        'campaign_id',
        'name',
        'url',
        'method',
        'events',
        'is_active',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PostbackLog::class);
    }
}
