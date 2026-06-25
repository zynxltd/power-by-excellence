<?php

namespace App\Services\Leads;

use App\Jobs\ProcessLeadJob;
use App\Models\Campaign;
use App\Models\LeadImport;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CsvImportService
{
    public function __construct(protected LeadIngestService $ingest) {}

    public function import(UploadedFile $file, Campaign $campaign, ?int $userId = null): LeadImport
    {
        $path = $file->store('imports');

        $import = LeadImport::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'user_id' => $userId,
            'filename' => $file->getClientOriginalName(),
            'status' => 'processing',
        ]);

        AccountContext::set($campaign->account);

        $handle = fopen(Storage::path($path), 'r');
        $headers = fgetcsv($handle);
        $errors = [];
        $row = 0;
        $success = 0;
        $failed = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            $payload = array_combine($headers, $data);

            if ($payload === false) {
                $failed++;
                $errors[] = ['row' => $row, 'error' => 'Column mismatch'];

                continue;
            }

            try {
                $payload['campaign_reference'] = $campaign->reference;
                $lead = $this->ingest->ingest($payload);
                ProcessLeadJob::dispatch($lead->id);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $row, 'error' => $e->getMessage()];
            }
        }

        fclose($handle);

        $import->update([
            'status' => 'completed',
            'total_rows' => $row,
            'processed_rows' => $row,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'errors' => array_slice($errors, 0, 100),
        ]);

        return $import->fresh();
    }
}
