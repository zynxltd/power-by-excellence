<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignField;
use App\Models\HostedForm;
use App\Models\User;
use App\Services\Caps\CapService;
use App\Services\Buyers\BuyerEligibilityService;
use App\Support\CampaignRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignFunctionalityTest extends TestCase
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

    public function test_campaign_index_and_edit_pages_load(): void
    {
        $campaign = Campaign::where('account_id', $this->admin->account_id)->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($this->admin)
            ->get(route('campaigns.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Campaigns/Index')->has('campaigns'));

        $this->actingAs($this->admin)
            ->get(route('campaigns.edit', $campaign))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Campaigns/Form')
                ->has('campaignWorkflow')
                ->has('tenantHub')
            );
    }

    public function test_campaign_store_with_vertical_and_spend_caps(): void
    {
        $response = $this->actingAs($this->admin)->post(route('campaigns.store'), [
            'name' => 'Solar Test Campaign',
            'reference' => 'solar-test-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'vertical_id' => 'solar',
            'payout_amount' => 6,
            'floor_price' => 12,
            'sell_mode' => 'exclusive',
            'bidding_mode' => 'waterfall',
            'caps' => [
                'daily' => 100,
                'daily_spend_cap' => 500,
                'monthly_spend_cap' => 10000,
            ],
        ]);

        $campaign = Campaign::where('reference', 'solar-test-campaign')->first();
        $response->assertRedirect(route('campaigns.show', $campaign));

        $this->assertSame('solar', $campaign->vertical_id);
        $this->assertSame(500.0, (float) $campaign->caps['daily_spend_cap']);
        $this->assertGreaterThan(0, CampaignField::where('campaign_id', $campaign->id)->count());
    }

    public function test_campaign_validation_rules_can_be_updated_via_patch(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $this->actingAs($this->admin)
            ->patch(route('campaigns.validation', $campaign), [
                'validation_config' => [
                    'require_email' => true,
                    'require_phone' => true,
                    'block_disposable_email' => true,
                ],
                'dedupe_config' => [
                    'fields' => ['email', 'phone1'],
                    'reject_days' => 30,
                ],
            ])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertTrue($campaign->validation_config['require_email'] ?? false);
        $this->assertTrue($campaign->validation_config['block_disposable_email'] ?? false);
        $this->assertSame(['email', 'phone1'], $campaign->dedupe_config['fields'] ?? null);
        $this->assertSame(30, $campaign->dedupe_config['reject_days'] ?? null);
    }

    public function test_api_spec_vertical_and_premade_templates_load(): void
    {
        $campaign = Campaign::where('reference', 'mortgage-uk')->first();

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.load-template', $campaign), [
                'vertical_id' => 'solar',
            ])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame('solar', $campaign->vertical_id);
        $this->assertNotEmpty($campaign->api_spec['fields'] ?? []);

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.load-premade', $campaign), [
                'template_key' => 'payday_ping',
            ])
            ->assertRedirect();

        $campaign->refresh();
        $fieldNames = collect($campaign->api_spec['fields'] ?? [])->pluck('name');
        $this->assertTrue($fieldNames->contains('loan_amount'));
    }

    public function test_api_spec_apply_to_form_syncs_hosted_form_steps(): void
    {
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();

        $campaign->update([
            'api_spec' => [
                'description' => 'Test',
                'fields' => [
                    ['name' => 'firstname', 'label' => 'First', 'type' => 'string', 'required' => true, 'form_type' => 'text'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'form_type' => 'email'],
                ],
            ],
        ]);

        $form = HostedForm::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'name' => 'API Spec Form',
            'slug' => 'api-spec-form-test',
            'is_active' => true,
            'config' => ['steps' => []],
        ]);

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.apply-form', $campaign), [
                'hosted_form_id' => $form->id,
            ])
            ->assertRedirect(route('forms.edit', $form));

        $form->refresh();
        $this->assertTrue($form->config['imported_from_api_spec'] ?? false);
        $this->assertNotEmpty($form->config['steps'] ?? []);
    }

    public function test_campaign_show_includes_workflow_and_leads_today(): void
    {
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();

        $this->actingAs($this->admin)
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Campaigns/Show')
                ->has('campaignWorkflow')
                ->has('tenantHub')
                ->has('leadsToday')
                ->has('deliveries')
            );
    }

    public function test_tenant_admin_cannot_access_other_tenant_campaign(): void
    {
        $caCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($this->admin)
            ->get('/campaigns/'.$caCampaign->id)
            ->assertNotFound();

        // Central-host URL must also scope to the signed-in tenant admin's account.
        $this->actingAs($this->admin)
            ->get('/campaigns/'.$caCampaign->id)
            ->assertNotFound();
    }

    public function test_campaign_monthly_spend_cap_persists_on_update(): void
    {
        $campaign = Campaign::create([
            'account_id' => $this->admin->account_id,
            'name' => 'Cap Update Test',
            'reference' => 'cap-update-test',
            'country' => 'GB',
            'currency' => 'GBP',
            'payout_amount' => 5,
            'floor_price' => 10,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)->put(route('campaigns.update', $campaign), [
            'name' => 'Cap Update Test',
            'reference' => 'cap-update-test',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
            'caps' => ['monthly_spend_cap' => 2500],
        ])->assertRedirect();

        $this->assertSame(2500.0, (float) $campaign->fresh()->caps['monthly_spend_cap']);
    }

    public function test_campaign_volume_cap_blocks_delivery(): void
    {
        $campaign = Campaign::where('account_id', $this->admin->account_id)->first();
        $delivery = $campaign->deliveries()->first();
        $campaign->update(['caps' => ['daily' => 1]]);
        app(CapService::class)->increment('campaign', $campaign->id, $campaign->caps);

        $lead = \App\Models\Lead::where('campaign_id', $campaign->id)->first();
        $service = app(BuyerEligibilityService::class);

        $this->assertFalse($service->canDeliver($lead->load('campaign'), $delivery->fresh(['buyer'])));
    }

    public function test_campaign_logo_upload_and_region_on_index(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('campaigns.store'), [
            'name' => 'Logo Campaign',
            'reference' => 'logo-campaign',
            'country' => 'US',
            'currency' => 'USD',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
            'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
        ])->assertRedirect();

        $campaign = Campaign::where('reference', 'logo-campaign')->first();
        $this->assertNotNull($campaign->logo_path);
        Storage::disk('public')->assertExists($campaign->logo_path);

        $region = CampaignRegion::forCampaign($campaign);
        $this->assertFalse($region['is_multi']);
        $this->assertSame('US', $region['code']);
        $this->assertSame('🇺🇸', $region['emoji']);

        $this->actingAs($this->admin)
            ->get(route('campaigns.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Campaigns/Index')
                ->where('campaigns.data', function ($rows) use ($campaign) {
                    $row = collect($rows)->firstWhere('reference', 'logo-campaign');

                    return $row
                        && $row['logo_url'] === Storage::disk('public')->url($campaign->logo_path)
                        && ($row['region']['code'] ?? null) === 'US';
                })
            );
    }

    public function test_campaign_multi_geo_uses_world_flag(): void
    {
        $campaign = Campaign::create([
            'account_id' => $this->admin->account_id,
            'name' => 'Global Campaign',
            'reference' => 'global-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'payout_amount' => 5,
            'floor_price' => 10,
            'status' => 'active',
            'multi_geo' => true,
            'geo_countries' => ['US', 'CA'],
        ]);

        $region = CampaignRegion::forCampaign($campaign);
        $this->assertTrue($region['is_multi']);
        $this->assertSame('world', $region['type']);
        $this->assertSame('🌍', $region['emoji']);

        $this->actingAs($this->admin)->put(route('campaigns.update', $campaign), [
            'name' => 'Global Campaign',
            'reference' => 'global-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
            'multi_geo' => true,
            'geo_countries' => ['US', 'AU'],
        ])->assertRedirect();

        $campaign->refresh();
        $this->assertSame(['US', 'AU'], $campaign->geo_countries);
    }
}
