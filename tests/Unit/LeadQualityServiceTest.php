<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Services\Leads\LeadQualityService;
use Tests\TestCase;

class LeadQualityServiceTest extends TestCase
{
    public function test_perfect_lead_scores_excellent(): void
    {
        $analysis = LeadQualityService::analyze([
            'quality_score' => 95,
            'email_validation' => ['passed' => true, 'status' => 'deliverable'],
            'hlr_validation' => ['passed' => true, 'status' => 'active'],
        ], [
            'email' => 'good@test.test',
            'phone1' => '07700900123',
            'zipcode' => 'SW1A 1AA',
            'lastname' => 'Smith',
        ]);

        $this->assertSame(95, $analysis['score']);
        $this->assertSame('excellent', $analysis['grade']);
        $this->assertTrue($analysis['email']['passed']);
        $this->assertTrue($analysis['hlr']['passed']);
        $this->assertSame('complete', $analysis['completeness']['status']);
    }

    public function test_failed_email_and_hlr_reduce_score(): void
    {
        $unchecked = ['status' => 'unchecked', 'passed' => null, 'fraud_score' => null];

        $score = LeadQualityService::scoreFromChecks(
            ['status' => 'failed', 'passed' => false, 'fraud_score' => null],
            ['status' => 'failed', 'passed' => false, 'fraud_score' => null],
            $unchecked,
            ['missing' => []],
        );

        $this->assertSame(45, $score);
    }

    public function test_failed_ip_reduces_score(): void
    {
        $unchecked = ['status' => 'unchecked', 'passed' => null, 'fraud_score' => null];
        $passed = ['status' => 'passed', 'passed' => true, 'fraud_score' => 10];

        $score = LeadQualityService::scoreFromChecks(
            $passed,
            $passed,
            ['status' => 'failed', 'passed' => false, 'fraud_score' => 90],
            ['missing' => []],
        );

        $this->assertSame(90, $score);
    }

    public function test_missing_fields_reduce_score(): void
    {
        $unchecked = ['status' => 'unchecked', 'passed' => null, 'fraud_score' => null];

        $score = LeadQualityService::scoreFromChecks(
            $unchecked,
            $unchecked,
            $unchecked,
            ['missing' => ['phone1', 'zipcode']],
        );

        $this->assertSame(90, $score);
    }

    public function test_analyze_lead_uses_stored_quality_score_when_present(): void
    {
        $lead = new Lead([
            'metadata' => [
                'quality_score' => 72,
                'email_validation' => ['passed' => true],
            ],
            'field_data' => ['email' => 'stored@test.test'],
        ]);

        $analysis = LeadQualityService::analyzeLead($lead);

        $this->assertSame(72, $analysis['score']);
        $this->assertSame('good', $analysis['grade']);
    }

    public function test_grade_buckets(): void
    {
        $this->assertSame('excellent', LeadQualityService::grade(80));
        $this->assertSame('good', LeadQualityService::grade(60));
        $this->assertSame('fair', LeadQualityService::grade(40));
        $this->assertSame('poor', LeadQualityService::grade(39));
    }
}
