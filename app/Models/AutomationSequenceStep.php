<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationSequenceStep extends Model
{
    /** Canvas position (0-based execution order). Alias: {@see getPositionAttribute}. */
    protected $fillable = ['automation_sequence_id', 'sort_order', 'action', 'delay_minutes', 'channel', 'config'];

    public function getPositionAttribute(): int
    {
        return (int) $this->sort_order;
    }

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(AutomationSequence::class, 'automation_sequence_id');
    }
}
