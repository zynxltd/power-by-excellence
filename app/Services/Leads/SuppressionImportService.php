<?php

namespace App\Services\Leads;

use App\Models\Campaign;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SuppressionImportService
{
    /**
     * @var list<string>
     */
    protected const HEADER_NAMES = ['email', 'phone', 'phone1', 'hash', 'value'];

    public function __construct(
        protected FieldHashService $fieldHasher,
    ) {}

    public function import(Campaign $campaign, UploadedFile $file, string $field = 'email'): int
    {
        $handle = fopen($file->getRealPath(), 'r');
        $firstRow = $this->normalizeRow(fgetcsv($handle) ?: []);
        $count = 0;

        if ($firstRow === []) {
            fclose($handle);

            return 0;
        }

        $hasHeader = $this->rowIsHeader($firstRow);

        if (! $hasHeader) {
            $count += $this->storeValue($campaign, $field, $firstRow[0] ?? null);
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $row = $this->normalizeRow($row);

            if ($hasHeader) {
                $data = $this->combineRow($firstRow, $row);
                if ($data === null) {
                    continue;
                }

                $value = $data[$field] ?? $data[array_key_first($data)] ?? null;
            } else {
                $value = $row[0] ?? null;
            }

            $count += $this->storeValue($campaign, $field, $value);
        }

        fclose($handle);

        app(\App\Services\Security\AuditLogService::class)->record(
            'suppression.imported',
            'campaign',
            $campaign->id,
            ['field' => $field, 'count' => $count, 'algorithm' => FieldHashService::ALGORITHM]
        );

        return $count;
    }

    protected function storeValue(Campaign $campaign, string $field, mixed $value): int
    {
        if (blank($value)) {
            return 0;
        }

        $hash = $this->fieldHasher->resolveHash($field, (string) $value);
        if ($hash === null) {
            return 0;
        }

        DB::table('suppression_hashes')->updateOrInsert(
            [
                'account_id' => $campaign->account_id,
                'field_type' => $field,
                'hash' => $hash,
            ],
            ['buyer_id' => null, 'created_at' => now(), 'updated_at' => now()]
        );

        return 1;
    }

    /**
     * @param  list<string|null>|false  $headers
     * @return list<string>
     */
    protected function normalizeRow(array|false $headers): array
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

    /**
     * @param  list<string>  $row
     */
    protected function rowIsHeader(array $row): bool
    {
        if ($this->isBlankRow($row)) {
            return false;
        }

        if (count($row) !== 1) {
            return true;
        }

        return in_array(strtolower($row[0]), self::HEADER_NAMES, true);
    }
}
