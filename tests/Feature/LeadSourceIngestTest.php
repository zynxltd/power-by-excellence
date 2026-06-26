<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadSourceIngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_ingest_creates_lead(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $account = Account::where('slug', 'excellence-uk')->first();
        $campaign = Campaign::where('account_id', $account->id)->first();

        $settings = $account->settings ?? [];
        $settings['lead_sources']['google'] = [
            'enabled' => true,
            'campaign_id' => $campaign->id,
        ];
        $account->update(['settings' => $settings]);

        $response = $this->postJson('/api/v1/integrations/google/ingest/'.$account->slug, [
            'email' => 'google.lead@example.com',
            'firstname' => 'Test',
            'lastname' => 'Lead',
            'phone1' => '07700900444',
            'zipcode' => 'SW1A 1AA',
        ]);

        if ($response->status() !== 202) {
            $this->fail('Status '.$response->status().': '.$response->getContent());
        }

        $response->assertJsonPath('accepted', true);
        $this->assertDatabaseHas('leads', [
            'campaign_id' => $campaign->id,
        ]);
        $this->assertSame(1, Lead::where('campaign_id', $campaign->id)->where('source', 'google')->count());
    }

    public function test_facebook_webhook_verification(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $account = Account::where('slug', 'excellence-uk')->first();
        $settings = $account->settings ?? [];
        $settings['lead_sources']['facebook'] = [
            'enabled' => true,
            'verify_token' => 'fb-test-token',
            'campaign_id' => Campaign::where('account_id', $account->id)->value('id'),
        ];
        $account->update(['settings' => $settings]);

        $this->get('/api/v1/integrations/facebook/webhook/'.$account->slug.'?hub.mode=subscribe&hub.challenge=CHAL123&hub.verify_token=fb-test-token')
            ->assertOk()
            ->assertSee('CHAL123');
    }
}
