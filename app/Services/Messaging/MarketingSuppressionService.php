<?php

namespace App\Services\Messaging;

use App\Models\Account;
use App\Models\MarketingOptOut;
use App\Services\Leads\FieldHashService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MarketingSuppressionService
{
    /**
     * @var list<string>
     */
    protected const IMPORT_HEADERS = ['email', 'phone', 'phone1', 'field_type', 'value'];

    public function __construct(
        protected FieldHashService $fieldHasher,
    ) {}

    public function isSuppressed(int $accountId, string $channel, string $recipient): bool
    {
        $fieldType = $this->channelFieldType($channel);
        $hash = $this->fieldHasher->resolveHash($fieldType, $recipient);

        if (! $hash) {
            return false;
        }

        if (MarketingOptOut::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('field_type', $fieldType)
            ->where('hash', $hash)
            ->exists()) {
            return true;
        }

        return DB::table('suppression_hashes')
            ->where('account_id', $accountId)
            ->where('field_type', $fieldType)
            ->where('hash', $hash)
            ->exists();
    }

    public function optOut(int $accountId, string $fieldType, string $value, string $source = 'unsubscribe'): void
    {
        $hash = $this->fieldHasher->resolveHash($fieldType, $value);

        if (! $hash) {
            return;
        }

        MarketingOptOut::withoutGlobalScopes()->updateOrCreate(
            [
                'account_id' => $accountId,
                'field_type' => $fieldType,
                'hash' => $hash,
            ],
            [
                'source' => $source,
                'label' => $this->maskValue($fieldType, $value),
            ],
        );
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    public function importCsv(int $accountId, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $firstRow = $this->normalizeRow(fgetcsv($handle) ?: []);
        $imported = 0;
        $skipped = 0;

        if ($firstRow === []) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0];
        }

        $hasHeader = $this->rowIsHeader($firstRow);

        if (! $hasHeader) {
            [$added, $missed] = $this->importRow($accountId, $firstRow);
            $imported += $added;
            $skipped += $missed;
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $row = $this->normalizeRow($row);

            if ($hasHeader) {
                $data = $this->combineRow($firstRow, $row);
                if ($data === null) {
                    $skipped++;

                    continue;
                }

                [$added, $missed] = $this->importMappedRow($accountId, $data);
            } else {
                [$added, $missed] = $this->importRow($accountId, $row);
            }

            $imported += $added;
            $skipped += $missed;
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    public function optOutFromAccount(Account $account, string $email): void
    {
        $this->optOut($account->id, 'email', $email);
    }

    public function countForAccount(int $accountId): int
    {
        $optOuts = MarketingOptOut::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->count();

        $hashes = DB::table('suppression_hashes')
            ->where('account_id', $accountId)
            ->count();

        return $optOuts + $hashes;
    }

    public function suppressFromEspEvent(int $accountId, string $recipient, string $eventType): void
    {
        $fieldType = str_contains($recipient, '@') ? 'email' : 'phone1';
        $source = match ($eventType) {
            'bounce' => 'esp_bounce',
            'complaint' => 'esp_complaint',
            default => 'esp',
        };

        $this->optOut($accountId, $fieldType, $recipient, $source);
    }

    protected function importMappedRow(int $accountId, array $data): array
    {
        if (! empty($data['email'])) {
            return $this->storeOptOut($accountId, 'email', (string) $data['email']);
        }

        if (! empty($data['phone1'])) {
            return $this->storeOptOut($accountId, 'phone1', (string) $data['phone1']);
        }

        if (! empty($data['phone'])) {
            return $this->storeOptOut($accountId, 'phone1', (string) $data['phone']);
        }

        if (! empty($data['value']) && ! empty($data['field_type'])) {
            return $this->storeOptOut($accountId, (string) $data['field_type'], (string) $data['value']);
        }

        if (! empty($data['value'])) {
            $value = (string) $data['value'];
            $fieldType = str_contains($value, '@') ? 'email' : 'phone1';

            return $this->storeOptOut($accountId, $fieldType, $value);
        }

        return [0, 1];
    }

    /**
     * @param  list<string>  $row
     * @return array{0: int, 1: int}
     */
    protected function importRow(int $accountId, array $row): array
    {
        $value = trim((string) ($row[0] ?? ''));
        if ($value === '') {
            return [0, 1];
        }

        $fieldType = str_contains($value, '@') ? 'email' : 'phone1';

        return $this->storeOptOut($accountId, $fieldType, $value);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function storeOptOut(int $accountId, string $fieldType, string $value): array
    {
        $hash = $this->fieldHasher->resolveHash($fieldType, $value);
        if (! $hash) {
            return [0, 1];
        }

        MarketingOptOut::withoutGlobalScopes()->updateOrCreate(
            [
                'account_id' => $accountId,
                'field_type' => $fieldType,
                'hash' => $hash,
            ],
            [
                'source' => 'import',
                'label' => $this->maskValue($fieldType, $value),
            ],
        );

        return [1, 0];
    }

    protected function channelFieldType(string $channel): string
    {
        return $channel === 'sms' ? 'phone1' : 'email';
    }

    protected function maskValue(string $fieldType, string $value): string
    {
        $value = trim($value);

        if ($fieldType === 'email' && str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return substr($local, 0, 1).'***@'.$domain;
        }

        $digits = preg_replace('/\D/', '', $value) ?: $value;

        return '***'.substr($digits, -4);
    }

    /**
     * @param  list<string|null>  $row
     * @return list<string>
     */
    protected function normalizeRow(array $row): array
    {
        return array_map(fn ($value) => trim((string) ($value ?? '')), $row);
    }

    /**
     * @param  list<string>  $row
     */
    protected function isBlankRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    /**
     * @param  list<string>  $header
     */
    protected function rowIsHeader(array $header): bool
    {
        $normalized = array_map(fn ($value) => strtolower(trim($value)), $header);

        return (bool) array_intersect($normalized, self::IMPORT_HEADERS);
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $row
     * @return array<string, string>|null
     */
    protected function combineRow(array $header, array $row): ?array
    {
        if (count($header) !== count($row)) {
            return null;
        }

        $data = [];
        foreach ($header as $index => $key) {
            $data[strtolower(trim($key))] = $row[$index] ?? '';
        }

        return $data;
    }
}
