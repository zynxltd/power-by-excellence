<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\PlatformNotification;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Forms\SupplierHostedFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierFormApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected Supplier $supplier;

    protected User $supplierUser;

    protected User $admin;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $this->account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $this->supplier = Supplier::where('account_id', $this->account->id)->firstOrFail();
        $this->supplierUser = User::where('email', 'supplier-portal@excellence-uk.test')->firstOrFail();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();
        $this->campaign = Campaign::where('account_id', $this->account->id)->firstOrFail();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_supplier_can_create_draft_form_on_embeds_page(): void
    {
        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.forms.store'), [
                'campaign_id' => $this->campaign->id,
                'name' => 'Affiliate Landing Form',
                'redirect_url' => 'https://partner.example/thanks',
                'allowed_domains' => ['partner.example'],
            ])
            ->assertRedirect();

        $form = HostedForm::where('name', 'Affiliate Landing Form')->first();
        $this->assertNotNull($form);
        $this->assertSame($this->supplier->id, $form->supplier_id);
        $this->assertSame(SupplierHostedFormService::STATUS_DRAFT, $form->approval_status);
        $this->assertFalse($form->is_active);
        $this->assertSame($this->supplier->id, $form->config['default_supplier_id']);
    }

    public function test_supplier_form_stores_selected_tracking_sid(): void
    {
        $secondSource = $this->supplier->sources()->create([
            'sid' => 'facebook_ads',
            'name' => 'Facebook Ads',
        ]);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.forms.store'), [
                'campaign_id' => $this->campaign->id,
                'source_id' => $secondSource->id,
                'name' => 'Facebook Landing Form',
                'redirect_url' => '',
                'allowed_domains' => [],
            ])
            ->assertRedirect();

        $form = HostedForm::where('name', 'Facebook Landing Form')->firstOrFail();
        $this->assertSame('facebook_ads', $form->config['default_sid']);
        $this->assertSame($secondSource->id, $form->config['default_source_id']);
    }

    public function test_embed_urls_use_form_specific_sid(): void
    {
        $facebookSource = $this->supplier->sources()->create([
            'sid' => 'facebook_ads',
            'name' => 'Facebook Ads',
        ]);

        HostedForm::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Facebook Embed Form',
            'slug' => 'facebook-embed-form',
            'is_active' => true,
            'approval_status' => SupplierHostedFormService::STATUS_APPROVED,
            'config' => [
                'steps' => [],
                'default_supplier_id' => $this->supplier->id,
                'default_sid' => $facebookSource->sid,
                'default_source_id' => $facebookSource->id,
            ],
        ]);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->get(route('portal.supplier.embeds'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('forms', fn ($forms) => collect($forms)->contains(
                    fn ($form) => $form['name'] === 'Facebook Embed Form'
                        && str_contains($form['embed']['directUrl'], 'sid=facebook_ads')
                        && $form['default_sid'] === 'facebook_ads'
                ))
            );
    }

    public function test_supplier_can_submit_form_for_tenant_approval(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Pending Supplier Form',
            'slug' => 'pending-supplier-form',
            'is_active' => false,
            'approval_status' => SupplierHostedFormService::STATUS_DRAFT,
            'config' => ['steps' => []],
        ]);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.forms.submit', $form), [
                'submission_notes' => 'Planning to embed on partner.example landing page.',
            ])
            ->assertRedirect();

        $form->refresh();
        $this->assertSame(SupplierHostedFormService::STATUS_PENDING, $form->approval_status);
        $this->assertNotNull($form->submitted_at);

        $this->assertDatabaseHas('platform_notifications', [
            'account_id' => $this->account->id,
            'audience' => 'tenant',
            'title' => 'Form approval requested',
        ]);
    }

    public function test_admin_can_approve_supplier_form(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Approve Me',
            'slug' => 'approve-me-form',
            'is_active' => false,
            'approval_status' => SupplierHostedFormService::STATUS_PENDING,
            'submitted_at' => now(),
            'config' => ['steps' => []],
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('forms.approve', $form))
            ->assertRedirect();

        $form->refresh();
        $this->assertSame(SupplierHostedFormService::STATUS_APPROVED, $form->approval_status);
        $this->assertTrue($form->is_active);
        $this->assertTrue($form->isLive());
    }

    public function test_admin_can_reject_supplier_form(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Reject Me',
            'slug' => 'reject-me-form',
            'is_active' => false,
            'approval_status' => SupplierHostedFormService::STATUS_PENDING,
            'submitted_at' => now(),
            'config' => ['steps' => []],
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('forms.reject', $form), [
                'rejection_reason' => 'Missing compliance copy on the landing page.',
            ])
            ->assertRedirect();

        $form->refresh();
        $this->assertSame(SupplierHostedFormService::STATUS_REJECTED, $form->approval_status);
        $this->assertFalse($form->is_active);
        $this->assertSame('Missing compliance copy on the landing page.', $form->rejection_reason);
    }

    public function test_approved_supplier_form_appears_on_embeds_page(): void
    {
        HostedForm::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Live Supplier Form',
            'slug' => 'live-supplier-form',
            'is_active' => true,
            'approval_status' => SupplierHostedFormService::STATUS_APPROVED,
            'config' => ['steps' => []],
        ]);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->get(route('portal.supplier.embeds'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Supplier/Embeds')
                ->has('campaigns')
                ->has('requests')
                ->where('forms', fn ($forms) => collect($forms)->contains(fn ($form) => $form['name'] === 'Live Supplier Form'))
            );
    }

    public function test_pending_supplier_form_is_not_publicly_accessible(): void
    {
        $form = HostedForm::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Hidden Pending Form',
            'slug' => 'hidden-pending-form',
            'is_active' => false,
            'approval_status' => SupplierHostedFormService::STATUS_PENDING,
            'config' => ['steps' => []],
        ]);

        $this->ukHost()
            ->get(route('forms.show', $form->slug))
            ->assertNotFound();
    }

    public function test_admin_forms_index_lists_pending_approvals(): void
    {
        HostedForm::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Queue Item',
            'slug' => 'queue-item-form',
            'is_active' => false,
            'approval_status' => SupplierHostedFormService::STATUS_PENDING,
            'submitted_at' => now(),
            'config' => ['steps' => []],
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->get(route('forms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Forms/Index')
                ->has('pendingApprovals', 1)
                ->where('approvalStats.pending', 1)
            );
    }
}
