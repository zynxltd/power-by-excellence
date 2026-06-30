<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\CampaignField;
use App\Models\User;
use App\Models\VerticalFieldTemplate;
use App\Services\VerticalFieldTemplates\VerticalFieldTemplateApplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VerticalFieldTemplateApplyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Route::has('vertical-field-templates.apply-wizard')) {
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth'])->group(function () {
                \Illuminate\Support\Facades\Route::get('vertical-field-templates/apply-wizard', [\App\Http\Controllers\Admin\VerticalFieldTemplateController::class, 'applyWizard'])
                    ->name('vertical-field-templates.apply-wizard');
                \Illuminate\Support\Facades\Route::post('vertical-field-templates/{verticalFieldTemplate}/preview', [\App\Http\Controllers\Admin\VerticalFieldTemplateController::class, 'preview'])
                    ->name('vertical-field-templates.preview');
            });
        }

        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();
        $this->account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $this->campaign = Campaign::where('account_id', $this->account->id)
            ->where('reference', 'auto-insurance-uk')
            ->firstOrFail();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    protected function makeTemplate(array $overrides = []): VerticalFieldTemplate
    {
        return VerticalFieldTemplate::create(array_merge([
            'account_id' => $this->account->id,
            'vertical_id' => 'insurance_auto',
            'name' => 'Auto template',
            'fields' => [
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['name' => 'phone1', 'label' => 'Phone', 'type' => 'tel', 'required' => true],
                ['name' => 'vehicle_year', 'label' => 'Vehicle year', 'type' => 'number', 'required' => true],
            ],
        ], $overrides));
    }

    public function test_apply_wizard_updates_campaign_fields_with_replace_all(): void
    {
        $template = $this->makeTemplate();
        $beforeCount = CampaignField::where('campaign_id', $this->campaign->id)->count();

        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('vertical-field-templates.apply', $template), [
                'campaign_id' => $this->campaign->id,
                'strategy' => VerticalFieldTemplateApplyService::STRATEGY_REPLACE_ALL,
            ])
            ->assertRedirect(route('campaigns.show', $this->campaign));

        $this->assertSame(3, CampaignField::where('campaign_id', $this->campaign->id)->count());
        $this->assertDatabaseHas('campaign_fields', [
            'campaign_id' => $this->campaign->id,
            'name' => 'vehicle_year',
            'label' => 'Vehicle year',
        ]);
        $this->assertNotSame($beforeCount, 3);
    }

    public function test_preview_does_not_persist_campaign_fields(): void
    {
        $template = $this->makeTemplate();
        $before = CampaignField::where('campaign_id', $this->campaign->id)->orderBy('name')->pluck('name')->all();

        $this->ukHost()
            ->actingAs($this->admin)
            ->postJson(route('vertical-field-templates.preview', $template), [
                'campaign_id' => $this->campaign->id,
                'strategy' => VerticalFieldTemplateApplyService::STRATEGY_REPLACE_ALL,
            ])
            ->assertOk()
            ->assertJsonPath('strategy', VerticalFieldTemplateApplyService::STRATEGY_REPLACE_ALL)
            ->assertJsonStructure(['to_add', 'to_replace', 'to_remove']);

        $after = CampaignField::where('campaign_id', $this->campaign->id)->orderBy('name')->pluck('name')->all();
        $this->assertSame($before, $after);
    }

    public function test_wrong_vertical_is_rejected_for_preview_and_apply(): void
    {
        $template = $this->makeTemplate([
            'vertical_id' => 'loans_personal',
            'name' => 'Loans template',
            'fields' => [
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ],
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->postJson(route('vertical-field-templates.preview', $template), [
                'campaign_id' => $this->campaign->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vertical_id');

        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('vertical-field-templates.apply', $template), [
                'campaign_id' => $this->campaign->id,
            ])
            ->assertSessionHasErrors('vertical_id');
    }

    public function test_apply_wizard_page_filters_templates_by_campaign_vertical(): void
    {
        $matching = $this->makeTemplate();
        $this->makeTemplate([
            'vertical_id' => 'loans_personal',
            'name' => 'Loans only',
            'fields' => [['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->get(route('vertical-field-templates.apply-wizard', ['campaign_id' => $this->campaign->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/VerticalFieldTemplates/ApplyWizard')
                ->where('campaign.id', $this->campaign->id)
                ->has('templates', 1)
                ->where('templates.0.id', $matching->id));
    }
}
