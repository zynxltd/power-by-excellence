<?php

namespace Tests\Unit;

use App\Services\Rules\RuleEngine;
use PHPUnit\Framework\TestCase;

class RuleEngineTest extends TestCase
{
    public function test_and_conditions(): void
    {
        $engine = new RuleEngine;

        $rules = [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'age', 'op' => 'gte', 'value' => 21],
                ['field' => 'state', 'op' => 'eq', 'value' => 'TX'],
            ],
        ];

        $this->assertTrue($engine->matches($rules, ['age' => 25, 'state' => 'TX']));
        $this->assertFalse($engine->matches($rules, ['age' => 18, 'state' => 'TX']));
    }

    public function test_in_operator(): void
    {
        $engine = new RuleEngine;

        $rules = ['field' => 'state', 'op' => 'in', 'value' => ['TX', 'CA']];

        $this->assertTrue($engine->matches($rules, ['state' => 'CA']));
        $this->assertFalse($engine->matches($rules, ['state' => 'NY']));
    }

    public function test_in_operator_accepts_comma_separated_string(): void
    {
        $engine = new RuleEngine;

        $rules = ['field' => 'state', 'op' => 'in', 'value' => 'CA, TX, FL'];

        $this->assertTrue($engine->matches($rules, ['state' => 'TX']));
        $this->assertFalse($engine->matches($rules, ['state' => 'NY']));
    }

    public function test_first_failing_condition(): void
    {
        $engine = new RuleEngine;

        $rules = [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'state', 'op' => 'eq', 'value' => 'CA'],
                ['field' => 'loan_amount', 'op' => 'gte', 'value' => 5000],
            ],
        ];

        $failed = $engine->firstFailingCondition($rules, ['state' => 'TX', 'loan_amount' => 10000]);

        $this->assertSame('state', $failed['field']);
        $this->assertStringContainsString('TX', $engine->describeCondition($failed, ['state' => 'TX', 'loan_amount' => 10000]));
    }

    public function test_summarize_rules(): void
    {
        $engine = new RuleEngine;

        $summary = $engine->summarizeRules([
            'operator' => 'and',
            'conditions' => [
                ['field' => 'state', 'op' => 'in', 'value' => 'CA,TX'],
            ],
        ]);

        $this->assertSame(['state is one of: CA, TX'], $summary);
    }
}
