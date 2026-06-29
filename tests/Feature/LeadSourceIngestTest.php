<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Leads\LeadIngestService;
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

        $this->postJson('/api/v1/integrations/google/ingest/'.$account->slug, [
            'email' => 'google.lead@example.com',
            'firstname' => 'Test',
            'lastname' => 'Lead',
            'phone1' => '07700900444',
            'zipcode' => 'SW1A 1AA',
        ])->assertStatus(202)->assertJsonPath('accepted', true);

        $this->assertSame(1, Lead::where('campaign_id', $campaign->id)->where('source', 'google')->count());
    }

    public function test_google_ingest_applies_saved_field_mapping(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $account = Account::where('slug', 'excellence-uk')->first();
        $campaign = Campaign::where('account_id', $account->id)->first();

        $settings = $account->settings ?? [];
        $settings['lead_sources']['google'] = [
            'enabled' => true,
            'campaign_id' => $campaign->id,
            'field_mapping' => [
                'email_address' => 'email',
                'given_name' => 'firstname',
                'family_name' => 'lastname',
                'mobile' => 'phone1',
            ],
        ];
        $account->update(['settings' => $settings]);

        $this->postJson('/api/v1/integrations/google/ingest/'.$account->slug, [
            'email_address' => 'mapped.lead@example.com',
            'given_name' => 'Mapped',
            'family_name' => 'Lead',
            'mobile' => '07700900555',
        ])->assertStatus(202);

        $lead = Lead::where('campaign_id', $campaign->id)->where('source', 'google')->latest('id')->first();
        $this->assertSame('mapped.lead@example.com', $lead->getField('email'));
        $this->assertSame('Mapped', $lead->getField('firstname'));
        $this->assertSame('Lead', $lead->getField('lastname'));
        $this->assertSame('07700900555', $lead->getField('phone1'));
    }

    public function test_google_user_column_data_ingest_with_mapping(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $account = Account::where('slug', 'excellence-uk')->first();
        $campaign = Campaign::where('account_id', $account->id)->first();

        $settings = $account->settings ?? [];
        $settings['lead_sources']['google'] = [
            'enabled' => true,
            'campaign_id' => $campaign->id,
            'field_mapping' => [
                'email' => 'email',
                'first_name' => 'firstname',
            ],
        ];
        $account->update(['settings' => $settings]);

        $this->postJson('/api/v1/integrations/google/ingest/'.$account->slug, [
            'user_column_data' => [
                ['column_id' => 'EMAIL', 'string_value' => 'column.lead@example.com'],
                ['column_id' => 'FIRST_NAME', 'string_value' => 'Column'],
            ],
        ])->assertStatus(202);

        $lead = Lead::where('campaign_id', $campaign->id)->where('source', 'google')->latest('id')->first();
        $this->assertSame('column.lead@example.com', $lead->getField('email'));
        $this->assertSame('Column', $lead->getField('firstname'));
    }

    public function test_lead_ingest_service_applies_case_insensitive_mapping(): void
    {
        $service = app(LeadIngestService::class);

        $mapped = $service->applyLeadSourceFieldMapping(
            ['EMAIL_ADDRESS' => 'a@example.com', 'phone' => '123'],
            ['email_address' => 'email', 'phone' => 'phone1'],
        );

        $this->assertSame('a@example.com', $mapped['email']);
        $this->assertSame('123', $mapped['phone1']);
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

    public function test_ingest_rejects_when_integration_disabled(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $account = Account::where('slug', 'excellence-uk')->first();

        $this->postJson('/api/v1/integrations/google/ingest/'.$account->slug, [
            'email' => 'disabled@example.com',
        ])->assertStatus(403);
    }

    public function test_admin_update_rejects_duplicate_source_mapping_keys(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $admin = \App\Models\User::where('email', 'uk@powerbyexcellence.test')->first();
        $campaign = Campaign::where('account_id', $admin->account_id)->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->from(route('integrations.lead-source', 'google'))
            ->put(route('integrations.lead-source.update', 'google'), [
                'enabled' => true,
                'campaign_id' => $campaign->id,
                'field_mapping' => [
                    ['source' => 'email_address', 'target' => 'email'],
                    ['source' => 'EMAIL_ADDRESS', 'target' => 'firstname'],
                ],
            ])
            ->assertSessionHasErrors('field_mapping');
    }
}
