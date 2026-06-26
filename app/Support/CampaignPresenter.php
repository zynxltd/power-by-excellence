<?php

namespace App\Support;

use App\Models\Campaign;
use Illuminate\Support\Facades\Storage;

class CampaignPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(Campaign $campaign): array
    {
        $data = $campaign->toArray();
        $data['logo_url'] = $campaign->logo_path
            ? Storage::disk('public')->url($campaign->logo_path)
            : null;
        $data['region'] = CampaignRegion::forCampaign($campaign);

        return $data;
    }
}
