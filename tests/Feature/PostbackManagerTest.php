<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Postback;
use App\Models\PostbackLog;
use App\Models\User;
use App\Services\Postbacks\PostbackDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostbackManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_admin_postbacks_page_loads(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->actingAs($admin)
            ->get('/postbacks')
            ->assertOk();
    }

    public function test_postback_fires_on_lead_sold_event(): void
    {
        Http::fake(['https://tracker.test/*' => Http::response('OK', 200)]);

        $account = Account::first();
        $campaign = Campaign::where('account_id', $account->id)->first();
        $lead = Lead::where('campaign_id', $campaign->id)->first();

        Postback::create([
            'account_id' => $account->id,
            'name' => 'Test Pixel',
            'url' => 'https://tracker.test/pixel?lead=[lead_uuid]',
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => true,
        ]);

        app(PostbackDispatcher::class)->dispatch($lead, 'lead.sold');

        $this->assertDatabaseHas('postback_logs', [
            'lead_id' => $lead->id,
            'event' => 'lead.sold',
            'status' => 'success',
            'http_status' => 200,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'tracker.test/pixel'));
    }

    public function test_postback_respects_supplier_scope(): void
    {
        Http::fake();

        $account = Account::first();
        $lead = Lead::where('account_id', $account->id)->whereNotNull('supplier_id')->first()
            ?? Lead::where('account_id', $account->id)->first();

        $wrongSupplier = \App\Models\Supplier::where('account_id', $account->id)
            ->where('id', '!=', $lead->supplier_id)
            ->first();

        if (! $wrongSupplier) {
            $wrongSupplier = \App\Models\Supplier::create([
                'account_id' => $account->id,
                'reference' => 'other-supplier',
                'name' => 'Other Supplier',
                'status' => 'active',
            ]);
        }

        Postback::create([
            'account_id' => $account->id,
            'supplier_id' => $wrongSupplier->id,
            'name' => 'Wrong Supplier',
            'url' => 'https://tracker.test/wrong',
            'method' => 'get',
            'events' => ['lead.accepted'],
            'is_active' => true,
        ]);

        Postback::create([
            'account_id' => $account->id,
            'supplier_id' => $lead->supplier_id,
            'name' => 'Correct Supplier',
            'url' => 'https://tracker.test/correct',
            'method' => 'get',
            'events' => ['lead.accepted'],
            'is_active' => true,
        ]);

        app(PostbackDispatcher::class)->dispatch($lead, 'lead.accepted');

        $this->assertDatabaseCount('postback_logs', 1);
        $this->assertDatabaseHas('postback_logs', ['event' => 'lead.accepted', 'status' => 'success']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'tracker.test/correct'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'tracker.test/wrong'));
    }

    public function test_admin_can_create_postback(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->actingAs($admin)->post('/postbacks', [
            'name' => 'Affiliate Pixel',
            'url' => 'https://aff.example.com/pb?sid=[sid]',
            'method' => 'get',
            'events' => ['lead.sold', 'lead.accepted'],
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('postbacks', ['name' => 'Affiliate Pixel']);
    }
}
