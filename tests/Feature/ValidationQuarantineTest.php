<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Services\Leads\LeadPipeline;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationQuarantineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AccountContext::clear();
        session()->forget('current_account_id');
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_integration_validation_failure_quarantines_with_reason(): void
    {
        $campaign = Campaign::withoutGlobalScopes()->with('fields')->where('reference', 'auto-insurance-uk')->first();
        $this->assertNotNull($campaign);

        $campaign->update([
            'validation_config' => array_merge($campaign->validation_config ?? [], [
                'quarantine_on_validation_fail' => true,
                'email_validation' => true,
            ]),
        ]);

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => $this->validFieldData($campaign, ['email' => 'user@invalid.demo']),
            'received_at' => now(),
        ]);

        app(LeadPipeline::class)->process($lead->fresh());

        $lead->refresh();
        $this->assertSame(LeadStatus::Quarantined, $lead->status);
        $this->assertSame('validation', $lead->metadata['quarantine_reason'] ?? null);
        $this->assertNotEmpty($lead->metadata['email_validation'] ?? null);

        $this->assertTrue(
            LeadEvent::where('lead_id', $lead->id)->where('event_type', 'validation.failed')->exists()
        );
    }

    public function test_field_validation_failure_quarantines_when_configured(): void
    {
        $campaign = Campaign::withoutGlobalScopes()->with('fields')->where('reference', 'loans-uk')->first();
        $this->assertNotNull($campaign);

        $campaign->update([
            'validation_config' => array_merge($campaign->validation_config ?? [], [
                'quarantine_on_validation_fail' => true,
                'require_email' => true,
            ]),
        ]);

        $fields = $this->validFieldData($campaign);
        unset($fields['email']);

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => $fields,
            'received_at' => now(),
        ]);

        app(LeadPipeline::class)->process($lead->fresh());

        $lead->refresh();
        $this->assertSame(LeadStatus::Quarantined, $lead->status);
        $this->assertSame('validation', $lead->metadata['quarantine_reason'] ?? null);
        $this->assertNotEmpty($lead->metadata['field_validation'] ?? null);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    protected function validFieldData(Campaign $campaign, array $overrides = []): array
    {
        $fields = [];
        foreach ($campaign->fields as $field) {
            $fields[$field->name] = match ($field->name) {
                'email' => 'valid@example.com',
                'phone1' => '07700900123',
                'zipcode' => 'SW1A 1AA',
                'vehicle_year', 'loan_amount', 'monthly_income' => '10000',
                default => 'Test',
            };
        }

        return array_merge($fields, $overrides);
    }
}
