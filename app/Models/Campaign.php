<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'type',
        'channel',
        'call_settings',
        'pipeline_profile',
        'name',
        'logo_path',
        'reference',
        'country',
        'multi_geo',
        'geo_countries',
        'currency',
        'status',
        'vertical_id',
        'payout_supplier_on',
        'payout_amount',
        'caps',
        'dedupe_config',
        'validation_config',
        'api_spec',
        'reference_locked',
        'use_advanced_distribution',
        'sell_mode',
        'max_sells',
        'floor_price',
        'bidding_mode',
        'ping_timeout_ms',
    ];

    protected function casts(): array
    {
        return [
            'payout_amount' => 'decimal:2',
            'floor_price' => 'decimal:2',
            'caps' => 'array',
            'dedupe_config' => 'array',
            'validation_config' => 'array',
            'api_spec' => 'array',
            'reference_locked' => 'boolean',
            'use_advanced_distribution' => 'boolean',
            'multi_geo' => 'boolean',
            'geo_countries' => 'array',
            'call_settings' => 'array',
        ];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CampaignField::class)->orderBy('sort_order');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->orderBy('priority');
    }

    public function distributionConfigs(): HasMany
    {
        return $this->hasMany(DistributionConfig::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function campaignSuppliers(): HasMany
    {
        return $this->hasMany(CampaignSupplier::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuppression(): bool
    {
        return $this->type === 'suppression';
    }
}
