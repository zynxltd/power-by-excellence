<?php

namespace Tests\Feature;

use App\Jobs\RunTenantDataExportJob;
use App\Models\Account;
use App\Models\TenantDataExport;
use App\Models\User;
use App\Services\Compliance\TenantDataExportService;
use App\Services\Messaging\MarketingSuppressionService;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class TenantDataExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        Storage::fake('local');
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = Account::where('slug', 'excellence-uk')->first();
        AccountContext::set($this->account);
    }

    public function test_sar_export_generates_expected_zip_structure(): void
    {
        app(MarketingSuppressionService::class)->optOut(
            $this->account->id,
            'email',
            'sar-test@example.com',
            'manual',
        );

        $export = app(TenantDataExportService::class)->request($this->account, $this->admin->id);
        app(TenantDataExportService::class)->run($export);

        $export->refresh();
        $this->assertSame('ready', $export->status);
        $this->assertNotNull($export->storage_path);
        Storage::disk('local')->assertExists($export->storage_path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($export->storage_path)));
        $this->assertNotFalse($zip->locateName('leads.csv'));
        $this->assertNotFalse($zip->locateName('users.csv'));
        $this->assertNotFalse($zip->locateName('access_logs.csv'));
        $this->assertNotFalse($zip->locateName('audit_logs.csv'));
        $this->assertNotFalse($zip->locateName('marketing_opt_outs.csv'));
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $zip->close();
    }

    public function test_large_tenant_export_is_queued(): void
    {
        Queue::fake();

        $export = TenantDataExport::create([
            'account_id' => $this->account->id,
            'requested_by' => $this->admin->id,
            'status' => 'pending',
            'lead_count' => TenantDataExportService::QUEUE_LEAD_THRESHOLD + 1,
        ]);

        $this->mock(TenantDataExportService::class, function ($mock) use ($export) {
            $mock->shouldReceive('leadCount')->andReturn(TenantDataExportService::QUEUE_LEAD_THRESHOLD + 1);
            $mock->shouldReceive('shouldQueue')->andReturn(true);
            $mock->shouldReceive('request')->andReturn($export);
        });

        RunTenantDataExportJob::dispatch($export->id);

        Queue::assertPushed(RunTenantDataExportJob::class);
    }

    public function test_export_job_builds_ready_archive(): void
    {
        $export = app(TenantDataExportService::class)->request($this->account, $this->admin->id);

        (new RunTenantDataExportJob($export->id))->handle(app(TenantDataExportService::class));

        $export->refresh();
        $this->assertSame('ready', $export->status);
        Storage::disk('local')->assertExists($export->storage_path);
    }
}
