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
        $headers = $this->normalizeHeaders(fgetcsv($handle) ?: []);
        $errors = [];
        $row = 0;
        $success = 0;
        $failed = 0;

        if ($headers === []) {
            fclose($handle);
            $import->update([
                'status' => 'completed',
                'total_rows' => 0,
                'processed_rows' => 0,
                'success_rows' => 0,
                'failed_rows' => 0,
                'errors' => [['row' => 0, 'error' => 'CSV file has no header row']],
            ]);

            return $import->fresh();
        }

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($data)) {
                continue;
            }

            $row++;
            $payload = $this->combineRow($headers, $data);

            if ($payload === null) {
                $failed++;
                $errors[] = ['row' => $row, 'error' => 'Column count does not match header'];

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

    /**
     * @param  list<string|null>|false  $headers
     * @return list<string>
     */
    protected function normalizeHeaders(array|false $headers): array
    {
        if ($headers === false || $headers === []) {
            return [];
        }

        return array_values(array_filter(array_map(function ($header) {
            if ($header === null) {
                return null;
            }

            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);

            return trim($header);
        }, $headers)));
    }

    /**
     * @param  list<string|null>  $headers
     * @param  list<string|null>  $data
     * @return array<string, string|null>|null
     */
    protected function combineRow(array $headers, array $data): ?array
    {
        if (count($headers) !== count($data)) {
            return null;
        }

        $payload = array_combine($headers, $data);

        return $payload === false ? null : $payload;
    }

    /**
     * @param  list<string|null>  $data
     */
    protected function isBlankRow(array $data): bool
    {
        return collect($data)->every(fn ($value) => blank($value));
    }
}
