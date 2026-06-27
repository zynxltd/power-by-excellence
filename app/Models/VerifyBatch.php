<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifyBatch extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'user_id',
        'filename',
        'status',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'results',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
