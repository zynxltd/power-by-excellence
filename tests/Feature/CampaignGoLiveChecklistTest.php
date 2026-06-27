<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Services\Campaigns\CampaignSetupStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignGoLiveChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_campaign_show_includes_go_live_checklist(): void
    {
        $admin = \App\Models\User::where('email', 'uk@powerbyexcellence.test')->first();
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();

        $this->actingAs($admin)
            ->get("/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Campaigns/Show')
                ->has('goLiveChecklist', 6)
            );
    }

    public function test_checklist_service_returns_expected_items(): void
    {
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();
        $checklist = app(CampaignSetupStatusService::class)->checklist($campaign);

        $this->assertCount(6, $checklist);
        $this->assertArrayHasKey('key', $checklist[0]);
        $this->assertArrayHasKey('label', $checklist[0]);
        $this->assertArrayHasKey('complete', $checklist[0]);
        $this->assertArrayHasKey('route', $checklist[0]);
    }
}
