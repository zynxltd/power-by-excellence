<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $fillable = [
        'supplier_id',
        'sid',
        'name',
        'caps',
        'payout_override',
    ];

    protected function casts(): array
    {
        return [
            'caps' => 'array',
            'payout_override' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function subSuppliers(): HasMany
    {
        return $this->hasMany(SubSupplier::class);
    }
}
