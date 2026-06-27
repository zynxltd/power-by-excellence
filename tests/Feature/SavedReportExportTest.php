<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Exports\LeadExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_lead_export_service_applies_filters(): void
    {
        $accountId = $this->ukAdmin->account_id;
        $sold = Lead::where('account_id', $accountId)->where('status', 'sold')->first();
        $this->assertNotNull($sold);

        $service = app(LeadExportService::class);
        $query = Lead::query()->where('account_id', $accountId);
        $query = $service->applyFilters($query, [
            'status' => 'sold',
            'campaign_id' => $sold->campaign_id,
        ]);

        $csv = $service->buildCsvFromQuery($query, 10);

        $this->assertStringContainsString('uuid,campaign,status', $csv);
        $this->assertStringContainsString($sold->uuid, $csv);
    }

    public function test_saved_report_export_route_returns_csv(): void
    {
        $report = SavedReport::create([
            'account_id' => $this->ukAdmin->account_id,
            'name' => 'Sold leads',
            'filters' => ['status' => 'sold'],
            'status' => 'active',
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('saved-reports.export', $report))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }
}
