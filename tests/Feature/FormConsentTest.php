<?php

namespace Tests\Feature;

use App\Enums\LawfulBasis;
use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\Lead;
use App\Models\User;
use App\Services\Compliance\FormConsentPolicy;
use App\Services\Leads\LeadValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FormConsentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        Queue::fake();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->campaign = Campaign::where('reference', 'loans-uk')->first();
    }

    protected function validSteps(): array
    {
        return [[
            'id' => 'step-1',
            'title' => 'Your details',
            'description' => '',
            'fields' => [
                ['name' => 'firstname', 'label' => 'First name', 'type' => 'text', 'required' => true, 'options' => []],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'options' => []],
            ],
        ]];
    }

    protected function makeConsentForm(array $consentOverrides = []): HostedForm
    {
        $this->campaign->update([
            'validation_config' => array_merge($this->campaign->validation_config ?? [], [
                'require_consent' => true,
                'consent_text' => 'I agree to be contacted about my enquiry.',
                'lawful_basis' => LawfulBasis::Consent->value,
                'channel_consent_channels' => ['email', 'sms'],
            ]),
        ]);

        return HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Consent Test Form',
            'slug' => 'consent-test-'.uniqid(),
            'is_active' => true,
            'config' => [
                'multi_step' => false,
                'steps' => $this->validSteps(),
                'consent' => FormConsentPolicy::normalize(array_merge([
                    'require_consent' => true,
                    'consent_text' => 'I agree to be contacted about my enquiry.',
                    'lawful_basis' => LawfulBasis::Consent->value,
                    'channel_consent_channels' => ['email', 'sms'],
                ], $consentOverrides)),
            ],
        ]);
    }

    public function test_form_rejects_without_consent_when_required(): void
    {
        $form = $this->makeConsentForm();

        $this->post(route('forms.submit', $form->slug), [
            'firstname' => 'Consent',
            'email' => 'consent.reject@example.com',
            'consent_accepted' => false,
        ])->assertSessionHasErrors('consent_accepted');

        $this->assertDatabaseMissing('leads', [
            'campaign_id' => $this->campaign->id,
            'source' => 'hosted_form:'.$form->slug,
        ]);
    }

    public function test_consent_fields_stored_on_submit(): void
    {
        $form = $this->makeConsentForm();

        $this->post(route('forms.submit', $form->slug), [
            'firstname' => 'Consent',
            'email' => 'consent.ok@example.com',
            'consent_accepted' => true,
            'channel_consent' => ['email' => true, 'sms' => false],
        ])->assertSessionHasNoErrors();

        $lead = Lead::where('source', 'hosted_form:'.$form->slug)->first();
        $this->assertNotNull($lead);

        $artifact = $lead->consentArtifact();
        $this->assertNotNull($artifact);
        $this->assertTrue($artifact['accepted']);
        $this->assertSame('I agree to be contacted about my enquiry.', $artifact['consent_text']);
        $this->assertSame(LawfulBasis::Consent->value, $artifact['lawful_basis']);
        $this->assertTrue($artifact['channel_consent']['email']);
        $this->assertFalse($artifact['channel_consent']['sms']);
        $this->assertSame('I agree to be contacted about my enquiry.', $lead->getField('consent_text'));
        $this->assertNotNull($artifact['optin_url']);
        $this->assertNotNull($artifact['ip_address']);
        $this->assertNotNull($artifact['user_agent']);
    }

    public function test_disabled_consent_allows_submit_without_checkbox(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Open Form',
            'slug' => 'open-form-'.uniqid(),
            'is_active' => true,
            'config' => [
                'multi_step' => false,
                'steps' => $this->validSteps(),
                'consent' => FormConsentPolicy::normalize([
                    'require_consent' => false,
                ]),
            ],
        ]);

        $this->post(route('forms.submit', $form->slug), [
            'firstname' => 'Open',
            'email' => 'open@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'campaign_id' => $this->campaign->id,
            'source' => 'hosted_form:'.$form->slug,
        ]);
    }

    public function test_lawful_basis_validated_on_form_save(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'name' => 'Invalid Basis Form',
            'slug' => 'invalid-basis-'.uniqid(),
            'is_active' => true,
            'config' => [
                'steps' => $this->validSteps(),
            ],
        ]);

        $this->actingAs($this->admin)
            ->put(route('forms.update', $form), [
                'campaign_id' => $this->campaign->id,
                'name' => $form->name,
                'is_active' => true,
                'config' => [
                    'multi_step' => false,
                    'steps' => $this->validSteps(),
                    'consent' => [
                        'require_consent' => true,
                        'consent_text' => 'Consent copy',
                        'lawful_basis' => 'not_a_real_basis',
                    ],
                ],
            ])
            ->assertSessionHasErrors('config.consent.lawful_basis');
    }

    public function test_lead_validator_rejects_missing_consent_for_campaign(): void
    {
        $campaign = Campaign::withoutGlobalScopes()->with('fields')->where('reference', 'loans-uk')->first();
        $this->assertNotNull($campaign);

        $campaign->update([
            'validation_config' => array_merge($campaign->validation_config ?? [], [
                'require_consent' => true,
                'consent_text' => 'Required consent',
                'lawful_basis' => LawfulBasis::Consent->value,
            ]),
        ]);

        $lead = new Lead([
            'campaign_id' => $campaign->id,
            'field_data' => $this->validFieldData($campaign),
        ]);

        $reason = app(LeadValidator::class)->validate($lead, $campaign->fresh());
        $this->assertSame('Consent is required', $reason);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    protected function validFieldData(Campaign $campaign, array $overrides = []): array
    {
        $fields = [];
        foreach ($campaign->fields as $field) {
            $fields[$field->name] = match ($field->name) {
                'email' => 'valid@example.com',
                'phone1' => '07700900123',
                'zipcode' => 'SW1A 1AA',
                'vehicle_year', 'loan_amount', 'monthly_income' => '10000',
                default => 'Test',
            };
        }

        return array_merge($fields, $overrides);
    }

    public function test_form_consent_policy_normalizes_lawful_basis(): void
    {
        $normalized = FormConsentPolicy::normalize([
            'lawful_basis' => 'invalid',
            'channel_consent_channels' => ['email', 'sms', 'invalid'],
        ]);

        $this->assertSame(LawfulBasis::Consent->value, $normalized['lawful_basis']);
        $this->assertSame(['email', 'sms'], $normalized['channel_consent_channels']);
    }
}
