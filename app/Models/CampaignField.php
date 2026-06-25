<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignField extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'label',
        'type',
        'required',
        'ping_field',
        'validation',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'ping_field' => 'boolean',
            'validation' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
