<?php

namespace App\Support;

use Illuminate\Http\Response;

class CsvExport
{
    /**
     * @param  list<mixed>  $values
     */
    public static function escapeRow(array $values): string
    {
        return implode(',', array_map(
            fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
            $values
        ));
    }

    public static function download(string $body, string $filename): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
