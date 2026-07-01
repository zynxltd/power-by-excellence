<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadImport;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierImportTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected Supplier $supplier;

    protected User $supplierUser;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Route::has('portal.supplier.leads.import.errors')) {
            \Illuminate\Support\Facades\Route::get(
                '/portal/supplier/leads/import/{import}/errors',
                [\App\Http\Controllers\Portal\SupplierPortalController::class, 'downloadImportErrors'],
            )
                ->middleware(['web', 'auth', 'verified'])
                ->name('portal.supplier.leads.import.errors');
        }

        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $this->account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $this->supplier = Supplier::where('account_id', $this->account->id)->firstOrFail();
        $this->supplierUser = User::where('email', 'supplier-portal@excellence-uk.test')->firstOrFail();
        $this->campaign = Campaign::where('account_id', $this->account->id)
            ->where('reference', 'auto-insurance-uk')
            ->firstOrFail();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_supplier_can_open_import_page(): void
    {
        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->get(route('portal.supplier.leads.import'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Supplier/ImportLeads')
                ->where('supplier.id', $this->supplier->id)
                ->has('campaigns')
                ->where('campaigns', fn ($campaigns) => collect($campaigns)->contains('reference', 'auto-insurance-uk'))
                ->has('recentImports'));
    }

    public function test_supplier_mapped_csv_import_attributes_leads_to_supplier(): void
    {
        $csv = "Email Address,Mobile\nsupplier-import-1@test.test,07700900123\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.leads.import.store'), [
                'campaign_id' => $this->campaign->id,
                'file' => $file,
                'column_mapping' => [
                    'Email Address' => 'email',
                    'Mobile' => 'phone1',
                ],
            ])
            ->assertRedirect(route('portal.supplier.leads.import'))
            ->assertSessionHas('success')
            ->assertSessionHas('importResult');

        $import = LeadImport::where('user_id', $this->supplierUser->id)->latest('id')->first();
        $this->assertNotNull($import);
        $this->assertSame(1, $import->success_rows);
        $this->assertSame([
            'Email Address' => 'email',
            'Mobile' => 'phone1',
        ], $import->column_mapping);

        $lead = Lead::where('campaign_id', $this->campaign->id)
            ->where('supplier_id', $this->supplier->id)
            ->whereJsonContains('field_data->email', 'supplier-import-1@test.test')
            ->first();

        $this->assertNotNull($lead);
    }

    public function test_supplier_import_reports_partial_failures_and_downloadable_error_csv(): void
    {
        $csv = "email,phone1\nvalid@example.com,07700900123\n,bad-row\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.leads.import.store'), [
                'campaign_id' => $this->campaign->id,
                'file' => $file,
                'column_mapping' => [
                    'email' => 'email',
                    'phone1' => 'phone1',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('importResult', fn (array $result) => $result['success_rows'] === 1 && $result['failed_rows'] === 1);

        $import = LeadImport::where('user_id', $this->supplierUser->id)->latest('id')->first();
        $this->assertNotNull($import);
        $this->assertSame(1, $import->failed_rows);
        $this->assertNotEmpty($import->errors);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->get('/portal/supplier/leads/import/'.$import->id.'/errors')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_supplier_cannot_import_for_unlinked_campaign(): void
    {
        $otherCampaign = Campaign::where('account_id', $this->account->id)
            ->where('reference', '!=', $this->campaign->reference)
            ->firstOrFail();

        $otherCampaign->campaignSuppliers()->where('supplier_id', $this->supplier->id)->delete();

        $csv = "email,phone1\nblocked@test.test,07700900999\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.leads.import.store'), [
                'campaign_id' => $otherCampaign->id,
                'file' => $file,
                'column_mapping' => ['email' => 'email', 'phone1' => 'phone1'],
            ])
            ->assertForbidden();
    }

    public function test_supplier_import_requires_column_mapping(): void
    {
        $csv = "email,phone1\nblocked@test.test,07700900999\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.leads.import.store'), [
                'campaign_id' => $this->campaign->id,
                'file' => $file,
                'column_mapping' => [],
            ])
            ->assertSessionHasErrors('column_mapping');
    }
}
