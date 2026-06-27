<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Services\Forms\SupplierHostedFormService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HostedForm extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'campaign_id',
        'supplier_id',
        'name',
        'slug',
        'config',
        'is_active',
        'approval_status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'submission_notes',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HostedForm $form): void {
            $form->slug ??= Str::slug($form->name).'-'.Str::random(6);
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isSupplierOwned(): bool
    {
        return $this->supplier_id !== null;
    }

    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->approval_status === null) {
            return true;
        }

        return $this->approval_status === SupplierHostedFormService::STATUS_APPROVED;
    }

    public function scopeLive($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                    ->orWhere('approval_status', SupplierHostedFormService::STATUS_APPROVED);
            });
    }

    public function embedUrl(array $params = [], bool $iframe = false): string
    {
        return app(\App\Services\Forms\HostedFormEmbedService::class)->embedUrl($this, $params, $iframe);
    }

    public function iframeSnippet(array $params = [], int $height = 720): string
    {
        return app(\App\Services\Forms\HostedFormEmbedService::class)->iframeSnippet($this, $params, $height);
    }
}
