<?php

namespace App\Services\Exports;

use App\Models\ScheduledExport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class ScheduledExportRemoteDelivery
{
    public function upload(ScheduledExport $export, string $contents, string $filename): void
    {
        if (! in_array($export->delivery_method, ['ftp', 'sftp'], true)) {
            throw new \InvalidArgumentException("Unsupported remote delivery method: {$export->delivery_method}");
        }

        $host = $export->remoteHost();
        $username = $export->remoteUsername();

        if (! $host || ! $username) {
            throw new \RuntimeException('Remote host and username are required for FTP/SFTP delivery.');
        }

        $remotePath = trim($export->remotePath(), '/').'/'.$filename;
        $disk = $this->disk($export);

        if (! $disk->put($remotePath, $contents)) {
            throw new \RuntimeException("Remote upload failed for {$remotePath}");
        }
    }

    protected function disk(ScheduledExport $export): Filesystem
    {
        $driver = $export->delivery_method;
        $defaultPort = $driver === 'sftp' ? 22 : 21;

        return Storage::build([
            'driver' => $driver,
            'host' => $export->remoteHost(),
            'username' => $export->remoteUsername(),
            'password' => $export->remotePassword() ?? '',
            'port' => $export->remote_port ?? $defaultPort,
            'root' => '/',
            'timeout' => 30,
            'throw' => true,
        ]);
    }
}
