<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\User;
use App\Services\Api\CampaignApiSpecService;
use App\Support\Campaign\CampaignFieldCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiSpecFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected CampaignApiSpecService $specService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->specService = app(CampaignApiSpecService::class);
    }

    public function test_api_spec_page_exposes_documentation_props(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $this->actingAs($this->admin)
            ->get(route('campaigns.api-spec', $campaign))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Campaigns/ApiSpec')
                ->has('spec')
                ->has('sampleRequest')
                ->has('sampleResponse')
                ->has('sampleStatusResponse')
                ->has('curl')
                ->has('fieldTypes')
                ->has('formTypes')
                ->has('verticals')
                ->has('premadeTemplates')
                ->has('campaignWorkflow')
                ->has('tenantHub')
                ->where('apiBaseUrl', fn ($url) => str_contains($url, 'excellence-uk.powerbyexcellence.test/api/v1'))
                ->where('sampleRequest.campaign_reference', $campaign->reference)
                ->where('sampleResponse.status', 'queued')
            );
    }

    public function test_update_rejects_invalid_field_names(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $this->actingAs($this->admin)
            ->put(route('campaigns.api-spec.update', $campaign), [
                'spec' => [
                    'description' => 'Bad names',
                    'fields' => [
                        [
                            'name' => 'Invalid-Name',
                            'label' => 'Bad',
                            'type' => 'string',
                            'required' => true,
                        ],
                    ],
                ],
                'sync_fields' => false,
            ])
            ->assertSessionHasErrors('spec.fields.0.name');
    }

    public function test_update_without_sync_fields_does_not_touch_campaign_fields(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $before = $campaign->fields()->count();

        $this->actingAs($this->admin)
            ->put(route('campaigns.api-spec.update', $campaign), [
                'spec' => [
                    'description' => 'No sync',
                    'fields' => [
                        [
                            'name' => 'brand_new_field',
                            'label' => 'Brand new',
                            'type' => 'string',
                            'required' => false,
                            'ping_field' => false,
                            'form_type' => 'text',
                        ],
                    ],
                ],
                'sync_fields' => false,
            ])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame('No sync', $campaign->api_spec['description']);
        $this->assertSame($before, $campaign->fields()->count());
        $this->assertDatabaseMissing('campaign_fields', [
            'campaign_id' => $campaign->id,
            'name' => 'brand_new_field',
        ]);
    }

    public function test_sync_fields_updates_existing_campaign_field(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $field = $campaign->fields()->where('name', 'email')->first();
        $this->assertNotNull($field);

        $this->actingAs($this->admin)
            ->put(route('campaigns.api-spec.update', $campaign), [
                'spec' => [
                    'description' => 'Synced',
                    'fields' => [
                        [
                            'name' => 'email',
                            'label' => 'Email address',
                            'type' => 'email',
                            'required' => true,
                            'ping_field' => true,
                            'form_type' => 'email',
                        ],
                    ],
                ],
                'sync_fields' => true,
            ])
            ->assertRedirect();

        $field->refresh();
        $this->assertSame('Email address', $field->label);
        $this->assertTrue($field->ping_field);
    }

    public function test_unknown_premade_template_returns_422(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.load-premade', $campaign), [
                'template_key' => 'does_not_exist',
            ])
            ->assertStatus(422);
    }

    public function test_apply_to_form_rejects_form_from_other_campaign(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $other = Campaign::where('account_id', $campaign->account_id)
            ->where('id', '!=', $campaign->id)
            ->first();

        $foreignForm = HostedForm::create([
            'account_id' => $other->account_id,
            'campaign_id' => $other->id,
            'name' => 'Foreign form',
            'slug' => 'foreign-api-spec-form',
            'is_active' => true,
            'config' => ['steps' => []],
        ]);

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.apply-form', $campaign), [
                'hosted_form_id' => $foreignForm->id,
            ])
            ->assertStatus(422);
    }

    public function test_premade_template_generates_multi_step_form(): void
    {
        $campaign = Campaign::where('reference', 'mortgage-uk')->first();

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.load-premade', $campaign), [
                'template_key' => 'solar_full',
            ])
            ->assertRedirect();

        $campaign->refresh();
        $form = HostedForm::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'name' => 'Solar multi-step',
            'slug' => 'solar-multi-step-form',
            'is_active' => true,
            'config' => ['steps' => []],
        ]);

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.apply-form', $campaign), [
                'hosted_form_id' => $form->id,
            ])
            ->assertRedirect(route('forms.edit', $form));

        $form->refresh();
        $this->assertTrue($form->config['multi_step'] ?? false);
        $this->assertGreaterThan(1, count($form->config['steps'] ?? []));
    }

    public function test_tenant_admin_cannot_access_other_tenant_api_spec(): void
    {
        $otherCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($this->admin)
            ->get(route('campaigns.api-spec', $otherCampaign))
            ->assertNotFound();
    }

    public function test_default_spec_falls_back_to_campaign_fields(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $campaign->update(['api_spec' => null]);

        $spec = $this->specService->defaultSpec($campaign->fresh(['fields']));

        $this->assertNotEmpty($spec['fields']);
        $this->assertSame('POST', $spec['endpoint']['method']);
        $this->assertSame('/api/v1/leads', $spec['endpoint']['path']);
        $this->assertContains(
            'email',
            collect($spec['fields'])->pluck('name')->all()
        );
    }

    public function test_spec_to_form_steps_splits_large_schemas(): void
    {
        $campaign = Campaign::where('reference', 'mortgage-uk')->first();

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.load-premade', $campaign), [
                'template_key' => 'solar_full',
            ])
            ->assertRedirect();

        $steps = $this->specService->specToFormSteps(
            $this->specService->defaultSpec($campaign->fresh())
        );

        $this->assertGreaterThan(1, count($steps));
        $this->assertSame('step-contact', $steps[0]['id']);
    }

    public function test_campaign_field_catalog_merges_api_spec_fields(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $campaign->update([
            'api_spec' => [
                'fields' => [
                    [
                        'name' => 'api_only_field',
                        'label' => 'API only',
                        'type' => 'string',
                    ],
                ],
            ],
        ]);

        $names = collect(CampaignFieldCatalog::forCampaign($campaign->fresh()))->pluck('name');

        $this->assertTrue($names->contains('api_only_field'));
        $this->assertTrue($names->contains('email'));
    }

    public function test_sample_request_uses_saved_examples(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $campaign->update([
            'api_spec' => [
                'fields' => [
                    [
                        'name' => 'loan_amount',
                        'label' => 'Loan amount',
                        'type' => 'number',
                        'example' => '25000',
                    ],
                ],
            ],
        ]);

        $sample = $this->specService->sampleRequest($campaign->fresh(), $campaign->api_spec);

        $this->assertSame('25000', $sample['loan_amount']);
        $this->assertSame($campaign->reference, $sample['campaign_reference']);
    }

    public function test_lock_toggle_persists_locked_state(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.lock', $campaign), ['locked' => true])
            ->assertRedirect()
            ->assertSessionHas('success');

        $campaign->refresh();
        $this->assertTrue($this->specService->isLocked($campaign));

        $this->actingAs($this->admin)
            ->get(route('campaigns.api-spec', $campaign))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('spec.locked', true));

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.lock', $campaign), ['locked' => false])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertFalse($this->specService->isLocked($campaign));
    }

    public function test_update_rejected_when_api_spec_is_locked(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $campaign->update(['api_spec' => array_merge(
            $this->specService->defaultSpec($campaign),
            ['locked' => true],
        )]);

        $this->actingAs($this->admin)
            ->put(route('campaigns.api-spec.update', $campaign), [
                'spec' => [
                    'description' => 'Should not save',
                    'fields' => [
                        [
                            'name' => 'firstname',
                            'label' => 'First name',
                            'type' => 'string',
                            'required' => true,
                            'ping_field' => false,
                            'form_type' => 'text',
                        ],
                    ],
                ],
                'sync_fields' => false,
            ])
            ->assertStatus(422);

        $campaign->refresh();
        $this->assertNotSame('Should not save', $campaign->api_spec['description'] ?? null);
    }

    public function test_load_premade_template_rejected_when_locked(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $campaign->update(['api_spec' => array_merge(
            $this->specService->defaultSpec($campaign),
            ['locked' => true],
        )]);

        $this->actingAs($this->admin)
            ->post(route('campaigns.api-spec.load-premade', $campaign), [
                'template_key' => 'solar_full',
            ])
            ->assertStatus(422);
    }
}
