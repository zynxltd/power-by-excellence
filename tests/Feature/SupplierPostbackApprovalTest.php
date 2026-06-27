<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\PlatformNotification;
use App\Models\Postback;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Postbacks\SupplierPostbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierPostbackApprovalTest extends TestCase
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

    public function test_supplier_can_create_draft_postback(): void
    {
        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.postbacks.store'), [
                'name' => 'Affiliate pixel',
                'url' => url('/api/mock/postback'),
                'method' => 'get',
                'events' => ['lead.sold'],
            ])
            ->assertRedirect();

        $postback = Postback::where('name', 'Affiliate pixel')->first();
        $this->assertNotNull($postback);
        $this->assertSame($this->supplier->id, $postback->supplier_id);
        $this->assertSame(SupplierPostbackService::STATUS_DRAFT, $postback->approval_status);
        $this->assertFalse($postback->is_active);
    }

    public function test_supplier_can_submit_postback_for_tenant_approval(): void
    {
        $postback = Postback::create([
            'account_id' => $this->account->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Pending pixel',
            'url' => url('/api/mock/postback'),
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => false,
            'approval_status' => SupplierPostbackService::STATUS_DRAFT,
        ]);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.postbacks.submit', $postback), [
                'submission_notes' => 'Please enable for Google traffic.',
            ])
            ->assertRedirect();

        $postback->refresh();
        $this->assertSame(SupplierPostbackService::STATUS_PENDING, $postback->approval_status);

        $this->assertDatabaseHas('platform_notifications', [
            'account_id' => $this->account->id,
            'title' => 'Postback approval requested',
        ]);
    }

    public function test_admin_can_approve_supplier_postback(): void
    {
        $postback = Postback::create([
            'account_id' => $this->account->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Approve me',
            'url' => url('/api/mock/postback'),
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => false,
            'approval_status' => SupplierPostbackService::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('postbacks.approve', $postback))
            ->assertRedirect();

        $postback->refresh();
        $this->assertTrue($postback->isLive());
        $this->assertSame(SupplierPostbackService::STATUS_APPROVED, $postback->approval_status);
    }

    public function test_supplier_can_request_deletion_of_approved_postback(): void
    {
        $postback = Postback::create([
            'account_id' => $this->account->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Remove me',
            'url' => url('/api/mock/postback'),
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => true,
            'approval_status' => SupplierPostbackService::STATUS_APPROVED,
        ]);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.postbacks.request-deletion', $postback))
            ->assertRedirect();

        $this->assertSame(
            SupplierPostbackService::STATUS_PENDING_DELETION,
            $postback->fresh()->approval_status
        );
    }

    public function test_admin_can_confirm_postback_deletion(): void
    {
        $postback = Postback::create([
            'account_id' => $this->account->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Delete me',
            'url' => url('/api/mock/postback'),
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => true,
            'approval_status' => SupplierPostbackService::STATUS_PENDING_DELETION,
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('postbacks.approve-deletion', $postback))
            ->assertRedirect();

        $this->assertDatabaseMissing('postbacks', ['id' => $postback->id]);
    }

    public function test_dispatcher_only_fires_live_postbacks(): void
    {
        $lead = \App\Models\Lead::where('supplier_id', $this->supplier->id)->firstOrFail();

        $draft = Postback::create([
            'account_id' => $this->account->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Draft only',
            'url' => url('/api/mock/postback'),
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => false,
            'approval_status' => SupplierPostbackService::STATUS_DRAFT,
        ]);

        $approved = Postback::create([
            'account_id' => $this->account->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Live pixel',
            'url' => url('/api/mock/postback'),
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => true,
            'approval_status' => SupplierPostbackService::STATUS_APPROVED,
        ]);

        app(\App\Services\Postbacks\PostbackDispatcher::class)->dispatch($lead, 'lead.sold');

        $this->assertDatabaseMissing('postback_logs', ['postback_id' => $draft->id]);
        $this->assertDatabaseHas('postback_logs', ['postback_id' => $approved->id]);
    }

    public function test_integrations_page_lists_postback_requests(): void
    {
        Postback::create([
            'account_id' => $this->account->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Portal list',
            'url' => url('/api/mock/postback'),
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => false,
            'approval_status' => SupplierPostbackService::STATUS_DRAFT,
        ]);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->get(route('portal.supplier.integrations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Supplier/Integrations')
                ->has('postbackRequests', 1)
                ->where('postbackRequests.0.name', 'Portal list')
            );
    }
}
