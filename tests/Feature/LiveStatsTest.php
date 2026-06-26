<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveStatsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_authenticated_admin_can_fetch_live_stats(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('live-stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'leads_today',
                'sold_today',
                'unsold_today',
                'rejected_today',
                'pending',
                'quarantined',
                'ping_posts_today',
                'failed_today',
                'revenue_today',
                'revenue_by_currency',
                'reject_rate',
                'processing_count',
                'queue_breakdown',
                'pipeline_summary',
                'processing_leads',
                'updated_at',
            ]);
    }

    public function test_guest_cannot_fetch_live_stats(): void
    {
        $this->get(route('live-stats'))->assertRedirect(route('login'));
    }

    public function test_super_admin_without_tenant_context_cannot_fetch_live_stats(): void
    {
        $superAdmin = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($superAdmin)
            ->getJson(route('live-stats'))
            ->assertForbidden();
    }

    public function test_super_admin_with_tenant_context_can_fetch_live_stats(): void
    {
        $superAdmin = User::where('email', 'admin@powerbyexcellence.test')->first();
        $account = \App\Models\Account::where('slug', 'excellence-uk')->first();

        $this->actingAs($superAdmin)
            ->withSession(['current_account_id' => $account->id])
            ->getJson(route('live-stats'))
            ->assertOk()
            ->assertJsonStructure(['leads_today', 'sold_today', 'pending', 'quarantined']);
    }
}
