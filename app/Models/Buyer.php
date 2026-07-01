<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Buyer extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'reference',
        'name',
        'email',
        'status',
        'credit_balance',
        'currency',
        'caps',
        'schedule',
        'settings',
        'stripe_customer_id',
        'portal_password',
    ];

    protected function casts(): array
    {
        return [
            'credit_balance' => 'decimal:2',
            'caps' => 'array',
            'schedule' => 'array',
            'settings' => 'array',
            'portal_password' => 'hashed',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BuyerTransaction::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BuyerInvoice::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'sold_to_buyer_id');
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return array{logo_url: ?string, primary_color: ?string, welcome_text: ?string}
     */
    public function portalBranding(): array
    {
        $settings = $this->settings ?? [];

        return [
            'logo_url' => filled($settings['portal_logo_url'] ?? null) ? (string) $settings['portal_logo_url'] : null,
            'primary_color' => filled($settings['portal_primary_color'] ?? null) ? (string) $settings['portal_primary_color'] : null,
            'welcome_text' => filled($settings['portal_welcome_text'] ?? null) ? (string) $settings['portal_welcome_text'] : null,
        ];
    }

    public function resolvedCurrency(): string
    {
        return strtoupper($this->currency ?: $this->account?->default_currency ?: 'GBP');
    }
}
