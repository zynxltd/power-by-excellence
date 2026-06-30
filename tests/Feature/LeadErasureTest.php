<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LeadAdminController;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Lead;
use App\Models\LeadReturn;
use App\Models\User;
use App\Services\Compliance\LeadErasureService;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LeadErasureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = Account::where('slug', 'excellence-uk')->first();
    }

    protected function makeLead(array $overrides = []): Lead
    {
        $template = Lead::withoutGlobalScopes()
            ->where('account_id', $this->account->id)
            ->first();

        return Lead::create(array_merge([
            'account_id' => $this->account->id,
            'campaign_id' => $template->campaign_id,
            'supplier_id' => $template->supplier_id,
            'source_id' => $template->source_id,
            'status' => $template->status,
            'received_at' => now(),
            'field_data' => [
                'firstname' => 'Erase',
                'lastname' => 'Me',
                'email' => 'erase.'.uniqid().'@example.com',
                'phone1' => '07700900500',
            ],
            'ip_address' => '203.0.113.44',
            'user_agent' => 'PHPUnit',
            'metadata' => [],
        ], $overrides));
    }

    protected function freshLead(int $id): Lead
    {
        return Lead::withoutGlobalScopes()->findOrFail($id);
    }

    protected function requestErasure(Lead $lead, array $payload = []): void
    {
        AccountContext::set($this->account);
        $this->actingAs($this->admin);

        $request = Request::create('/leads/'.$lead->id.'/erasure', 'POST', $payload);
        $request->setUserResolver(fn () => $this->admin);

        app(LeadAdminController::class)->requestErasure($request, $lead->fresh());
    }

    public function test_erasure_redacts_field_data(): void
    {
        $lead = $this->makeLead();

        $this->requestErasure($lead, ['reason' => 'Data subject erasure request']);

        $lead = $this->freshLead($lead->id);
        $this->assertSame('[redacted]', $lead->field_data['email']);
        $this->assertNotNull($lead->metadata['anonymized_at']);
        $this->assertSame('Data subject erasure request', $lead->metadata['erasure']['reason']);
        $this->assertDatabaseHas('account_audit_logs', [
            'action' => 'lead.erasure',
            'entity_id' => $lead->id,
            'account_id' => $this->account->id,
        ]);
    }

    public function test_erasure_blocked_with_pending_lead_return(): void
    {
        $lead = $this->makeLead();
        LeadReturn::create([
            'lead_id' => $lead->id,
            'buyer_id' => Buyer::where('account_id', $this->account->id)->first()->id,
            'reason' => 'Invalid contact details',
            'status' => 'pending',
        ]);

        AccountContext::set($this->account);
        $this->actingAs($this->admin);

        $request = Request::create('/leads/'.$lead->id.'/erasure', 'POST', ['reason' => 'Should be blocked']);
        $request->setUserResolver(fn () => $this->admin);

        app(LeadAdminController::class)->requestErasure($request, $lead->fresh());

        $this->assertTrue(session()->has('error'));
        $this->assertNotSame('[redacted]', $this->freshLead($lead->id)->field_data['email']);
    }

    public function test_erasure_is_idempotent_on_already_anonymized_lead(): void
    {
        $lead = $this->makeLead([
            'field_data' => ['email' => '[redacted]', 'phone1' => '[redacted]'],
            'metadata' => [
                'anonymized_at' => now()->subDay()->toIso8601String(),
                'erasure' => [
                    'requested_at' => now()->subDay()->toIso8601String(),
                    'requested_by' => $this->admin->id,
                    'reason' => 'Previous request',
                    'completed_at' => now()->subDay()->toIso8601String(),
                ],
            ],
        ]);

        $this->requestErasure($lead, ['reason' => 'Repeat request']);

        $this->assertSame('Previous request', $this->freshLead($lead->id)->metadata['erasure']['reason']);
    }

    public function test_erasure_requires_reason(): void
    {
        $lead = $this->makeLead();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        AccountContext::set($this->account);
        $this->actingAs($this->admin);

        $request = Request::create('/leads/'.$lead->id.'/erasure', 'POST', []);
        $request->setUserResolver(fn () => $this->admin);

        app(LeadAdminController::class)->requestErasure($request, $lead->fresh());
    }

    public function test_lead_erasure_service_reports_blocking_reason(): void
    {
        $lead = $this->makeLead();
        LeadReturn::create([
            'lead_id' => $lead->id,
            'buyer_id' => Buyer::where('account_id', $this->account->id)->first()->id,
            'reason' => 'Quality dispute',
            'status' => 'pending',
        ]);

        $this->assertStringContainsString('return', strtolower(app(LeadErasureService::class)->blockingReason($lead) ?? ''));
    }

    public function test_erasure_manifest_documents_route(): void
    {
        $contents = file_get_contents(base_path('routes/compliance-phase-3.php'));
        $this->assertStringContainsString('registerCompliancePhase3LeadErasureRoutes', $contents);
        $this->assertStringContainsString('leads.erasure', $contents);
    }
}
