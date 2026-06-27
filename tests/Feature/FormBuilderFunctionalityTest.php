<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\Lead;
use App\Models\Source;
use App\Models\Supplier;
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
                ->has('suppliers')
                ->has('embed')
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

    public function test_domain_locked_form_allows_submit_from_same_origin_iframe(): void
    {
        Queue::fake();

        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Iframe locked',
            'slug' => 'iframe-locked-form',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'allowed_domains' => ['trusted.example.com'],
                'steps' => $this->validSteps(),
            ],
        ]);

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        $this->post(route('forms.submit', $form->slug), [
            'firstname' => 'Iframe',
            'email' => 'iframe.'.uniqid().'@example.com',
            'embed' => '1',
        ], [
            'Referer' => 'https://'.$appHost.'/forms/'.$form->slug.'?embed=1',
        ])->assertOk();

        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class);
    }

    public function test_form_submission_resolves_sid_and_supplier_from_query_and_defaults(): void
    {
        Queue::fake();

        $supplier = Supplier::where('account_id', $this->campaign->account_id)->first();
        $source = Source::where('supplier_id', $supplier->id)->first();

        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Tracked embed',
            'slug' => 'tracked-embed-form',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'default_supplier_id' => $supplier->id,
                'steps' => $this->validSteps(),
            ],
        ]);

        $email = 'tracked.'.uniqid().'@example.com';

        $this->get(route('forms.show', ['slug' => $form->slug, 'sid' => $source->sid, 'click_id' => 'clk_123', 'embed' => '1']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('embed', true)
                ->where('tracking.sid', $source->sid)
                ->where('tracking.click_id', 'clk_123')
            );

        $show = $this->get(route('forms.show', ['slug' => $form->slug, 'sid' => $source->sid, 'embed' => '1']));
        $version = $show->headers->get('X-Inertia-Version');

        $this->post(route('forms.submit', $form->slug), [
            'firstname' => 'Tracked',
            'email' => $email,
            'sid' => $source->sid,
            'supplier_id' => (string) $supplier->id,
            'click_id' => 'clk_123',
            'embed' => '1',
        ], [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $lead = Lead::where('source', 'hosted_form:'.$form->slug)->latest()->first();
        $this->assertNotNull($lead);
        $this->assertSame($email, $lead->field_data['email'] ?? null);
        $this->assertSame($supplier->id, $lead->supplier_id);
        $this->assertSame($source->sid, $lead->sid);
        $this->assertSame('clk_123', $lead->metadata['tracking']['click_id'] ?? null);
        $this->assertSame('hosted_form:'.$form->slug, $lead->source);
    }

    public function test_domain_locked_form_sets_frame_ancestors_csp(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'CSP form',
            'slug' => 'csp-form',
            'is_active' => true,
            'config' => [
                'allowed_domains' => ['partner.example.com'],
                'steps' => $this->validSteps(),
                'multi_step' => true,
            ],
        ]);

        $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://partner.example.com");
    }

    public function test_iframe_embed_blocked_when_account_setting_disabled(): void
    {
        $account = $this->campaign->account;
        $settings = $account->settings ?? [];
        $settings['supplier_iframe_embed'] = false;
        $account->update(['settings' => $settings]);

        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'No iframe',
            'slug' => 'no-iframe-form',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'steps' => $this->validSteps(),
            ],
        ]);

        $this->get(route('forms.show', ['slug' => $form->slug, 'embed' => '1']))
            ->assertForbidden();

        $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }

    public function test_iframe_embed_allowed_on_any_site_when_account_setting_enabled(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Open iframe',
            'slug' => 'open-iframe-form',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'steps' => $this->validSteps(),
            ],
        ]);

        $this->get(route('forms.show', ['slug' => $form->slug, 'embed' => '1']))
            ->assertOk();

        $response = $this->get(route('forms.show', $form->slug));
        $response->assertOk();
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
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

    public function test_poll_redirect_mode_returns_submitted_without_immediate_redirect(): void
    {
        Queue::fake();

        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Poll redirect',
            'slug' => 'poll-redirect-form',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'thank_you' => ['mode' => 'poll_redirect'],
                'steps' => $this->validSteps(),
            ],
        ]);

        $this->post(route('forms.submit', $form->slug), [
            'firstname' => 'Poll',
            'email' => 'poll.'.uniqid().'@example.com',
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Forms/Show')
                ->where('submitted', true)
                ->where('thankYou.mode', 'poll_redirect')
                ->has('submission.uuid')
            );
    }

    public function test_form_status_endpoint_returns_redirect_for_sold_lead(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Status poll',
            'slug' => 'status-poll-form',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'thank_you' => ['mode' => 'poll_redirect'],
                'steps' => $this->validSteps(),
            ],
        ]);

        $lead = Lead::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Sold,
            'source' => 'hosted_form:'.$form->slug,
            'field_data' => ['firstname' => 'Sold', 'email' => 'sold@example.com'],
            'redirect_url' => 'https://buyer.example/thanks',
            'received_at' => now(),
        ]);

        $this->getJson(route('forms.status', ['slug' => $form->slug, 'uuid' => $lead->uuid]))
            ->assertOk()
            ->assertJsonPath('status', LeadStatus::Sold->value)
            ->assertJsonPath('terminal', true)
            ->assertJsonPath('redirect_url', url('/r/'.$lead->uuid));
    }

    public function test_form_status_endpoint_rejects_lead_from_other_form(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Status guard',
            'slug' => 'status-guard-form',
            'is_active' => true,
            'config' => ['steps' => $this->validSteps()],
        ]);

        $lead = Lead::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Sold,
            'source' => 'hosted_form:other-form',
            'field_data' => ['firstname' => 'Other', 'email' => 'other@example.com'],
            'received_at' => now(),
        ]);

        $this->getJson(route('forms.status', ['slug' => $form->slug, 'uuid' => $lead->uuid]))
            ->assertNotFound();
    }

    public function test_admin_can_disable_submit_another_button(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'No resubmit',
            'slug' => 'no-resubmit-form',
            'is_active' => true,
            'config' => ['steps' => []],
        ]);

        $this->actingAs($this->admin)
            ->put(route('forms.update', $form), [
                'campaign_id' => $this->campaign->id,
                'name' => 'No resubmit',
                'is_active' => true,
                'config' => [
                    'multi_step' => true,
                    'steps' => $this->validSteps(),
                    'thank_you' => [
                        'mode' => 'inline',
                        'show_submit_another' => false,
                    ],
                ],
            ])
            ->assertRedirect();

        $form->refresh();
        $this->assertFalse($form->config['thank_you']['show_submit_another']);
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
