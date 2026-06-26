<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_api_docs_page_loads_with_platform_documentation(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('api-docs.index', ['campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ApiDocs/Index')
                ->has('apiBaseUrl')
                ->has('endpoints', 15)
                ->has('statusFields', 11)
                ->has('leadStatuses', 9)
                ->has('guides', 6)
                ->where('selectedCampaign.id', $campaign->id)
                ->where('selectedCampaign.reference', 'loans-uk')
                ->has('selectedSpec.fields')
            );
    }

    public function test_api_docs_page_without_campaign_still_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('api-docs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ApiDocs/Index')
                ->where('selectedCampaign', null)
                ->has('campaigns')
            );
    }
}
