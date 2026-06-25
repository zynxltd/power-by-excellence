<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Postback;
use App\Models\PostbackLog;
use App\Services\Buyers\BuyerConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BuyerConversionPostbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_funded_feedback_fires_lead_funded_postback(): void
    {
        Http::fake(['https://tracker.test/*' => Http::response('OK', 200)]);

        $account = Account::create([
            'name' => 'Conversion Test',
            'slug' => 'conversion-test',
            'default_currency' => 'EUR',
            'default_country' => 'DE',
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Loans',
            'reference' => 'loans-de',
            'status' => 'active',
            'country' => 'DE',
            'currency' => 'EUR',
            'sell_mode' => 'exclusive',
            'payout_amount' => 5,
            'floor_price' => 0,
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'conv-buyer',
            'name' => 'Conv Buyer',
            'status' => 'active',
            'currency' => 'EUR',
            'credit_balance' => 100,
        ]);

        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'sold_to_buyer_id' => $buyer->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'sold',
            'field_data' => ['email' => 'conv@test.com'],
            'received_at' => now(),
        ]);

        Postback::create([
            'account_id' => $account->id,
            'name' => 'Funded Pixel',
            'url' => 'https://tracker.test/funded?lead=[lead_uuid]',
            'method' => 'get',
            'events' => ['lead.funded'],
            'is_active' => true,
        ]);

        app(BuyerConversionService::class)->recordFeedback($buyer, $lead, 'funded', true);

        $this->assertDatabaseHas('postback_logs', [
            'lead_id' => $lead->id,
            'event' => 'lead.funded',
            'status' => 'success',
        ]);
    }
}
