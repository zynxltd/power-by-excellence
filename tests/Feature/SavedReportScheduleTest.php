<?php

namespace Tests\Feature;

use App\Console\Commands\ProcessSavedReportsCommand;
use App\Jobs\RunSavedReportJob;
use App\Mail\SavedReportExportMail;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Exports\SavedReportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SavedReportScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_schedule_and_recipients_save_with_next_run_at(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('saved-reports.store'), [
                'name' => 'Weekly sold leads',
                'filters' => ['status' => 'sold'],
                'schedule_preset' => 'weekly_monday_7am',
                'email_recipients' => ['reports@excellence.test', 'ops@excellence.test'],
                'status' => 'active',
            ])
            ->assertRedirect();

        $report = SavedReport::where('name', 'Weekly sold leads')->first();

        $this->assertNotNull($report);
        $this->assertSame('0 7 * * 1', $report->schedule_cron);
        $this->assertSame(['reports@excellence.test', 'ops@excellence.test'], $report->email_recipients);
        $this->assertNotNull($report->next_run_at);
        $this->assertTrue($report->next_run_at->isFuture());
    }

    public function test_process_command_dispatches_due_saved_report_jobs(): void
    {
        Bus::fake();

        SavedReport::create([
            'account_id' => $this->ukAdmin->account_id,
            'name' => 'Due report',
            'filters' => ['status' => 'sold'],
            'schedule_cron' => '0 7 * * *',
            'email_recipients' => ['reports@excellence.test'],
            'status' => 'active',
            'next_run_at' => now()->subMinute(),
        ]);

        $this->artisan(ProcessSavedReportsCommand::class)->assertSuccessful();

        Bus::assertDispatched(RunSavedReportJob::class, fn (RunSavedReportJob $job) => $job->savedReportId > 0);
    }

    public function test_run_saved_report_job_exports_and_emails_recipients(): void
    {
        Mail::fake();

        $report = SavedReport::create([
            'account_id' => $this->ukAdmin->account_id,
            'name' => 'Job report',
            'filters' => ['status' => 'sold'],
            'schedule_cron' => '0 8 * * *',
            'email_recipients' => ['reports@excellence.test'],
            'status' => 'active',
            'next_run_at' => now()->subMinute(),
        ]);

        $result = app(SavedReportRunner::class)->run($report, scheduled: true);

        $this->assertTrue($result);
        Mail::assertSent(SavedReportExportMail::class, function (SavedReportExportMail $mail) use ($report) {
            return $mail->report->is($report)
                && str_contains($mail->csv, 'uuid,campaign,status');
        });
        $report->refresh();
        $this->assertSame('success', $report->last_run_status);
        $this->assertNotNull($report->last_run_at);
        $this->assertNotNull($report->next_run_at);
        $this->assertTrue($report->next_run_at->isFuture());
    }

    public function test_scheduled_report_requires_recipients(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('saved-reports.store'), [
                'name' => 'Missing recipients',
                'schedule_preset' => 'daily_8am',
                'email_recipients' => [],
            ])
            ->assertSessionHasErrors('email_recipients');
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }
}
