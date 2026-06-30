<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Lead;
use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;
use App\Models\User;
use App\Services\ClickTrack\ConversionTrackingService;
use App\Support\ClickTrack\ClickTrackRouteRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkConversionPostbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->afterApplicationCreated(function (): void {
            ClickTrackRouteRegistrar::register();
        });

        parent::setUp();

        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_conversion_postback_url_saved_on_link_update(): void
    {
        $account = \App\Models\Account::where('slug', 'excellence-uk')->firstOrFail();
        $campaign = \App\Models\Campaign::where('reference', 'auto-insurance-uk')->firstOrFail();
        $supplier = \App\Models\Supplier::where('account_id', $account->id)->firstOrFail();

        $link = TrackingLink::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplier->id,
            'name' => 'Postback offer',
            'token' => 'postbacklink123456',
            'destination_url' => 'https://example.com/landing',
            'status' => 'active',
        ]);

        $admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();

        $this->actingAs($admin)
            ->patch('/click-track/links/'.$link->id, [
                'name' => $link->name,
                'destination_url' => $link->destination_url,
                'goal' => 'lead',
                'status' => 'active',
                'auto_approve_conversions' => true,
                'conversion_postback_url' => 'https://affiliate.test/pb?click=[click_id]&lead=[lead_uuid]',
                'conversion_postback_method' => 'get',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tracking_links', [
            'id' => $link->id,
            'conversion_postback_url' => 'https://affiliate.test/pb?click=[click_id]&lead=[lead_uuid]',
        ]);

        $link->refresh();
        $this->assertSame('get', $link->conversion_postback_macros['method'] ?? null);
    }

    public function test_macro_expansion_replaces_postback_tokens(): void
    {
        $service = app(ConversionTrackingService::class);
        $fields = $service->sampleMacroFields();

        $expanded = $service->expandPostbackUrl(
            'https://affiliate.test/pb?click_id=[click_id]&sub1=[sub1]&payout=[payout]&value=[conversion_value]&lead=[lead_uuid]',
            $fields,
        );

        $this->assertStringContainsString('click_id='.$fields['click_id'], $expanded);
        $this->assertStringContainsString('sub1='.$fields['sub1'], $expanded);
        $this->assertStringContainsString('payout='.$fields['payout'], $expanded);
        $this->assertStringContainsString('value='.$fields['conversion_value'], $expanded);
        $this->assertStringContainsString('lead='.$fields['lead_uuid'], $expanded);
    }

    public function test_approved_conversion_fires_get_postback_with_expanded_url(): void
    {
        Http::fake();

        $account = \App\Models\Account::where('slug', 'excellence-uk')->firstOrFail();
        $campaign = \App\Models\Campaign::where('reference', 'auto-insurance-uk')->firstOrFail();
        $supplier = \App\Models\Supplier::where('account_id', $account->id)->firstOrFail();
        $buyer = Buyer::where('account_id', $account->id)->firstOrFail();

        $link = TrackingLink::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplier->id,
            'name' => 'Postback link',
            'token' => 'postbackfire123456',
            'destination_url' => 'https://example.com/landing',
            'status' => 'active',
            'conversion_postback_url' => 'https://affiliate.test/conv?click_id=[click_id]&payout=[payout]&lead=[lead_uuid]',
            'conversion_postback_macros' => ['method' => 'get'],
        ]);

        $click = TrackingClick::create([
            'account_id' => $account->id,
            'tracking_link_id' => $link->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplier->id,
            'click_uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'sub1' => 'aff123',
            'clicked_at' => now(),
        ]);

        $lead = Lead::withoutGlobalScopes()->where('account_id', $account->id)->firstOrFail();
        $lead->update([
            'tracking_click_id' => $click->id,
            'status' => 'sold',
            'sold_to_buyer_id' => $buyer->id,
        ]);

        $conversion = TrackingConversion::create([
            'account_id' => $account->id,
            'tracking_link_id' => $link->id,
            'tracking_click_id' => $click->id,
            'lead_id' => $lead->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplier->id,
            'buyer_id' => $buyer->id,
            'conversion_uuid' => 'conv-uuid-test-001',
            'goal' => 'lead',
            'status' => TrackingConversion::STATUS_PENDING,
            'payout' => 12.50,
            'revenue' => 45.00,
            'sale_amount' => 45.00,
        ]);

        app(ConversionTrackingService::class)->approve($conversion, $lead->fresh());

        Http::assertSent(function ($request) use ($click, $lead) {
            return $request->method() === 'GET'
                && str_contains($request->url(), 'click_id='.$click->click_uuid)
                && str_contains($request->url(), 'payout=12.50')
                && str_contains($request->url(), 'lead='.$lead->uuid);
        });
    }

    public function test_blank_link_postback_inherits_supplier_default(): void
    {
        Http::fake();

        $account = \App\Models\Account::where('slug', 'excellence-uk')->firstOrFail();
        $campaign = \App\Models\Campaign::where('reference', 'auto-insurance-uk')->firstOrFail();
        $supplier = \App\Models\Supplier::where('account_id', $account->id)->firstOrFail();
        $supplier->update([
            'affiliate_settings' => array_merge($supplier->affiliate_settings ?? [], [
                'default_postback_url' => 'https://affiliate.test/default?lead=[lead_uuid]',
            ]),
        ]);

        $link = TrackingLink::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplier->id,
            'name' => 'Inherited postback',
            'token' => 'inheritpost123456',
            'destination_url' => 'https://example.com/landing',
            'status' => 'active',
        ]);

        $lead = Lead::withoutGlobalScopes()->where('account_id', $account->id)->firstOrFail();

        $conversion = TrackingConversion::create([
            'account_id' => $account->id,
            'tracking_link_id' => $link->id,
            'lead_id' => $lead->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplier->id,
            'conversion_uuid' => 'conv-uuid-test-002',
            'goal' => 'lead',
            'status' => TrackingConversion::STATUS_APPROVED,
            'payout' => 5,
            'revenue' => 10,
            'approved_at' => now(),
        ]);

        app(ConversionTrackingService::class)->fireLinkConversionPostback($conversion->fresh(['trackingLink.supplier']), $lead);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'lead='.$lead->uuid));
    }
}
