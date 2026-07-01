<?php

namespace App\Services\Suppliers;

use App\Jobs\ProcessLeadJob;
use App\Models\Campaign;
use App\Models\LeadImport;
use App\Services\Leads\LeadIngestService;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SupplierCsvImportService
{
    public function __construct(protected LeadIngestService $ingest) {}

    /**
     * @return array{headers: list<string>, rows: list<list<string|null>>}
     */
    public function preview(UploadedFile $file, int $maxRows = 5): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $headers = $this->normalizeHeaders(fgetcsv($handle) ?: []);
        $rows = [];

        while (count($rows) < $maxRows && ($data = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($data)) {
                continue;
            }

            $rows[] = array_map(fn ($value) => $value === null ? null : (string) $value, $data);
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, string>  $columnMapping  CSV header => campaign field name
     */
    public function import(
        UploadedFile $file,
        Campaign $campaign,
        array $columnMapping,
        ?int $userId = null,
        ?int $supplierId = null,
    ): LeadImport {
        $columnMapping = $this->normalizeColumnMapping($columnMapping);
        $this->validateMappingForCampaign($campaign, $columnMapping);

        $path = $file->store('imports');

        $import = LeadImport::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'user_id' => $userId,
            'filename' => $file->getClientOriginalName(),
            'status' => 'processing',
            'column_mapping' => $columnMapping,
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
                'errors' => [['row' => 0, 'error' => 'CSV file has no header row', 'fields' => []]],
            ]);

            return $import->fresh();
        }

        $unmappedHeaders = array_diff($headers, array_keys($columnMapping));
        if ($unmappedHeaders !== [] && $columnMapping !== []) {
            foreach ($unmappedHeaders as $header) {
                if (! in_array($header, array_keys($columnMapping), true)) {
                    // Headers without mapping are ignored; mapped-only import.
                }
            }
        }

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($data)) {
                continue;
            }

            $row++;
            $rawRow = $this->combineRow($headers, $data);

            if ($rawRow === null) {
                $failed++;
                $errors[] = [
                    'row' => $row,
                    'error' => 'Column count does not match header',
                    'fields' => [],
                ];

                continue;
            }

            $payload = $this->applyMapping($rawRow, $columnMapping);

            if ($payload === []) {
                $failed++;
                $errors[] = [
                    'row' => $row,
                    'error' => 'No mapped fields in row',
                    'fields' => $rawRow,
                ];

                continue;
            }

            if ($error = $this->validateMappedRow($payload, $columnMapping, $campaign)) {
                $failed++;
                $errors[] = [
                    'row' => $row,
                    'error' => $error,
                    'fields' => $payload,
                ];

                continue;
            }

            try {
                $payload['campaign_reference'] = $campaign->reference;
                if ($supplierId !== null) {
                    $payload['supplier_id'] = $supplierId;
                }

                $lead = $this->ingest->ingest($payload);
                ProcessLeadJob::dispatch($lead->id);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = [
                    'row' => $row,
                    'error' => $e->getMessage(),
                    'fields' => $payload,
                ];
            }
        }

        fclose($handle);

        $import->update([
            'status' => 'completed',
            'total_rows' => $row,
            'processed_rows' => $row,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'errors' => array_slice($errors, 0, 500),
        ]);

        return $import->fresh();
    }

    /**
     * @return array<string, string>
     */
    public function suggestMapping(array $csvHeaders, Campaign $campaign): array
    {
        $fieldNames = $campaign->fields()->pluck('name')->all();
        $mapping = [];

        foreach ($csvHeaders as $header) {
            $normalized = strtolower(str_replace([' ', '-'], '_', $header));
            foreach ($fieldNames as $field) {
                if (strtolower($field) === $normalized || strtolower($field) === strtolower($header)) {
                    $mapping[$header] = $field;
                    break;
                }
            }
        }

        return $mapping;
    }

    public function buildErrorReportCsv(LeadImport $import): string
    {
        $errors = $import->errors ?? [];

        if ($errors === []) {
            return "row,error\n";
        }

        $fieldKeys = [];
        foreach ($errors as $error) {
            foreach (array_keys($error['fields'] ?? []) as $key) {
                $fieldKeys[$key] = true;
            }
        }

        $columns = array_merge(['row', 'error'], array_keys($fieldKeys));
        $lines = [implode(',', $columns)];

        foreach ($errors as $error) {
            $row = [
                (string) ($error['row'] ?? ''),
                $this->escapeCsv((string) ($error['error'] ?? '')),
            ];

            foreach (array_slice($columns, 2) as $field) {
                $row[] = $this->escapeCsv((string) data_get($error, "fields.{$field}", ''));
            }

            $lines[] = implode(',', $row);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, string|null>  $payload
     * @param  array<string, string>  $columnMapping
     */
    protected function validateMappedRow(array $payload, array $columnMapping, Campaign $campaign): ?string
    {
        $mappedFields = array_values($columnMapping);
        $campaign->loadMissing('fields');

        foreach ($campaign->fields as $field) {
            if (! $field->required || ! in_array($field->name, $mappedFields, true)) {
                continue;
            }

            $value = $payload[$field->name] ?? null;
            if (blank($value)) {
                return "Required field missing: {$field->name}";
            }
        }

        foreach ($payload as $fieldName => $value) {
            if (blank($value)) {
                continue;
            }

            if ($fieldName === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'Invalid email address';
            }

            if (in_array($fieldName, ['phone1', 'phone2', 'phone3'], true)) {
                $digits = preg_replace('/\D/', '', (string) $value);
                if (strlen($digits) < 10) {
                    return "Invalid phone: {$fieldName}";
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $columnMapping
     * @return array<string, string>
     */
    protected function normalizeColumnMapping(array $columnMapping): array
    {
        $normalized = [];

        foreach ($columnMapping as $source => $target) {
            $source = trim((string) $source);
            $target = trim((string) $target);

            if ($source !== '' && $target !== '') {
                $normalized[$source] = $target;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $columnMapping
     */
    protected function validateMappingForCampaign(Campaign $campaign, array $columnMapping): void
    {
        if ($columnMapping === []) {
            throw ValidationException::withMessages([
                'column_mapping' => 'Map at least one CSV column to a campaign field.',
            ]);
        }

        $allowed = $campaign->fields()->pluck('name')->all();

        foreach ($columnMapping as $source => $target) {
            if (! in_array($target, $allowed, true)) {
                throw ValidationException::withMessages([
                    'column_mapping' => "Campaign field \"{$target}\" does not exist.",
                ]);
            }
        }
    }

    /**
     * @param  array<string, string|null>  $rawRow
     * @param  array<string, string>  $columnMapping
     * @return array<string, string|null>
     */
    protected function applyMapping(array $rawRow, array $columnMapping): array
    {
        $payload = [];

        foreach ($columnMapping as $csvHeader => $fieldName) {
            if (! array_key_exists($csvHeader, $rawRow)) {
                continue;
            }

            $value = $rawRow[$csvHeader];
            if ($value !== null && trim($value) !== '') {
                $payload[$fieldName] = trim($value);
            }
        }

        return $payload;
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
     * @param  list<string>  $headers
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

    protected function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
