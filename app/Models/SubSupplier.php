<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubSupplier extends Model
{
    protected $fillable = [
        'source_id',
        'ssid',
        'name',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
