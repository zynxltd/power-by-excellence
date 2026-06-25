<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HostedForm extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'campaign_id', 'name', 'slug', 'config', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
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

    public function embedUrl(): string
    {
        return url('/forms/'.$this->slug);
    }
}
