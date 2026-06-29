<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationSequenceEnrollment extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'automation_sequence_id',
        'lead_id',
        'current_step_order',
        'next_run_at',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'next_run_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(AutomationSequence::class, 'automation_sequence_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
