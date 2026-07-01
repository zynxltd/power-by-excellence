<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadImport extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'campaign_id',
        'user_id',
        'filename',
        'status',
        'total_rows',
        'processed_rows',
        'success_rows',
        'failed_rows',
        'errors',
        'column_mapping',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'column_mapping' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
