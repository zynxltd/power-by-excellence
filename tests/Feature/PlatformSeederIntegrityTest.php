<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\CampaignSupplier;
use App\Models\HostedForm;
use App\Models\Lead;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformSeederIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_all_configured_platforms_are_seeded(): void
    {
        $expected = count(config('tenant_platforms', []));

        $this->assertSame($expected, Account::count());
        $this->assertGreaterThanOrEqual(13, $expected);

        foreach (config('tenant_platforms', []) as $platform) {
            $account = Account::where('slug', $platform['slug'])->first();
            $this->assertNotNull($account, "Missing account: {$platform['slug']}");
            $this->assertSame(count($platform['campaigns']), Campaign::withoutGlobalScopes()->where('account_id', $account->id)->count());
            $this->assertNotNull(User::where('email', $platform['admin_email'])->first());
        }
    }

    public function test_each_platform_has_supplier_campaign_links_and_capture_forms(): void
    {
        foreach (Account::orderBy('id')->get() as $account) {
            $supplier = Supplier::where('account_id', $account->id)->where('reference', 'supplier-main')->first();
            $this->assertNotNull($supplier, "Missing main supplier on {$account->slug}");

            $campaignCount = Campaign::withoutGlobalScopes()->where('account_id', $account->id)->count();
            $linkCount = CampaignSupplier::where('supplier_id', $supplier->id)->count();
            $this->assertSame($campaignCount, $linkCount, "Supplier links mismatch on {$account->slug}");

            $formCount = HostedForm::withoutGlobalScopes()->where('account_id', $account->id)->count();
            $this->assertGreaterThanOrEqual(min(3, $campaignCount), $formCount, "Too few forms on {$account->slug}");
        }
    }

    public function test_uk_seeded_form_is_public_and_submittable(): void
    {
        Queue::fake();

        $form = HostedForm::withoutGlobalScopes()->where('slug', 'auto-insurance-quote-uk')->first();
        $this->assertNotNull($form);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->get(route('forms.show', $form->slug))
            ->assertOk();

        $email = 'seeded-form.'.uniqid().'@example.com';

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->post(route('forms.submit', $form->slug), [
                'vehicle_year' => '2020',
                'vehicle_make' => 'Ford',
                'cover_type' => 'Comprehensive',
                'firstname' => 'Seeded',
                'lastname' => 'Form',
                'email' => $email,
                'phone1' => '07700900400',
                'zipcode' => 'SW1A 1AA',
            ], [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk();

        $lead = Lead::where('campaign_id', $form->campaign_id)
            ->where('field_data->email', $email)
            ->first();

        $this->assertNotNull($lead);
        $this->assertSame('hosted_form:auto-insurance-quote-uk', $lead->source);
        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class);
    }

    public function test_supplier_portal_lists_seeded_capture_forms(): void
    {
        $supplierUser = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($supplierUser)
            ->get(route('portal.supplier.embeds'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('iframeEmbedAllowed', true)
                ->where('forms', fn ($forms) => collect($forms)->contains(fn ($form) => ($form['slug'] ?? null) === 'auto-insurance-quote-uk'))
            );
    }

    public function test_new_tenants_are_reachable_on_their_hosts(): void
    {
        $checks = [
            ['slug' => 'france-fr', 'email' => 'fr@powerbyexcellence.test', 'form' => 'auto-fr-capture'],
            ['slug' => 'spain-es', 'email' => 'es@powerbyexcellence.test', 'form' => 'auto-es-capture'],
            ['slug' => 'singapore-sg', 'email' => 'sg@powerbyexcellence.test', 'form' => 'loans-sg-capture'],
        ];

        foreach ($checks as $check) {
            $account = Account::where('slug', $check['slug'])->firstOrFail();
            $admin = User::where('email', $check['email'])->firstOrFail();

            $this->withServerVariables(['HTTP_HOST' => $account->domain])
                ->actingAs($admin)
                ->get(route('forms.index'))
                ->assertOk();

            $this->withServerVariables(['HTTP_HOST' => $account->domain])
                ->get(route('forms.show', $check['form']))
                ->assertOk();
        }
    }

    public function test_uk_has_variety_lead_statuses_in_seed_data(): void
    {
        $account = Account::where('slug', 'excellence-uk')->firstOrFail();

        $this->assertTrue(Lead::withoutGlobalScopes()->where('account_id', $account->id)->where('status', 'quarantined')->exists());
        $this->assertTrue(Lead::withoutGlobalScopes()->where('account_id', $account->id)->where('status', 'duplicate')->exists());
        $this->assertTrue(Lead::withoutGlobalScopes()->where('account_id', $account->id)->where('status', 'rejected')->exists());
        $this->assertTrue(Lead::withoutGlobalScopes()->where('account_id', $account->id)->where('status', 'sold')->exists());
    }
}
