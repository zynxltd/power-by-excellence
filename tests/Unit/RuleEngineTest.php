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
}
