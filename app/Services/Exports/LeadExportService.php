<?php

namespace App\Services\Exports;

use App\Models\Lead;
use App\Services\Leads\LeadQualityService;
use App\Support\CsvExport;

class LeadExportService
{
    public function applyFilters($query, array $filters)
    {
        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', (int) $filters['campaign_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('received_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('received_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['sold_to_buyer_id'])) {
            $query->where('sold_to_buyer_id', (int) $filters['sold_to_buyer_id']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        return $query;
    }

    public function buildCsvFromQuery($query, int $limit = 5000): string
    {
        $leads = (clone $query)
            ->with(['campaign:id,name,reference', 'financials', 'soldToBuyer:id,name'])
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get();

        $csv = "uuid,campaign,status,quality_score,email_status,hlr_status,firstname,lastname,email,phone,zipcode,revenue,buyer,received_at,distributed_at\n";

        foreach ($leads as $lead) {
            /** @var Lead $lead */
            $quality = LeadQualityService::analyzeLead($lead);
            $csv .= CsvExport::escapeRow([
                $lead->uuid,
                $lead->campaign?->reference ?? '',
                $lead->status->value,
                $quality['score'],
                $quality['email']['label'],
                $quality['hlr']['label'],
                $lead->getField('firstname'),
                $lead->getField('lastname'),
                $lead->getField('email'),
                $lead->getField('phone1'),
                $lead->getField('zipcode'),
                $lead->financials?->revenue ?? 0,
                $lead->soldToBuyer?->name ?? '',
                $lead->received_at,
                $lead->distributed_at,
            ])."\n";
        }

        return $csv;
    }
}
