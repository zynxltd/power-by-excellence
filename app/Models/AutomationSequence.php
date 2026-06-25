<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationSequence extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'campaign_id', 'name', 'trigger_event', 'status'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AutomationSequenceStep::class)->orderBy('sort_order');
    }
}
