<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\Account;
use App\Models\AccountAuditLog;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LogExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->registerLogExportRoutes();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = Account::where('slug', 'excellence-uk')->first();
    }

    protected function registerLogExportRoutes(): void
    {
        require_once base_path('routes/compliance-phase-3.php');

        Route::get('logs/access/export', [\App\Http\Controllers\Admin\AccessLogController::class, 'export'])
            ->name('logs.access.export');
        Route::get('logs/changes/export', [\App\Http\Controllers\Admin\ChangeLogController::class, 'export'])
            ->name('logs.changes.export');
        Route::get('logs/security/export', [\App\Http\Controllers\Admin\SecurityLogController::class, 'export'])
            ->name('logs.security.export');
    }

    protected function tenantRequest()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    /**
     * @return list<string>
     */
    protected function csvLines(string $content): array
    {
        return array_values(array_filter(explode("\n", trim($content)), fn ($line) => $line !== ''));
    }

    public function test_access_log_export_returns_csv_with_headers_and_filtered_rows(): void
    {
        $inRange = AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '10.0.0.1',
            'path' => '/login',
        ]);

        AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'failed',
            'ip_address' => '10.0.0.2',
            'path' => '/login',
        ]);

        $old = AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '10.0.0.3',
            'path' => '/login',
        ]);
        $old->forceFill([
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ])->saveQuietly();

        $response = $this->tenantRequest()
            ->actingAs($this->admin)
            ->get('/logs/access/export?'.http_build_query([
                'days' => 7,
                'action' => 'login',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $lines = $this->csvLines($response->getContent());
        $this->assertStringContainsString('created_at', $lines[0]);
        $this->assertStringContainsString('user_email', $lines[0]);
        $this->assertGreaterThanOrEqual(2, count($lines));
        $this->assertStringContainsString('10.0.0.1', $response->getContent());
        $this->assertStringNotContainsString('10.0.0.2', $response->getContent());
        $this->assertStringNotContainsString('10.0.0.3', $response->getContent());
    }

    public function test_change_log_export_returns_csv_with_expected_row_count(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => 'accepted',
            'field_data' => ['email' => 'export-changelog@test.test'],
            'received_at' => now(),
        ]);

        LeadEvent::create([
            'lead_id' => $lead->id,
            'event_type' => 'lead.exported',
            'level' => 'info',
            'message' => 'Included in export',
        ]);

        $stale = LeadEvent::create([
            'lead_id' => $lead->id,
            'event_type' => 'lead.stale',
            'level' => 'info',
            'message' => 'Outside date window',
        ]);
        $stale->forceFill([
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ])->saveQuietly();

        $response = $this->tenantRequest()
            ->actingAs($this->admin)
            ->get('/logs/changes/export?days=7');

        $response->assertOk();

        $lines = $this->csvLines($response->getContent());
        $this->assertStringContainsString('lead_uuid', $lines[0]);
        $this->assertStringContainsString('event_type', $lines[0]);
        $this->assertGreaterThanOrEqual(2, count($lines));
        $this->assertStringContainsString('lead.exported', $response->getContent());
        $this->assertStringNotContainsString('lead.stale', $response->getContent());
    }

    public function test_security_log_export_includes_access_and_audit_rows(): void
    {
        AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '198.51.100.20',
            'path' => '/login',
        ]);

        AccountAuditLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'campaign.updated',
            'entity_type' => 'campaign',
            'entity_id' => 1,
            'changes' => ['name' => 'Updated'],
            'ip_address' => '198.51.100.21',
        ]);

        $response = $this->tenantRequest()
            ->actingAs($this->admin)
            ->get('/logs/security/export?days=7');

        $response->assertOk();

        $lines = $this->csvLines($response->getContent());
        $this->assertStringContainsString('record_type', $lines[0]);
        $this->assertGreaterThanOrEqual(3, count($lines));
        $this->assertStringContainsString('"access"', $response->getContent());
        $this->assertStringContainsString('"audit"', $response->getContent());
        $this->assertStringContainsString('198.51.100.20', $response->getContent());
        $this->assertStringContainsString('198.51.100.21', $response->getContent());
    }

    public function test_access_log_export_excludes_other_tenant_rows(): void
    {
        $other = Account::where('slug', 'insurance-ca')->first();

        AccessLog::create([
            'account_id' => $other->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '203.0.113.99',
            'path' => '/login',
        ]);

        AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '198.51.100.30',
            'path' => '/login',
        ]);

        $response = $this->tenantRequest()
            ->actingAs($this->admin)
            ->get('/logs/access/export?days=7');

        $content = $response->getContent();
        $this->assertStringContainsString('198.51.100.30', $content);
        $this->assertStringNotContainsString('203.0.113.99', $content);
    }
}
