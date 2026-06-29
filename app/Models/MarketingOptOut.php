<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class MarketingOptOut extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'field_type', 'hash', 'label', 'source',
    ];
}
