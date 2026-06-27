<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledExport;
use App\Services\Exports\ScheduledExportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
                'email_recipients' => ['reports@example.com'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('scheduled_exports', [
            'name' => 'Weekly export',
            'delivery_method' => 'email',
        ]);
    }
}
