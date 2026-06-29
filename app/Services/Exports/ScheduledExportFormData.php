<?php

namespace App\Services\Exports;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduledExportFormData
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(Request $request): array
    {
        $method = $request->input('delivery_method', 'email');

        return [
            'name' => 'required|string|max:255',
            'buyer_id' => 'nullable|exists:buyers,id',
            'format' => 'nullable|string|in:csv',
            'delivery_method' => 'required|string|in:email,ftp,sftp',
            'cron' => 'nullable|string|max:64',
            'config' => 'nullable|array',
            'status' => 'nullable|string|in:active,paused',
            'remote_host' => Rule::requiredIf(in_array($method, ['ftp', 'sftp'], true)).'|nullable|string|max:255',
            'remote_port' => 'nullable|integer|min:1|max:65535',
            'remote_path' => 'nullable|string|max:500',
            'remote_username' => Rule::requiredIf(in_array($method, ['ftp', 'sftp'], true)).'|nullable|string|max:255',
            'remote_credentials' => Rule::requiredIf(in_array($method, ['ftp', 'sftp'], true)).'|nullable|string|max:1000',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function toAttributes(array $validated): array
    {
        $config = $validated['config'] ?? [];

        if (($validated['delivery_method'] ?? '') === 'email') {
            $email = $config['email'] ?? $config['email_recipients'][0] ?? null;
            if ($email) {
                $config['email_recipients'] = [(string) $email];
            }
        }

        $attributes = [
            'name' => $validated['name'],
            'buyer_id' => $validated['buyer_id'] ?? null,
            'format' => $validated['format'] ?? 'csv',
            'delivery_method' => $validated['delivery_method'],
            'cron' => $validated['cron'] ?? '0 8 * * *',
            'config' => $config,
            'status' => $validated['status'] ?? 'active',
        ];

        if (in_array($validated['delivery_method'], ['ftp', 'sftp'], true)) {
            $attributes['remote_host'] = $validated['remote_host'] ?? null;
            $attributes['remote_port'] = $validated['remote_port'] ?? null;
            $attributes['remote_path'] = $validated['remote_path'] ?? '/';
            $attributes['remote_username'] = $validated['remote_username'] ?? null;

            if (filled($validated['remote_credentials'] ?? null)) {
                $attributes['remote_credentials'] = $validated['remote_credentials'];
            }
        } else {
            $attributes['remote_host'] = null;
            $attributes['remote_port'] = null;
            $attributes['remote_path'] = null;
            $attributes['remote_username'] = null;
            $attributes['remote_credentials'] = null;
        }

        return $attributes;
    }
}
