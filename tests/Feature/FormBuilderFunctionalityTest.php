<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FormBuilderFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->campaign = Campaign::where('reference', 'loans-uk')->first();
    }

    protected function validSteps(): array
    {
        return [[
            'id' => 'step-1',
            'title' => 'Your details',
            'description' => 'Contact information',
            'fields' => [
                ['name' => 'firstname', 'label' => 'First name', 'type' => 'text', 'required' => true, 'options' => []],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'options' => []],
            ],
        ]];
    }

    public function test_form_builder_index_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('forms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Forms/Index')
                ->has('forms')
                ->has('campaigns')
                ->has('verticals')
                ->has('fieldTypes')
            );
    }

    public function test_admin_can_create_form(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('forms.store'), [
                'campaign_id' => $this->campaign->id,
                'name' => 'Builder Test Form',
                'config' => [
                    'redirect_url' => '',
                    'allowed_domains' => [],
                    'css' => '',
                ],
            ]);

        $form = HostedForm::where('name', 'Builder Test Form')->first();
        $this->assertNotNull($form);
        $this->assertSame($this->campaign->account_id, $form->account_id);
        $this->assertTrue($form->is_active);
        $this->assertNotEmpty($form->slug);

        $response->assertRedirect(route('forms.edit', $form));
    }

    public function test_edit_page_exposes_api_spec_options(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Edit props form',
            'slug' => 'edit-props-form',
            'is_active' => true,
            'config' => ['steps' => $this->validSteps(), 'multi_step' => true],
        ]);

        $this->actingAs($this->admin)
            ->get(route('forms.edit', $form))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Forms/Edit')
                ->has('form')
                ->has('apiSpec')
                ->has('specFieldOptions')
                ->has('campaignForms')
                ->has('fieldTypes')
            );
    }

    public function test_admin_can_update_multi_step_form_config(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Update test',
            'slug' => 'update-test-form',
            'is_active' => true,
            'config' => ['steps' => []],
        ]);

        $this->actingAs($this->admin)
            ->put(route('forms.update', $form), [
                'campaign_id' => $this->campaign->id,
                'name' => 'Updated form name',
                'is_active' => true,
                'config' => [
                    'multi_step' => true,
                    'steps' => $this->validSteps(),
                    'thank_you' => [
                        'mode' => 'inline',
                        'title' => 'Thanks!',
                        'message' => 'Received.',
                        'show_reference' => true,
                        'confetti' => false,
                    ],
                ],
            ])
            ->assertRedirect();

        $form->refresh();
        $this->assertSame('Updated form name', $form->name);
        $this->assertTrue($form->config['multi_step']);
        $this->assertSame('Thanks!', $form->config['thank_you']['title']);
        $this->assertCount(2, $form->config['steps'][0]['fields']);
    }

    public function test_update_rejects_fields_not_in_api_spec(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Invalid fields',
            'slug' => 'invalid-fields-form',
            'is_active' => true,
            'config' => ['steps' => []],
        ]);

        $this->actingAs($this->admin)
            ->put(route('forms.update', $form), [
                'campaign_id' => $this->campaign->id,
                'name' => 'Invalid fields',
                'is_active' => true,
                'config' => [
                    'multi_step' => true,
                    'steps' => [[
                        'id' => 'step-1',
                        'title' => 'Bad',
                        'fields' => [
                            ['name' => 'totally_unknown_field', 'label' => 'Bad', 'type' => 'text', 'required' => false, 'options' => []],
                        ],
                    ]],
                ],
            ])
            ->assertSessionHasErrors('config');
    }

    public function test_admin_can_delete_form(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Delete me',
            'slug' => 'delete-me-form',
            'is_active' => true,
            'config' => ['steps' => $this->validSteps(), 'multi_step' => true],
        ]);

        $this->actingAs($this->admin)
            ->from(route('forms.index'))
            ->delete(route('forms.destroy', $form))
            ->assertRedirect(route('forms.index'));

        $this->assertDatabaseMissing('hosted_forms', ['id' => $form->id]);
    }

    public function test_public_multi_step_form_renders_and_submits(): void
    {
        Queue::fake();

        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Public multi-step',
            'slug' => 'public-multi-step',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'steps' => $this->validSteps(),
            ],
        ]);

        $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Forms/Show')
                ->where('multiStep', true)
                ->has('steps', 1)
                ->where('steps.0.fields.0.name', 'firstname')
            );

        $show = $this->get(route('forms.show', $form->slug));
        $version = $show->headers->get('X-Inertia-Version');

        $this->post(route('forms.submit', $form->slug), [
            'firstname' => 'Form',
            'email' => 'form.builder.'.uniqid().'@example.com',
        ], [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('props.submitted', true);

        $this->assertDatabaseHas('leads', [
            'campaign_id' => $this->campaign->id,
            'source' => 'hosted_form:'.$form->slug,
        ]);

        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class);
    }

    public function test_public_form_falls_back_to_campaign_fields_without_steps(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Campaign fields fallback',
            'slug' => 'campaign-fields-fallback',
            'is_active' => true,
            'config' => ['multi_step' => false],
        ]);

        $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('multiStep', false)
                ->has('steps', 1)
                ->where('steps.0.id', 'single')
            );
    }

    public function test_inactive_form_is_not_publicly_accessible(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Inactive',
            'slug' => 'inactive-form',
            'is_active' => false,
            'config' => ['steps' => $this->validSteps(), 'multi_step' => true],
        ]);

        $this->get(route('forms.show', $form->slug))->assertNotFound();
    }

    public function test_allowed_domains_blocks_unauthorized_referer(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Domain locked',
            'slug' => 'domain-locked-form',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'allowed_domains' => ['trusted.example.com'],
                'steps' => $this->validSteps(),
            ],
        ]);

        $this->post(route('forms.submit', $form->slug), [
            'firstname' => 'Blocked',
            'email' => 'blocked@example.com',
        ], [
            'Referer' => 'https://evil.example.com/page',
        ])->assertForbidden();
    }

    public function test_redirect_thank_you_mode_redirects_away(): void
    {
        Queue::fake();

        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Redirect thanks',
            'slug' => 'redirect-thanks-form',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'redirect_url' => 'https://example.com/thanks',
                'thank_you' => ['mode' => 'redirect'],
                'steps' => $this->validSteps(),
            ],
        ]);

        $this->post(route('forms.submit', $form->slug), [
            'firstname' => 'Redirect',
            'email' => 'redirect.'.uniqid().'@example.com',
        ])->assertRedirect('https://example.com/thanks');
    }

    public function test_tenant_admin_cannot_create_form_for_other_tenant_campaign(): void
    {
        $otherCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($this->admin)
            ->post(route('forms.store'), [
                'campaign_id' => $otherCampaign->id,
                'name' => 'Cross-tenant form',
                'config' => ['redirect_url' => '', 'allowed_domains' => [], 'css' => ''],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('hosted_forms', ['name' => 'Cross-tenant form']);
    }

    public function test_tenant_admin_cannot_edit_other_tenant_form(): void
    {
        $otherCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();

        $form = HostedForm::withoutGlobalScopes()->create([
            'account_id' => $otherCampaign->account_id,
            'campaign_id' => $otherCampaign->id,
            'name' => 'Foreign form',
            'slug' => 'foreign-builder-form',
            'is_active' => true,
            'config' => ['steps' => []],
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($this->admin)
            ->get(route('forms.edit', $form))
            ->assertNotFound();
    }
}
