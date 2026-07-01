<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerInvoice extends Model
{
    protected $fillable = [
        'buyer_id',
        'stripe_invoice_id',
        'pdf_url',
        'amount',
        'currency',
        'period_start',
        'period_end',
        'status',
        'email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }
}
