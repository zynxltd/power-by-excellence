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
                ->has('recentImports'));
    }

    public function test_supplier_csv_import_attributes_leads_to_supplier(): void
    {
        $csv = "email,phone1\nsupplier-import-1@test.test,07700900123\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->ukHost()
            ->actingAs($this->supplierUser)
            ->post(route('portal.supplier.leads.import.store'), [
                'campaign_id' => $this->campaign->id,
                'file' => $file,
            ])
            ->assertRedirect(route('portal.supplier.leads.import'))
            ->assertSessionHas('success');

        $import = LeadImport::where('user_id', $this->supplierUser->id)->latest('id')->first();
        $this->assertNotNull($import);
        $this->assertSame(1, $import->success_rows);

        $lead = Lead::where('campaign_id', $this->campaign->id)
            ->where('supplier_id', $this->supplier->id)
            ->whereJsonContains('field_data->email', 'supplier-import-1@test.test')
            ->first();

        $this->assertNotNull($lead);
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
            ])
            ->assertForbidden();
    }
}
