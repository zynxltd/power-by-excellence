<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallReturn extends Model
{
    protected $fillable = [
        'call_session_id',
        'buyer_id',
        'reason',
        'status',
        'credit_amount',
        'refund_transaction_id',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_amount' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function refundTransaction(): BelongsTo
    {
        return $this->belongsTo(BuyerTransaction::class, 'refund_transaction_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
