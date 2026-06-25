<?php

namespace App\Services\Leads;

use App\Models\Campaign;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SuppressionImportService
{
    public function import(Campaign $campaign, UploadedFile $file, string $field = 'email'): int
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
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
}
