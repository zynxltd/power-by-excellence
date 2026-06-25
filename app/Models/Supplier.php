<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'reference',
        'name',
        'status',
        'affiliate_settings',
    ];

    protected function casts(): array
    {
        return [
            'affiliate_settings' => 'array',
        ];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function campaignSuppliers(): HasMany
    {
        return $this->hasMany(CampaignSupplier::class);
    }
}
