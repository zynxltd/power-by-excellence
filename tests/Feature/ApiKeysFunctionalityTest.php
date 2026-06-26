<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiKeysFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected User $superAdmin;

    protected Account $ukAccount;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->superAdmin = User::where('email', 'admin@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
        $this->campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    protected function centralHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'powerbyexcellence.test']);
    }

    public function test_api_keys_route_maps_to_tools_module(): void
    {
        $this->assertSame('tools', AdminModules::moduleForRoute('api-keys.index'));
        $this->assertSame('tools', AdminModules::moduleForRoute('api-keys.store'));
        $this->assertSame('tools', AdminModules::moduleForRoute('api-keys.destroy'));
    }

    public function test_index_scopes_to_tenant_and_hides_key_hash(): void
    {
        $otherAccount = Account::where('slug', 'insurance-ca')->first();
        ApiKey::withoutGlobalScopes()->create([
            'account_id' => $otherAccount->id,
            'name' => 'Foreign tenant key',
            'type' => 'administrator',
            'key_prefix' => 'foreign1',
            'key_hash' => Hash::make('secret'),
            'permissions' => ['*'],
            'is_active' => true,
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('api-keys.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ApiKeys/Index')
                ->has('apiKeys')
                ->has('suppliers')
                ->where('apiKeys', fn ($keys) => collect($keys)->every(
                    fn ($key) => $key['account_id'] === $this->ukAccount->id
                        && ! array_key_exists('key_hash', $key)
                ))
                ->where('apiKeys', fn ($keys) => collect($keys)->pluck('name')->doesntContain('Foreign tenant key'))
            );
    }

    public function test_super_admin_on_central_without_tenant_redirects_to_accounts(): void
    {
        $this->centralHost()
            ->actingAs($this->superAdmin)
            ->get(route('api-keys.index'))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error');
    }

    public function test_super_admin_with_tenant_context_can_manage_keys(): void
    {
        $this->centralHost()
            ->actingAs($this->superAdmin)
            ->withSession(['current_account_id' => $this->ukAccount->id])
            ->get(route('api-keys.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/ApiKeys/Index'));

        $this->centralHost()
            ->actingAs($this->superAdmin)
            ->withSession(['current_account_id' => $this->ukAccount->id])
            ->post(route('api-keys.store'), [
                'name' => 'Central context admin key',
                'type' => 'administrator',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'API key created'));

        $key = ApiKey::where('name', 'Central context admin key')->first();
        $this->assertSame($this->ukAccount->id, $key->account_id);
        $this->assertSame(['*'], $key->permissions);
    }

    public function test_administrator_key_defaults_to_full_access(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('api-keys.store'), [
                'name' => 'Full admin key',
                'type' => 'administrator',
            ])
            ->assertRedirect();

        $key = ApiKey::where('name', 'Full admin key')->first();
        $this->assertSame(['*'], $key->permissions);
    }

    public function test_supplier_key_requires_tenant_supplier_and_scopes_ingest(): void
    {
        $supplier = Supplier::where('account_id', $this->ukAccount->id)->first();
        $otherSupplier = Supplier::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'partner-solar-us'))
            ->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('api-keys.store'), [
                'name' => 'Missing supplier',
                'type' => 'supplier',
            ])
            ->assertSessionHasErrors('supplier_id');

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('api-keys.store'), [
                'name' => 'Cross tenant supplier',
                'type' => 'supplier',
                'supplier_id' => $otherSupplier->id,
            ])
            ->assertSessionHasErrors('supplier_id');

        $response = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('api-keys.store'), [
                'name' => 'Valid supplier key',
                'type' => 'supplier',
                'supplier_id' => $supplier->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $token = Str::after($response->getSession()->get('success'), 'Token (copy now): ');
        $key = ApiKey::where('name', 'Valid supplier key')->first();
        $this->assertSame($supplier->id, $key->supplier_id);
        $this->assertSame(['leads.create', 'leads.read'], $key->permissions);

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $this->campaign->reference,
            'sync' => true,
            'firstname' => 'Key',
            'lastname' => 'Test',
            'email' => 'apikey.'.uniqid().'@test.test',
            'phone1' => '07700900111',
            'zipcode' => 'SW1A 1AA',
        ], ['Authorization' => 'Bearer '.$token])
            ->assertOk();
    }

    public function test_created_token_authenticates_via_bearer_or_x_api_key_header(): void
    {
        $result = app(ApiKeyService::class)->create([
            'account_id' => $this->ukAccount->id,
            'name' => 'Header test key',
            'type' => 'administrator',
            'permissions' => ['leads.create'],
        ]);

        $payload = [
            'campaign_reference' => $this->campaign->reference,
            'sync' => true,
            'firstname' => 'Header',
            'lastname' => 'Test',
            'email' => 'header.'.uniqid().'@test.test',
            'phone1' => '07700900222',
            'zipcode' => 'SW1A 1AA',
        ];

        $this->postJson('/api/v1/leads', $payload, ['Authorization' => 'Bearer '.$result['token']])
            ->assertOk();

        $this->postJson('/api/v1/leads', [
            ...$payload,
            'email' => 'header2.'.uniqid().'@test.test',
        ], ['X-API-Key' => $result['token']])
            ->assertOk();
    }

    public function test_revoked_key_stops_working_and_last_used_updates(): void
    {
        $result = app(ApiKeyService::class)->create([
            'account_id' => $this->ukAccount->id,
            'name' => 'Revoke me',
            'type' => 'administrator',
            'permissions' => ['leads.create'],
        ]);

        $key = $result['api_key'];

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $this->campaign->reference,
            'sync' => true,
            'firstname' => 'Before',
            'lastname' => 'Revoke',
            'email' => 'before.'.uniqid().'@test.test',
            'phone1' => '07700900333',
            'zipcode' => 'SW1A 1AA',
        ], ['Authorization' => 'Bearer '.$result['token']])
            ->assertOk();

        $this->assertNotNull($key->fresh()->last_used_at);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->delete(route('api-keys.destroy', $key))
            ->assertRedirect();

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $this->campaign->reference,
            'sync' => true,
            'firstname' => 'After',
            'lastname' => 'Revoke',
            'email' => 'after.'.uniqid().'@test.test',
            'phone1' => '07700900444',
            'zipcode' => 'SW1A 1AA',
        ], ['Authorization' => 'Bearer '.$result['token']])
            ->assertUnauthorized();
    }

    public function test_invalid_permissions_are_rejected(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('api-keys.store'), [
                'name' => 'Bad permissions',
                'type' => 'administrator',
                'permissions' => ['totally.made.up'],
            ])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_tenant_admin_cannot_revoke_other_tenant_api_key(): void
    {
        $otherAccount = Account::where('slug', 'insurance-ca')->first();
        $key = ApiKey::withoutGlobalScopes()->create([
            'account_id' => $otherAccount->id,
            'name' => 'Foreign key',
            'type' => 'administrator',
            'key_prefix' => 'foreign2',
            'key_hash' => Hash::make('foreign'),
            'permissions' => ['*'],
            'is_active' => true,
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->delete(route('api-keys.destroy', $key))
            ->assertNotFound();
    }
}
