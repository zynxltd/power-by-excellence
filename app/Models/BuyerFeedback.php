<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerFeedback extends Model
{
    protected $table = 'buyer_feedback';

    protected $fillable = [
        'lead_id',
        'buyer_id',
        'status',
        'converted',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'converted' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }
}
