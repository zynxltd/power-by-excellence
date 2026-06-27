<?php

namespace App\Services\Leads;

use App\Models\VerifyBatch;

class VerifyBatchService
{
    /**
     * @return array{valid: int, invalid: int, results: list<array<string, mixed>>}
     */
    public function process(VerifyBatch $batch): array
    {
        $results = $batch->results ?? [];
        $valid = 0;
        $invalid = 0;
        $details = [];

        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }

            $issues = $this->validateRow($row);
            $passed = $issues === [];

            if ($passed) {
                $valid++;
            } else {
                $invalid++;
            }

            $details[] = array_merge($row, [
                'valid' => $passed,
                'issues' => $issues,
            ]);
        }

        if ($results === [] && $batch->total_rows > 0) {
            $invalid = $batch->total_rows;
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'results' => $details ?: $results,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public function validateRow(array $row): array
    {
        $issues = [];
        $email = trim((string) ($row['email'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? $row['phone1'] ?? ''));

        if ($email === '' && $phone === '') {
            $issues[] = ['field' => 'email', 'message' => 'Email or phone is required'];

            return $issues;
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $issues[] = ['field' => 'email', 'message' => 'Invalid email format'];
        }

        if ($phone !== '') {
            $digits = preg_replace('/\D/', '', $phone) ?? '';
            if (strlen($digits) < 10) {
                $issues[] = ['field' => 'phone', 'message' => 'Phone must contain at least 10 digits'];
            }
        }

        return $issues;
    }

    /**
     * @return list<array<string, string>>
     */
    public function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $headers = array_map(
            fn ($header) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
            fgetcsv($handle) ?: []
        );

        if ($headers === []) {
            fclose($handle);

            return [];
        }

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (collect($data)->every(fn ($value) => blank($value))) {
                continue;
            }

            $combined = array_combine($headers, array_pad($data, count($headers), ''));
            if ($combined !== false) {
                $rows[] = $combined;
            }
        }

        fclose($handle);

        return $rows;
    }
}
