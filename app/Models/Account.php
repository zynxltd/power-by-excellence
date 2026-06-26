<?php

namespace App\Models;

use App\Support\Tenancy\TenantResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Account extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'favicon_path',
        'brand_name',
        'domain',
        'timezone',
        'default_currency',
        'default_country',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function buyers(): HasMany
    {
        return $this->hasMany(Buyer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function portalUrl(string $path = '/'): string
    {
        return TenantResolver::portalUrl($this, $path);
    }

    public function resolvedDomain(): string
    {
        if ($this->domain) {
            return $this->domain;
        }

        return $this->slug.'.'.TenantResolver::baseDomain();
    }

    /**
     * Public-facing branding for login, guest layouts, and portals.
     *
     * @return array{name: string, display_name: string, logo_url: ?string, favicon_url: ?string}
     */
    public function publicBranding(): array
    {
        $displayName = $this->brand_name ?: $this->name;

        return [
            'name' => $displayName,
            'display_name' => $displayName,
            'logo_url' => $this->logo_path
                ? Storage::disk('public')->url($this->logo_path)
                : null,
            'favicon_url' => $this->favicon_path
                ? Storage::disk('public')->url($this->favicon_path)
                : null,
        ];
    }
}
