<?php

namespace App\Services\Leads;

use App\Models\Campaign;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SuppressionImportService
{
    public function import(Campaign $campaign, UploadedFile $file, string $field = 'email'): int
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = $this->normalizeHeaders(fgetcsv($handle) ?: []);
        $count = 0;

        if ($header === []) {
            fclose($handle);

            return 0;
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $data = $this->combineRow($header, $row);
            if ($data === null) {
                continue;
            }

            $value = $data[$field] ?? $data[array_key_first($data)] ?? null;
            if (blank($value)) {
                continue;
            }

            $hash = hash('sha256', strtolower(trim((string) $value)));

            DB::table('suppression_hashes')->updateOrInsert(
                [
                    'account_id' => $campaign->account_id,
                    'field_type' => $field,
                    'hash' => $hash,
                ],
                ['buyer_id' => null, 'created_at' => now(), 'updated_at' => now()]
            );
            $count++;
        }

        fclose($handle);

        app(\App\Services\Security\AuditLogService::class)->record(
            'suppression.imported',
            'campaign',
            $campaign->id,
            ['field' => $field, 'count' => $count]
        );

        return $count;
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

        $combined = array_combine($headers, $data);

        return $combined === false ? null : $combined;
    }

    /**
     * @param  list<string|null>  $data
     */
    protected function isBlankRow(array $data): bool
    {
        return collect($data)->every(fn ($value) => blank($value));
    }
}
