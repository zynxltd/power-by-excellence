<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IvrFlow extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'campaign_id',
        'name',
        'nodes',
        'entry_node',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function callSessions(): HasMany
    {
        return $this->hasMany(CallSession::class);
    }
}
