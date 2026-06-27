<?php

namespace Tests\Feature;

use App\Models\AutoResponder;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadByteFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_features_hub_and_subpages_load(): void
    {
        $routes = [
            '/features',
            '/features/capture',
            '/features/validation',
            '/features/routing',
            '/features/delivery',
            '/features/auto-responders',
            '/reports',
            '/routing/simulator',
        ];

        foreach ($routes as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_auto_responder_can_be_created_and_removed(): void
    {
        $campaign = Campaign::first();

        $this->actingAs($this->admin)
            ->post(route('features.auto-responders.store'), [
                'campaign_id' => $campaign->id,
                'name' => 'Sold thank you',
                'channel' => 'email',
                'trigger_event' => 'on_lead_sold',
                'status' => 'active',
                'config' => [
                    'subject' => 'Thanks {{firstname}}',
                    'body' => 'We received your enquiry.',
                    'to_field' => 'email',
                ],
            ])
            ->assertRedirect();

        $responder = AutoResponder::where('name', 'Sold thank you')->first();
        $this->assertNotNull($responder);

        $this->actingAs($this->admin)
            ->delete(route('features.auto-responders.destroy', $responder))
            ->assertRedirect();

        $this->assertDatabaseMissing('auto_responders', ['id' => $responder->id]);
    }

    public function test_auto_responder_test_send_returns_preview_before_provider_is_connected(): void
    {
        config([
            'messaging.sms_provider' => 'log',
            'messaging.email_provider' => 'sendgrid',
            'services.sendgrid.key' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('features.auto-responders.test'), [
                'channel' => 'sms',
                'recipient' => '+447700900123',
                'config' => [
                    'body' => 'Hi [firstname], thanks for your enquiry.',
                ],
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('autoResponderTestResult', fn (array $result) => $result['mode'] === 'preview'
                && $result['channel'] === 'sms'
                && str_contains($result['body'], 'Alex'));

        $this->actingAs($this->admin)
            ->post(route('features.auto-responders.test'), [
                'channel' => 'email',
                'recipient' => 'not-an-email',
                'config' => [
                    'subject' => 'Test',
                    'body' => 'Hello',
                ],
            ])
            ->assertSessionHasErrors('recipient');
    }

    public function test_campaign_validation_rules_can_be_updated(): void
    {
        $campaign = Campaign::first();

        $this->actingAs($this->admin)
            ->patch(route('campaigns.validation', $campaign), [
                'validation_config' => [
                    'require_email' => true,
                    'require_phone' => true,
                    'block_disposable_email' => true,
                ],
                'dedupe_config' => [
                    'reject_days' => 30,
                    'fields' => ['email', 'phone1'],
                ],
            ])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertTrue($campaign->validation_config['require_email'] ?? false);
        $this->assertEquals(30, $campaign->dedupe_config['reject_days'] ?? null);
    }

    public function test_delivery_index_filters_and_clone(): void
    {
        $delivery = Delivery::first();

        $this->actingAs($this->admin)
            ->get(route('deliveries.index', ['campaign_id' => $delivery->campaign_id, 'view' => 'table']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Deliveries/Index')
                ->has('filters')
                ->has('stats')
            );

        $this->actingAs($this->admin)
            ->post(route('deliveries.clone', $delivery))
            ->assertRedirect();

        $this->assertDatabaseHas('deliveries', [
            'name' => $delivery->name.' (copy)',
            'status' => 'saved',
        ]);
    }

    public function test_delivery_show_includes_health_and_logs(): void
    {
        $delivery = Delivery::first();

        $this->actingAs($this->admin)
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Deliveries/Show')
                ->has('health')
                ->has('stats')
                ->has('recentLogs')
                ->has('pingTreeLinks')
            );
    }

    public function test_distribution_show_renders_visual_tree(): void
    {
        $config = DistributionConfig::first();
        $this->assertNotNull($config);

        $this->actingAs($this->admin)
            ->get(route('distribution.show', $config))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Distribution/Show')
                ->has('tiers')
                ->has('config')
            );
    }

    public function test_routing_simulator_runs_dry_run(): void
    {
        $campaign = Campaign::first();

        $this->actingAs($this->admin)
            ->post(route('routing.simulator.run'), [
                'campaign_id' => $campaign->id,
                'field_data' => [
                    'firstname' => 'Sim',
                    'email' => 'sim@test.com',
                    'phone1' => '07700900123',
                ],
            ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Routing/Simulator')
                ->has('simulation')
                ->where('simulation.would_sell', fn ($v) => is_bool($v))
            );
    }

    public function test_reports_page_with_period_filter(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.index', ['days' => 14]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Reports/Index')
                ->where('days', 14)
                ->has('charts')
                ->has('byBuyer')
                ->has('byCampaign')
                ->has('bySid')
                ->has('deliveryPerformance')
                ->has('summary.kpis')
                ->where('summary.kpis.epl', fn ($v) => is_numeric($v))
                ->where('summary.kpis.epc', fn ($v) => is_numeric($v))
            );
    }
}
