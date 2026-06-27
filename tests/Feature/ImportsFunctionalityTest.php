<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\CampaignField;
use App\Models\Lead;
use App\Models\LeadImport;
use App\Models\User;
use App\Enums\DeliveryMethod;
use App\Models\Delivery;
use App\Services\Api\ApiKeyService;
use App\Services\Leads\LeadPipeline;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImportsFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected Account $ukAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    protected function importCampaign(): Campaign
    {
        $campaign = Campaign::create([
            'account_id' => $this->ukAccount->id,
            'name' => 'Import QA',
            'reference' => 'import-qa-'.Str::random(6),
            'status' => 'active',
            'payout_amount' => 5,
            'currency' => 'GBP',
        ]);

        foreach (['firstname', 'lastname', 'email', 'phone1', 'zipcode'] as $i => $name) {
            CampaignField::create([
                'campaign_id' => $campaign->id,
                'name' => $name,
                'required' => in_array($name, ['email', 'phone1', 'zipcode'], true),
                'sort_order' => $i,
            ]);
        }

        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();

        Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Import Store',
            'method' => DeliveryMethod::StoreLead,
            'status' => 'active',
            'revenue_type' => 'fixed',
            'revenue_amount' => 15,
        ]);

        return $campaign;
    }

    public function test_imports_routes_map_to_tools_module(): void
    {
        $this->assertSame('tools', AdminModules::moduleForRoute('imports.index'));
        $this->assertSame('tools', AdminModules::moduleForRoute('imports.store'));
    }

    public function test_lead_csv_import_records_success_and_failed_rows(): void
    {
        Queue::fake();

        $campaign = $this->importCampaign();
        $email = 'import-ok.'.uniqid().'@example.com';

        $csv = "firstname,lastname,email,phone1,zipcode\n";
        $csv .= "Good,Row,{$email},07700900123,SW1A 1AA\n";
        $csv .= "Bad,Row,short@example.com\n";

        $file = UploadedFile::fake()->createWithContent('mixed.csv', $csv);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('imports.store'), [
                'campaign_id' => $campaign->id,
                'file' => $file,
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('success');

        $import = LeadImport::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($import);
        $this->assertSame('completed', $import->status);
        $this->assertSame(2, $import->total_rows);
        $this->assertSame(1, $import->success_rows);
        $this->assertSame(1, $import->failed_rows);
        $this->assertSame($import->total_rows, $import->success_rows + $import->failed_rows);
    }

    public function test_csv_with_bom_header_imports_successfully(): void
    {
        Queue::fake();

        $campaign = $this->importCampaign();
        $email = 'bom-import.'.uniqid().'@example.com';
        $csv = "\xEF\xBB\xBFfirstname,email,phone1,zipcode\nJane,{$email},07700900444,EC1A 1BB\n";
        $file = UploadedFile::fake()->createWithContent('bom.csv', $csv);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('imports.store'), [
                'campaign_id' => $campaign->id,
                'file' => $file,
            ])
            ->assertRedirect();

        $import = LeadImport::where('filename', 'bom.csv')->first();
        $this->assertSame(1, $import->success_rows);
        $this->assertSame(0, $import->failed_rows);
    }

    public function test_malformed_row_counts_as_failed(): void
    {
        Queue::fake();

        $campaign = $this->importCampaign();
        $csv = "email,phone1,zipcode\nonly-two@example.com,07700900555\n";
        $file = UploadedFile::fake()->createWithContent('mismatch.csv', $csv);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('imports.store'), [
                'campaign_id' => $campaign->id,
                'file' => $file,
            ])
            ->assertRedirect();

        $import = LeadImport::where('filename', 'mismatch.csv')->first();
        $this->assertSame(1, $import->failed_rows);
        $this->assertSame(0, $import->success_rows);
    }

    public function test_store_rejects_cross_tenant_campaign(): void
    {
        $usCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'partner-solar-us'))
            ->first();

        $csv = "email,phone1,zipcode\ncross@example.com,07700900666,90210\n";
        $file = UploadedFile::fake()->createWithContent('cross.csv', $csv);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('imports.store'), [
                'campaign_id' => $usCampaign->id,
                'file' => $file,
            ])
            ->assertSessionHasErrors('campaign_id');

        $this->assertDatabaseMissing('lead_imports', ['filename' => 'cross.csv']);
    }

    public function test_import_history_is_tenant_scoped(): void
    {
        $campaign = $this->importCampaign();

        LeadImport::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'user_id' => $this->ukAdmin->id,
            'filename' => 'uk-only.csv',
            'status' => 'completed',
            'total_rows' => 1,
            'success_rows' => 1,
            'failed_rows' => 0,
        ]);

        $otherAccount = Account::where('slug', 'partner-solar-us')->first();
        $otherCampaign = Campaign::withoutGlobalScopes()->where('account_id', $otherAccount->id)->first();

        LeadImport::withoutGlobalScopes()->create([
            'account_id' => $otherAccount->id,
            'campaign_id' => $otherCampaign->id,
            'filename' => 'us-only.csv',
            'status' => 'completed',
            'total_rows' => 5,
            'success_rows' => 5,
            'failed_rows' => 0,
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('imports.data', fn ($rows) => collect($rows)->pluck('filename')->contains('uk-only.csv')
                    && ! collect($rows)->pluck('filename')->contains('us-only.csv'))
            );
    }

    public function test_api_import_rejects_cross_tenant_campaign_reference(): void
    {
        $usCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'partner-solar-us'))
            ->first();

        $token = app(ApiKeyService::class)->create([
            'account_id' => $this->ukAccount->id,
            'name' => 'UK import',
            'type' => 'administrator',
            'permissions' => ['leads.create'],
        ])['token'];

        $csv = "firstname,email,phone1,zipcode\nApi,api.".uniqid().'@example.com,07700900777,SW1A 1AA'."\n";
        $file = UploadedFile::fake()->createWithContent('api.csv', $csv);

        $this->post('/api/v1/leads/import', [
            'campaign_reference' => $usCampaign->reference,
            'file' => $file,
        ], ['Authorization' => 'Bearer '.$token])
            ->assertNotFound();
    }

    public function test_suppression_import_blocks_matching_leads_in_pipeline(): void
    {
        $campaign = $this->importCampaign();
        $blockedEmail = 'blocked.'.uniqid().'@example.com';

        $csv = "email\n{$blockedEmail}\n";
        $file = UploadedFile::fake()->createWithContent('suppression.csv', $csv);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('imports.store'), [
                'type' => 'suppression',
                'campaign_id' => $campaign->id,
                'field' => 'email',
                'file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => [
                'firstname' => 'Blocked',
                'lastname' => 'User',
                'email' => $blockedEmail,
                'phone1' => '07700900888',
                'zipcode' => 'SW1A 1AA',
            ],
            'received_at' => now(),
        ]);

        app(LeadPipeline::class)->process($lead->fresh());

        $lead->refresh();
        $this->assertSame(LeadStatus::Rejected, $lead->status);
        $this->assertStringContainsString('Suppressed', $lead->reject_reason);
    }

    public function test_suppression_import_deduplicates_hashes(): void
    {
        $campaign = $this->importCampaign();
        $csv = "email\ndupe@blocked.com\ndupe@blocked.com\n";
        $file = UploadedFile::fake()->createWithContent('dupes.csv', $csv);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('imports.store'), [
                'type' => 'suppression',
                'campaign_id' => $campaign->id,
                'field' => 'email',
                'file' => $file,
            ])
            ->assertRedirect();

        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('suppression_hashes')
            ->where('account_id', $campaign->account_id)
            ->where('field_type', 'email')
            ->where('hash', hash('sha256', 'dupe@blocked.com'))
            ->count());
    }

    public function test_suppression_import_accepts_pre_hashed_values(): void
    {
        $campaign = $this->importCampaign();
        $digest = hash('sha256', 'prehashed.'.uniqid().'@example.com');
        $csv = "hash\n{$digest}\n";
        $file = UploadedFile::fake()->createWithContent('prehashed.csv', $csv);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('imports.store'), [
                'type' => 'suppression',
                'campaign_id' => $campaign->id,
                'field' => 'email',
                'file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('suppression_hashes', [
            'account_id' => $campaign->account_id,
            'field_type' => 'email',
            'hash' => $digest,
        ]);
    }

    public function test_suppression_import_supports_headerless_single_column_csv(): void
    {
        $campaign = $this->importCampaign();
        $email = 'headerless.'.uniqid().'@example.com';
        $csv = "{$email}\n";
        $file = UploadedFile::fake()->createWithContent('headerless.csv', $csv);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('imports.store'), [
                'type' => 'suppression',
                'campaign_id' => $campaign->id,
                'field' => 'email',
                'file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('suppression_hashes', [
            'account_id' => $campaign->account_id,
            'field_type' => 'email',
            'hash' => hash('sha256', strtolower($email)),
        ]);
    }

    public function test_suppression_import_normalises_phone_formats(): void
    {
        $campaign = $this->importCampaign();
        $csv = "phone1\n07700900444\n";
        $file = UploadedFile::fake()->createWithContent('phones.csv', $csv);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('imports.store'), [
                'type' => 'suppression',
                'campaign_id' => $campaign->id,
                'field' => 'phone1',
                'file' => $file,
            ])
            ->assertRedirect();

        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => [
                'firstname' => 'Phone',
                'lastname' => 'Blocked',
                'email' => 'phone.'.uniqid().'@example.com',
                'phone1' => '+44 7700 900444',
                'zipcode' => 'SW1A 1AA',
            ],
            'received_at' => now(),
        ]);

        app(LeadPipeline::class)->process($lead->fresh());

        $lead->refresh();
        $this->assertSame(LeadStatus::Rejected, $lead->status);
        $this->assertStringContainsString('Suppressed', $lead->reject_reason);
    }

    public function test_empty_csv_completes_with_zero_rows(): void
    {
        $campaign = $this->importCampaign();
        $file = UploadedFile::fake()->createWithContent('empty.csv', '');

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('imports.store'), [
                'campaign_id' => $campaign->id,
                'file' => $file,
            ])
            ->assertRedirect();

        $import = LeadImport::where('filename', 'empty.csv')->first();
        $this->assertSame('completed', $import->status);
        $this->assertSame(0, $import->total_rows);
        $this->assertSame(0, $import->success_rows);
        $this->assertSame(0, $import->failed_rows);
    }
}
