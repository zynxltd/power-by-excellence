<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledExport extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'buyer_id',
        'name',
        'format',
        'delivery_method',
        'remote_host',
        'remote_port',
        'remote_path',
        'remote_username',
        'remote_credentials',
        'cron',
        'config',
        'status',
        'last_run_at',
    ];

    protected $hidden = [
        'remote_credentials',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'remote_credentials' => 'encrypted',
            'last_run_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function remoteHost(): ?string
    {
        return $this->remote_host
            ?? $this->config['host']
            ?? $this->config['ftp_host']
            ?? null;
    }

    public function remoteUsername(): ?string
    {
        return $this->remote_username
            ?? $this->config['user']
            ?? $this->config['ftp_user']
            ?? null;
    }

    public function remotePassword(): ?string
    {
        if ($this->remote_credentials) {
            return $this->remote_credentials;
        }

        return $this->config['pass'] ?? $this->config['ftp_password'] ?? null;
    }

    public function remotePath(): string
    {
        return $this->remote_path
            ?? $this->config['path']
            ?? $this->config['ftp_path']
            ?? '/';
    }
}
