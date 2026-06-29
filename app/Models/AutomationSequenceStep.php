<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationSequenceStep extends Model
{
    protected $fillable = ['automation_sequence_id', 'sort_order', 'action', 'delay_minutes', 'channel', 'config'];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(AutomationSequence::class, 'automation_sequence_id');
    }
}
