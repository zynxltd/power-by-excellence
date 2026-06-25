<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'api_key_id',
        'method',
        'path',
        'status_code',
        'duration_ms',
        'error_message',
        'response_summary',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'response_summary' => 'array',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
