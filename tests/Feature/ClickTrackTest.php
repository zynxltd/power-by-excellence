<?php

namespace Tests\Feature;

use App\ClickTrack\IntegrationManifest;
use App\ClickTrack\PricingModuleFlags;
use App\Models\Account;
use App\Models\EventAlert;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Supplier;
use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;
use App\Models\User;
use App\Services\ClickTrack\ClickFraudService;
use App\Services\ClickTrack\ClickCapService;
use App\Services\ClickTrack\ClickTrackEntitlementService;
use App\Services\ClickTrack\ClickTrackPendingQueueService;
use App\Services\ClickTrack\ConversionTrackingService;
use App\Models\SupplierClickPayout;
use App\Services\ClickTrack\SupplierClickPayoutService;
use App\Services\ClickTrack\SupplierClickStatsService;
use App\Services\Leads\LeadIngestService;
use App\Support\ClickTrack\ClickTrackRouteRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickTrackTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected Campaign $campaign;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        $this->afterApplicationCreated(function (): void {
            ClickTrackRouteRegistrar::register();
        });

        parent::setUp();

        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $this->account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $this->campaign = Campaign::where('reference', 'auto-insurance-uk')->firstOrFail();
        $this->supplier = Supplier::where('account_id', $this->account->id)->firstOrFail();

        $settings = $this->account->settings ?? [];
        $settings['subscription_plan'] = 'growth';
        $settings['click_track'] = ['enabled' => true];
        $this->account->update(['settings' => $settings]);
    }

    public function test_tracking_link_redirect_logs_click_and_appends_click_id(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Test offer',
            'token' => 'testtoken123456',
            'destination_url' => 'https://example.com/landing',
            'goal' => 'lead',
            'status' => 'active',
        ]);

        $response = $this->get('/c/'.$link->token.'?sub1=aff123');

        $response->assertRedirect();
        $this->assertStringContainsString('click_id=', $response->headers->get('Location'));
        $this->assertStringContainsString('sub1=aff123', $response->headers->get('Location'));

        $this->assertDatabaseHas('tracking_clicks', [
            'tracking_link_id' => $link->id,
            'sub1' => 'aff123',
        ]);
    }

    public function test_lead_ingest_attaches_click_and_creates_conversion_on_sold(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Offer',
            'token' => 'ingestlink123456',
            'destination_url' => 'https://example.com/form',
            'status' => 'active',
            'config' => ['auto_approve_conversions' => true],
        ]);

        $click = TrackingClick::create([
            'account_id' => $this->account->id,
            'tracking_link_id' => $link->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'click_uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'clicked_at' => now(),
        ]);

        $lead = app(LeadIngestService::class)->ingest([
            'campaign_reference' => $this->campaign->reference,
            'click_id' => $click->click_uuid,
            'email' => 'clicktrack@test.com',
            'firstname' => 'Click',
        ]);

        $this->assertSame($click->id, $lead->fresh()->tracking_click_id);

        $buyer = Buyer::where('account_id', $this->account->id)->firstOrFail();
        $lead->update(['status' => 'sold', 'sold_to_buyer_id' => $buyer->id]);
        app(ConversionTrackingService::class)->fromLeadSold($lead->fresh());

        $this->assertDatabaseHas('tracking_conversions', [
            'lead_id' => $lead->id,
            'tracking_click_id' => $click->id,
            'status' => TrackingConversion::STATUS_APPROVED,
        ]);
    }

    public function test_growth_plan_is_entitled_to_click_track(): void
    {
        $this->assertTrue(app(ClickTrackEntitlementService::class)->isEntitled($this->account));
    }

    public function test_starter_plan_requires_addon_enable(): void
    {
        $settings = $this->account->settings;
        $settings['subscription_plan'] = 'starter';
        unset($settings['click_track']);
        $this->account->update(['settings' => $settings]);

        $this->assertFalse(app(ClickTrackEntitlementService::class)->isEntitled($this->account->fresh()));
    }

    public function test_admin_can_view_click_track_dashboard(): void
    {
        $user = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('click-track.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/ClickTrack/Dashboard'));
    }

    public function test_impression_pixel_returns_gif(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Pixel offer',
            'token' => 'pixellink1234567',
            'destination_url' => 'https://example.com',
            'status' => 'active',
        ]);

        $this->get('/i/'.$link->token)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $this->assertDatabaseHas('tracking_impressions', [
            'tracking_link_id' => $link->id,
        ]);
    }

    public function test_link_cap_usage_tracks_daily_clicks(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Capped offer',
            'token' => 'cappedlink123456',
            'destination_url' => 'https://example.com',
            'status' => 'active',
            'config' => ['cap_daily' => 2],
        ]);

        TrackingClick::create([
            'account_id' => $this->account->id,
            'tracking_link_id' => $link->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'click_uuid' => '11111111-1111-1111-1111-111111111111',
            'clicked_at' => now(),
        ]);
        TrackingClick::create([
            'account_id' => $this->account->id,
            'tracking_link_id' => $link->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'click_uuid' => '22222222-2222-2222-2222-222222222222',
            'clicked_at' => now(),
        ]);

        $usage = app(ClickCapService::class)->usageForLink($link);

        $this->assertSame(2, $usage['clicks_today']);
        $this->assertTrue($usage['click_cap_reached']);
        $this->assertTrue(app(ClickCapService::class)->linkCapReached($link));
    }

    public function test_pending_conversion_queue_lists_pending_items(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Queue offer',
            'token' => 'queueoffer123456',
            'destination_url' => 'https://example.com',
            'status' => 'active',
        ]);

        TrackingConversion::create([
            'account_id' => $this->account->id,
            'tracking_link_id' => $link->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'conversion_uuid' => '33333333-3333-3333-3333-333333333333',
            'goal' => 'lead',
            'status' => TrackingConversion::STATUS_PENDING,
            'payout' => 10,
            'revenue' => 20,
        ]);

        $queue = app(ClickTrackPendingQueueService::class)->conversionQueue($this->account->id);

        $this->assertSame(1, $queue['count']);
        $this->assertCount(1, $queue['items']);
        $this->assertSame('lead', $queue['items'][0]['goal']);
    }

    public function test_pricing_module_flags_export_growth_tier(): void
    {
        $flags = PricingModuleFlags::forPage();

        $this->assertSame('click_track', $flags['product_key']);
        $this->assertTrue($flags['tiers']['growth']['included']);
        $this->assertTrue($flags['tiers']['growth']['show_on_pricing']);
        $this->assertSame('Included', $flags['tiers']['growth']['marketing_label']);

        $manifest = IntegrationManifest::pricingModuleFlags();
        $this->assertSame($flags, $manifest);
    }

    public function test_supplier_portal_clicks_page_loads(): void
    {
        TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Supplier link',
            'token' => 'supplierlink12345',
            'destination_url' => 'https://example.com',
            'status' => 'active',
        ]);

        $supplierUser = User::where('email', 'supplier-portal@excellence-uk.test')->firstOrFail();

        $this->actingAs($supplierUser)
            ->get(route('portal.supplier.clicks'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Supplier/Clicks')
                ->has('stats.links', 1)
            );
    }

    public function test_supplier_click_stats_service_returns_cap_alerts(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Alert link',
            'token' => 'alertlink1234567',
            'destination_url' => 'https://example.com',
            'status' => 'active',
            'config' => ['cap_daily' => 1],
        ]);

        TrackingClick::create([
            'account_id' => $this->account->id,
            'tracking_link_id' => $link->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'click_uuid' => '44444444-4444-4444-4444-444444444444',
            'clicked_at' => now(),
        ]);

        $stats = app(SupplierClickStatsService::class)->forSupplier($this->supplier);

        $this->assertCount(1, $stats['cap_alerts']);
        $this->assertTrue($stats['cap_alerts'][0]['click_cap_reached']);
    }

    public function test_hard_cap_blocks_redirect(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Hard cap',
            'token' => 'hardcaplink123456',
            'destination_url' => 'https://example.com',
            'status' => 'active',
            'config' => ['cap_daily' => 1],
        ]);

        TrackingClick::create([
            'account_id' => $this->account->id,
            'tracking_link_id' => $link->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'click_uuid' => '55555555-5555-5555-5555-555555555555',
            'clicked_at' => now(),
        ]);

        $this->get('/c/'.$link->token.'?sub1=newclick')
            ->assertStatus(429);
    }

    public function test_soft_cap_allows_click_but_flags_usage(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Soft cap',
            'token' => 'softcaplink123456',
            'destination_url' => 'https://example.com',
            'status' => 'active',
            'config' => ['cap_daily' => 10],
        ]);

        for ($i = 0; $i < 8; $i++) {
            TrackingClick::create([
                'account_id' => $this->account->id,
                'tracking_link_id' => $link->id,
                'campaign_id' => $this->campaign->id,
                'supplier_id' => $this->supplier->id,
                'click_uuid' => sprintf('66666666-6666-6666-6666-66666666660%d', $i),
                'clicked_at' => now(),
            ]);
        }

        $usage = app(ClickCapService::class)->usageForLink($link);
        $this->assertTrue($usage['click_soft_cap_reached']);
        $this->assertFalse($usage['click_cap_reached']);

        $this->get('/c/'.$link->token.'?sub1=soft9')
            ->assertRedirect();

        $this->assertSame(9, app(ClickCapService::class)->clicksToday($link));
    }

    public function test_fraud_duplicate_skips_new_click_log(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Fraud test',
            'token' => 'fraudlink12345678',
            'destination_url' => 'https://example.com/landing',
            'status' => 'active',
        ]);

        $first = $this->get('/c/'.$link->token.'?sub1=aff999');
        $first->assertRedirect();
        $clickUuid = TrackingClick::where('tracking_link_id', $link->id)->value('click_uuid');
        $this->assertSame(1, TrackingClick::where('tracking_link_id', $link->id)->count());

        $second = $this->get('/c/'.$link->token.'?sub1=aff999');
        $second->assertRedirect();
        $this->assertStringContainsString($clickUuid, $second->headers->get('Location'));
        $this->assertSame(1, TrackingClick::where('tracking_link_id', $link->id)->count());
    }

    public function test_hourly_cap_enforcement_blocks_clicks(): void
    {
        $settings = $this->account->settings;
        $settings['click_track']['cap_hourly'] = 2;
        $this->account->update(['settings' => $settings]);

        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Hourly cap',
            'token' => 'hourlycap12345678',
            'destination_url' => 'https://example.com',
            'status' => 'active',
        ]);

        $this->get('/c/'.$link->token.'?sub1=h1')->assertRedirect();
        $this->get('/c/'.$link->token.'?sub1=h2')->assertRedirect();
        $this->get('/c/'.$link->token.'?sub1=h3')->assertStatus(429);
    }

    public function test_event_alert_fires_when_click_track_usage_crosses_soft_threshold(): void
    {
        EventAlert::create([
            'account_id' => $this->account->id,
            'name' => 'Click cap warning',
            'metric' => 'click_track_usage_pct',
            'operator' => 'gte',
            'threshold' => 80,
            'channel' => 'webhook',
            'status' => 'active',
            'config' => ['webhook_url' => 'https://example.com/alerts'],
        ]);

        $settings = $this->account->settings;
        $settings['click_track']['clicks_cap'] = 10;
        $this->account->update(['settings' => $settings]);

        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Alert link',
            'token' => 'alertcap123456789',
            'destination_url' => 'https://example.com',
            'status' => 'active',
            'config' => ['cap_daily' => 10],
        ]);

        for ($i = 0; $i < 7; $i++) {
            TrackingClick::create([
                'account_id' => $this->account->id,
                'tracking_link_id' => $link->id,
                'campaign_id' => $this->campaign->id,
                'supplier_id' => $this->supplier->id,
                'click_uuid' => sprintf('77777777-7777-7777-7777-77777777770%d', $i),
                'clicked_at' => now(),
            ]);
        }

        $this->get('/c/'.$link->token.'?sub1=trigger8')->assertRedirect();

        $this->assertDatabaseHas('event_alert_fires', [
            'account_id' => $this->account->id,
            'metric' => 'click_track_usage_pct',
        ]);
    }

    public function test_conversion_approve_creates_supplier_payout_ledger_entry(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Payout offer',
            'token' => 'payoutlink1234567',
            'destination_url' => 'https://example.com',
            'status' => 'active',
            'payout_amount' => 15,
            'revenue_amount' => 30,
            'config' => ['revenue_share_pct' => 50],
        ]);

        $conversion = TrackingConversion::create([
            'account_id' => $this->account->id,
            'tracking_link_id' => $link->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'conversion_uuid' => '88888888-8888-8888-8888-888888888888',
            'goal' => 'lead',
            'status' => TrackingConversion::STATUS_PENDING,
            'payout' => 0,
            'revenue' => 30,
        ]);

        app(SupplierClickPayoutService::class)->syncFromConversion($conversion);
        $this->assertDatabaseHas('supplier_click_payouts', [
            'tracking_conversion_id' => $conversion->id,
            'status' => SupplierClickPayout::STATUS_PENDING,
        ]);

        app(\App\Services\ClickTrack\ConversionTrackingService::class)->approve($conversion);

        $this->assertDatabaseHas('supplier_click_payouts', [
            'tracking_conversion_id' => $conversion->id,
            'status' => SupplierClickPayout::STATUS_APPROVED,
        ]);

        $stats = app(SupplierClickStatsService::class)->forSupplier($this->supplier);
        $this->assertGreaterThan(0, $stats['approved_earnings']);
    }

    public function test_supplier_can_export_click_payout_csv(): void
    {
        $link = TrackingLink::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Export offer',
            'token' => 'exportlink1234567',
            'destination_url' => 'https://example.com',
            'status' => 'active',
        ]);

        $conversion = TrackingConversion::create([
            'account_id' => $this->account->id,
            'tracking_link_id' => $link->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'conversion_uuid' => '99999999-9999-9999-9999-999999999999',
            'goal' => 'lead',
            'status' => TrackingConversion::STATUS_APPROVED,
            'payout' => 12.5,
            'revenue' => 25,
            'approved_at' => now(),
        ]);

        app(SupplierClickPayoutService::class)->syncFromConversion($conversion);

        $supplierUser = User::where('email', 'supplier-portal@excellence-uk.test')->firstOrFail();

        $this->actingAs($supplierUser)
            ->get(route('portal.supplier.clicks.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
