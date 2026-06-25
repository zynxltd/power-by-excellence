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
}
