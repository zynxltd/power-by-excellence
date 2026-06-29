<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledExport;
use App\Services\Exports\ScheduledExportRemoteDelivery;
use App\Services\Exports\ScheduledExportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class ScheduledExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_scheduled_export_runner_updates_last_run_at(): void
    {
        Mail::fake();

        $account = Account::where('slug', 'excellence-uk')->first();

        $export = ScheduledExport::create([
            'account_id' => $account->id,
            'name' => 'Daily leads',
            'format' => 'csv',
            'delivery_method' => 'email',
            'cron' => '* * * * *',
            'config' => [
                'filters' => [],
                'email_recipients' => ['admin@example.com'],
            ],
            'status' => 'active',
        ]);

        app(ScheduledExportRunner::class)->run($export);

        $this->assertNotNull($export->fresh()->last_run_at);
    }

    public function test_admin_can_create_scheduled_export(): void
    {
        $this->withoutVite();

        $admin = \App\Models\User::where('email', 'uk@powerbyexcellence.test')->first();

        $response = $this->actingAs($admin)->post('/scheduled-exports', [
            'name' => 'Weekly export',
            'delivery_method' => 'email',
            'cron' => '0 9 * * 1',
            'config' => [
                'email' => 'reports@example.com',
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('scheduled_exports', [
            'name' => 'Weekly export',
            'delivery_method' => 'email',
        ]);
    }

    public function test_sftp_export_uploads_via_remote_delivery(): void
    {
        $account = Account::where('slug', 'excellence-uk')->first();

        $export = ScheduledExport::create([
            'account_id' => $account->id,
            'name' => 'SFTP export',
            'format' => 'csv',
            'delivery_method' => 'sftp',
            'remote_host' => 'sftp.example.com',
            'remote_port' => 22,
            'remote_path' => '/exports',
            'remote_username' => 'exporter',
            'remote_credentials' => 'secret-pass',
            'cron' => '* * * * *',
            'config' => ['filters' => []],
            'status' => 'active',
        ]);

        $mock = Mockery::mock(ScheduledExportRemoteDelivery::class);
        $mock->shouldReceive('upload')
            ->once()
            ->withArgs(function (ScheduledExport $passed, string $csv, string $filename) use ($export) {
                return $passed->id === $export->id
                    && str_contains($csv, 'email')
                    && str_ends_with($filename, '.csv');
            });

        $this->app->instance(ScheduledExportRemoteDelivery::class, $mock);

        $result = app(ScheduledExportRunner::class)->run($export);

        $this->assertTrue($result);
        $this->assertNotNull($export->fresh()->last_run_at);
    }

    public function test_ftp_export_stores_encrypted_credentials(): void
    {
        $account = Account::where('slug', 'excellence-uk')->first();

        $export = ScheduledExport::create([
            'account_id' => $account->id,
            'name' => 'FTP export',
            'format' => 'csv',
            'delivery_method' => 'ftp',
            'remote_host' => 'ftp.example.com',
            'remote_port' => 21,
            'remote_path' => '/incoming',
            'remote_username' => 'ftpuser',
            'remote_credentials' => 'ftp-secret',
            'cron' => '0 6 * * *',
            'config' => ['filters' => []],
            'status' => 'active',
        ]);

        $this->assertSame('ftp-secret', $export->remotePassword());
        $this->assertNotSame('ftp-secret', $export->getRawOriginal('remote_credentials'));
    }
}
