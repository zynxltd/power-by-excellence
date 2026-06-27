<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuarantineFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected Account $ukAccount;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
        $this->campaign = Campaign::where('reference', 'loans-uk')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    protected function apiAuth(array $permissions): array
    {
        $token = app(ApiKeyService::class)->create([
            'account_id' => $this->ukAccount->id,
            'name' => 'Quarantine API',
            'type' => 'administrator',
            'permissions' => $permissions,
        ])['token'];

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_quarantine_routes_map_to_operations_module(): void
    {
        $this->assertSame('operations', \App\Support\AdminModules::moduleForRoute('quarantine.index'));
        $this->assertSame('operations', \App\Support\AdminModules::moduleForRoute('quarantine.release'));
    }

    public function test_index_resolves_human_readable_quarantine_reasons(): void
    {
        $ooh = $this->createQuarantinedLead([
            'metadata' => [
                'quarantine_reason' => 'out_of_hours',
                'quarantine_message' => 'Out of hours - held for next delivery window',
            ],
        ]);

        $validation = $this->createQuarantinedLead([
            'field_data' => ['email' => 'validation@test.test'],
            'metadata' => [
                'quarantine_reason' => 'validation',
                'email_validation' => ['status' => 'invalid'],
            ],
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('quarantine.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Quarantine/Index')
                ->has('policy.default_hours')
                ->where('policy.validation_rejects_on_expire', true)
                ->where('leads.data', fn ($rows) => collect($rows)->contains(
                    fn ($row) => $row['id'] === $ooh->id && $row['quarantine_reason'] === 'out_of_hours'
                ))
                ->where('leads.data', fn ($rows) => collect($rows)->contains(
                    fn ($row) => $row['id'] === $validation->id && $row['quarantine_reason'] === 'validation'
                ))
            );
    }

    public function test_hold_stats_include_leads_without_explicit_reason_code(): void
    {
        $this->createQuarantinedLead([
            'field_data' => ['email' => 'implicit-hold@test.test'],
            'metadata' => [
                'quarantine_message' => 'Manual review hold',
            ],
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('quarantine.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.hold', fn ($count) => $count >= 1)
            );
    }

    public function test_unsold_and_expiring_filters(): void
    {
        $unsold = $this->createQuarantinedLead([
            'field_data' => ['email' => 'unsold-filter@test.test'],
            'metadata' => [
                'quarantine_reason' => 'unsold',
                'quarantine_message' => 'Unsold - held for retry',
            ],
        ]);

        $expiring = $this->createQuarantinedLead([
            'field_data' => ['email' => 'expiring@test.test'],
            'quarantined_until' => now()->subHour(),
            'metadata' => ['quarantine_reason' => 'hold'],
        ]);

        $this->createQuarantinedLead([
            'field_data' => ['email' => 'future@test.test'],
            'quarantined_until' => now()->addDays(2),
            'metadata' => ['quarantine_reason' => 'hold'],
        ]);

        $host = $this->ukHost()->actingAs($this->ukAdmin);

        $host->get(route('quarantine.index', ['reason' => 'unsold']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.reason', 'unsold')
                ->where('leads.data', fn ($rows) => collect($rows)->pluck('id')->contains($unsold->id))
            );

        $host->get(route('quarantine.index', ['reason' => 'expiring']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.reason', 'expiring')
                ->where('leads.data', fn ($rows) => collect($rows)->pluck('id')->contains($expiring->id))
            );
    }

    public function test_single_release_and_reject_from_quarantine_queue(): void
    {
        Queue::fake();

        $releasable = $this->createQuarantinedLead([
            'metadata' => ['quarantine_reason' => 'hold'],
        ]);

        $rejectable = $this->createQuarantinedLead([
            'field_data' => ['email' => 'reject-single@test.test'],
            'metadata' => ['quarantine_reason' => 'unsold'],
        ]);

        $host = $this->ukHost()->actingAs($this->ukAdmin);

        $host->post(route('quarantine.release', $releasable))
            ->assertRedirect();

        $releasable->refresh();
        $this->assertSame(LeadStatus::Accepted, $releasable->status);
        $this->assertNull($releasable->quarantined_until);
        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class);

        $host->post(route('quarantine.reject', $rejectable))
            ->assertRedirect();

        $rejectable->refresh();
        $this->assertSame(LeadStatus::Rejected, $rejectable->status);
        $this->assertSame('Quarantine rejected by admin', $rejectable->reject_reason);
        $this->assertNull($rejectable->quarantined_until);
    }

    public function test_lead_show_reject_clears_quarantine_until(): void
    {
        $lead = $this->createQuarantinedLead([
            'metadata' => ['quarantine_reason' => 'validation', 'email_validation' => ['status' => 'invalid']],
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('leads.quarantine.reject', $lead))
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame(LeadStatus::Rejected, $lead->status);
        $this->assertNull($lead->quarantined_until);
    }

    public function test_api_validation_hold_cannot_be_released(): void
    {
        $lead = $this->createQuarantinedLead([
            'uuid' => (string) Str::uuid(),
            'metadata' => [
                'quarantine_reason' => 'validation',
                'email_validation' => ['status' => 'invalid'],
            ],
        ]);

        $this->postJson(
            '/api/v1/quarantine/'.$lead->uuid.'/release',
            [],
            $this->apiAuth(['quarantine.manage'])
        )
            ->assertStatus(422)
            ->assertJsonPath('error', 'Validation holds must be rejected - they cannot be released back into distribution.');

        $this->assertSame(LeadStatus::Quarantined, $lead->fresh()->status);
    }

    public function test_api_quarantine_is_tenant_scoped(): void
    {
        $otherCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();

        $foreignLead = Lead::withoutGlobalScopes()->create([
            'account_id' => $otherCampaign->account_id,
            'campaign_id' => $otherCampaign->id,
            'uuid' => (string) Str::uuid(),
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'foreign-api@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => ['quarantine_reason' => 'hold'],
        ]);

        $this->getJson('/api/v1/quarantine', $this->apiAuth(['quarantine.manage']))
            ->assertOk()
            ->assertJsonMissing(['uuid' => $foreignLead->uuid]);

        $this->postJson(
            '/api/v1/quarantine/'.$foreignLead->uuid.'/release',
            [],
            $this->apiAuth(['quarantine.manage'])
        )->assertNotFound();
    }

    public function test_api_reject_clears_quarantine_until(): void
    {
        $lead = $this->createQuarantinedLead([
            'uuid' => (string) Str::uuid(),
            'metadata' => ['quarantine_reason' => 'hold'],
        ]);

        $this->postJson(
            '/api/v1/quarantine/'.$lead->uuid.'/reject',
            [],
            $this->apiAuth(['quarantine.manage'])
        )->assertOk();

        $lead->refresh();
        $this->assertSame(LeadStatus::Rejected, $lead->status);
        $this->assertNull($lead->quarantined_until);
    }

    public function test_expired_out_of_hours_hold_is_auto_released(): void
    {
        Queue::fake();

        $lead = $this->createQuarantinedLead([
            'quarantined_until' => now()->subMinutes(5),
            'metadata' => ['quarantine_reason' => 'out_of_hours'],
        ]);

        $this->artisan('quarantine:process-expired')->assertSuccessful();

        $lead->refresh();
        $this->assertSame(LeadStatus::Accepted, $lead->status);
        $this->assertNull($lead->quarantined_until);
        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createQuarantinedLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'quarantine-'.Str::random(6).'@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => ['quarantine_reason' => 'hold'],
        ], $overrides));
    }
}
