<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignApiSpecTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_admin_can_view_and_save_api_spec(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();

        $this->actingAs($admin)->get(route('campaigns.api-spec', $campaign))->assertOk();

        $payload = [
            'spec' => [
                'description' => 'Test spec',
                'fields' => [
                    [
                        'name' => 'firstname',
                        'label' => 'First Name',
                        'type' => 'string',
                        'required' => true,
                        'ping_field' => false,
                        'example' => 'Jane',
                        'form_type' => 'text',
                    ],
                    [
                        'name' => 'email',
                        'label' => 'Email',
                        'type' => 'email',
                        'required' => true,
                        'ping_field' => false,
                        'example' => 'jane@example.com',
                        'form_type' => 'email',
                    ],
                ],
            ],
            'sync_fields' => true,
        ];

        $this->actingAs($admin)
            ->put(route('campaigns.api-spec.update', $campaign), $payload)
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame('Test spec', $campaign->api_spec['description']);
        $this->assertDatabaseHas('campaign_fields', ['campaign_id' => $campaign->id, 'name' => 'email']);
    }

    public function test_public_form_shows_thank_you_after_submit(): void
    {
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();
        $form = HostedForm::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'name' => 'Test Thank You Form',
            'slug' => 'test-thank-you-form',
            'is_active' => true,
            'config' => [
                'multi_step' => false,
                'steps' => [[
                    'id' => 'step-1',
                    'title' => 'Test',
                    'fields' => [
                        ['name' => 'firstname', 'label' => 'Name', 'type' => 'text', 'required' => true, 'options' => []],
                        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'options' => []],
                    ],
                ]],
                'thank_you' => [
                    'mode' => 'inline',
                    'title' => 'Thanks!',
                    'message' => 'We got it.',
                    'show_reference' => true,
                    'confetti' => false,
                ],
            ],
        ]);

        $show = $this->from(route('forms.show', $form->slug))
            ->get(route('forms.show', $form->slug));
        $version = $show->headers->get('X-Inertia-Version');

        $response = $this->post(route('forms.submit', $form->slug), [
            'firstname' => 'Test',
            'email' => 'test-thank-you@example.com',
        ], [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('component', 'Forms/Show');
        $response->assertJsonPath('props.submitted', true);
        $this->assertNotEmpty($response->json('props.submission.queue_id'));

        $this->assertDatabaseHas('leads', [
            'campaign_id' => $campaign->id,
        ]);
    }
}
