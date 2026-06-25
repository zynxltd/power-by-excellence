<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFinancial extends Model
{
    protected $fillable = [
        'lead_id',
        'revenue',
        'payout',
        'margin',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'payout' => 'decimal:2',
            'margin' => 'decimal:2',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
