<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Supplier;
use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;
use App\Models\User;
use App\Services\ClickTrack\ClickTrackEntitlementService;
use App\Services\ClickTrack\ConversionTrackingService;
use App\Services\Leads\LeadIngestService;
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
        parent::setUp();

        $this->seed(\Database\Seeders\PlatformSeeder::class);

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

    public function test_admin_can_view_click_track_dashboard(): void
    {
        $user = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('click-track.dashboard'))
            ->assertOk();
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
}
