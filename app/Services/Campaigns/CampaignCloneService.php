<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;

class CampaignCloneService
{
    public function clone(Campaign $source, ?string $name = null, ?string $reference = null): Campaign
    {
        $source->load(['fields', 'deliveries', 'distributionConfigs']);

        return DB::transaction(function () use ($source, $name, $reference) {
            $copy = $this->cloneCampaign($source, $name, $reference);
            $this->cloneFields($source, $copy);
            $deliveryMap = $this->cloneDeliveries($source, $copy);
            $this->remapDeliveryReferences($source, $copy, $deliveryMap);
            $this->cloneDistributionConfigs($source, $copy, $deliveryMap);

            return $copy->fresh(['fields', 'deliveries', 'distributionConfigs']);
        });
    }

    protected function cloneCampaign(Campaign $source, ?string $name, ?string $reference): Campaign
    {
        $copy = $source->replicate([
            'id',
            'reference',
            'name',
            'status',
            'reference_locked',
            'created_at',
            'updated_at',
        ]);

        $copy->name = $name ?: $source->name.' (copy)';
        $copy->reference = $this->resolveReference($source, $reference);
        $copy->status = 'saved';
        $copy->reference_locked = false;
        $copy->save();

        return $copy;
    }

    protected function cloneFields(Campaign $source, Campaign $copy): void
    {
        foreach ($source->fields as $field) {
            $newField = $field->replicate(['id', 'campaign_id', 'created_at', 'updated_at']);
            $newField->campaign_id = $copy->id;
            $newField->save();
        }
    }

    /**
     * @return array<int, int>
     */
    protected function cloneDeliveries(Campaign $source, Campaign $copy): array
    {
        $deliveryMap = [];

        foreach ($source->deliveries as $delivery) {
            $newDelivery = $delivery->replicate([
                'id',
                'campaign_id',
                'status',
                'on_success_delivery_id',
                'on_failure_delivery_id',
                'created_at',
                'updated_at',
            ]);

            $newDelivery->campaign_id = $copy->id;
            $newDelivery->status = 'saved';
            $newDelivery->on_success_delivery_id = null;
            $newDelivery->on_failure_delivery_id = null;
            $newDelivery->save();

            $deliveryMap[$delivery->id] = $newDelivery->id;
        }

        return $deliveryMap;
    }

    /**
     * @param  array<int, int>  $deliveryMap
     */
    protected function remapDeliveryReferences(Campaign $source, Campaign $copy, array $deliveryMap): void
    {
        foreach ($source->deliveries as $delivery) {
            $newDelivery = Delivery::find($deliveryMap[$delivery->id]);
            if (! $newDelivery) {
                continue;
            }

            $updates = [];

            if ($delivery->on_success_delivery_id) {
                $updates['on_success_delivery_id'] = $deliveryMap[$delivery->on_success_delivery_id] ?? null;
            }

            if ($delivery->on_failure_delivery_id) {
                $updates['on_failure_delivery_id'] = $deliveryMap[$delivery->on_failure_delivery_id] ?? null;
            }

            $config = $newDelivery->config ?? [];
            if ((int) ($config['campaign_id'] ?? 0) === $source->id) {
                $config['campaign_id'] = $copy->id;
                $updates['config'] = $config;
            }

            if ($updates !== []) {
                $newDelivery->update($updates);
            }
        }
    }

    /**
     * @param  array<int, int>  $deliveryMap
     */
    protected function cloneDistributionConfigs(Campaign $source, Campaign $copy, array $deliveryMap): void
    {
        foreach ($source->distributionConfigs as $config) {
            $newConfig = $config->replicate(['id', 'campaign_id', 'created_at', 'updated_at']);
            $newConfig->campaign_id = $copy->id;
            $newConfig->config = $this->remapDistributionConfig($config->config ?? [], $deliveryMap);
            $newConfig->save();
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, int>  $deliveryMap
     * @return array<string, mixed>
     */
    protected function remapDistributionConfig(array $config, array $deliveryMap): array
    {
        if (! isset($config['groups']) || ! is_array($config['groups'])) {
            return $config;
        }

        $config['groups'] = collect($config['groups'])
            ->map(function (array $group) use ($deliveryMap) {
                $group['delivery_ids'] = collect($group['delivery_ids'] ?? [])
                    ->map(fn ($id) => $deliveryMap[(int) $id] ?? (int) $id)
                    ->values()
                    ->all();

                return $group;
            })
            ->values()
            ->all();

        return $config;
    }

    protected function resolveReference(Campaign $source, ?string $reference): string
    {
        $base = $reference ? strtolower(trim($reference)) : $source->reference.'-copy';
        $candidate = $base;
        $suffix = 2;

        while (Campaign::withoutGlobalScopes()
            ->where('account_id', $source->account_id)
            ->where('reference', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
