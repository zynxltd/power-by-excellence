<?php

namespace App\Services\Rules;

class RuleEngine
{
    public function matches(?array $rules, array $data): bool
    {
        return $this->firstFailingCondition($rules, $data) === null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function firstFailingCondition(?array $rules, array $data): ?array
    {
        if (empty($rules)) {
            return null;
        }

        $operator = $rules['operator'] ?? 'and';
        $conditions = $rules['conditions'] ?? $rules;

        if (! isset($rules['operator']) && isset($rules['field'])) {
            return $this->evaluateCondition($rules, $data) ? null : $rules;
        }

        if (! isset($rules['operator']) && isset($rules[0])) {
            return $this->firstFailingInGroup('and', $rules, $data);
        }

        return $this->firstFailingInGroup($operator, $conditions, $data);
    }

    /**
     * @return list<string>
     */
    public function summarizeRules(?array $rules): array
    {
        if (empty($rules)) {
            return [];
        }

        $operator = strtoupper($rules['operator'] ?? 'AND');
        $conditions = $rules['conditions'] ?? [];

        if (! is_array($conditions) || $conditions === []) {
            return [];
        }

        return collect($conditions)
            ->map(fn (array $condition) => $this->describeCondition($condition))
            ->map(fn (string $line, int $index) => $index > 0 ? "{$operator} {$line}" : $line)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    public function describeCondition(array $condition, ?array $data = null): string
    {
        if (isset($condition['operator'], $condition['conditions'])) {
            $inner = collect($condition['conditions'])
                ->map(fn (array $c) => $this->describeCondition($c, $data))
                ->implode(' '.strtoupper($condition['operator']).' ');

            return "({$inner})";
        }

        $field = $condition['field'] ?? 'field';
        $op = $condition['op'] ?? 'eq';
        $expected = $condition['value'] ?? '';
        $actual = $data ? data_get($data, $field) : null;

        $label = match ($op) {
            'eq', '=' => "{$field} equals \"{$expected}\"",
            'neq', '!=' => "{$field} is not \"{$expected}\"",
            'gt', '>' => "{$field} is greater than {$expected}",
            'gte', '>=' => "{$field} is at least {$expected}",
            'lt', '<' => "{$field} is less than {$expected}",
            'lte', '<=' => "{$field} is at most {$expected}",
            'in' => "{$field} is one of: ".$this->valueListLabel($expected),
            'not_in' => "{$field} is not one of: ".$this->valueListLabel($expected),
            'contains' => "{$field} contains \"{$expected}\"",
            'regex' => "{$field} matches pattern {$expected}",
            'exists' => "{$field} has a value",
            'empty' => "{$field} is empty",
            default => "{$field} {$op} {$expected}",
        };

        if ($data !== null && ! $this->evaluateCondition($condition, $data)) {
            $actualDisplay = $actual === null || $actual === '' ? 'empty' : (string) $actual;

            return "{$label} (lead value: {$actualDisplay})";
        }

        return $label;
    }

    /**
     * @param  list<array<string, mixed>>  $conditions
     * @return array<string, mixed>|null
     */
    protected function firstFailingInGroup(string $operator, array $conditions, array $data): ?array
    {
        if ($operator === 'or') {
            $anyMatch = false;
            $last = null;

            foreach ($conditions as $condition) {
                if ($this->evaluateCondition($condition, $data)) {
                    $anyMatch = true;
                    break;
                }
                $last = $condition;
            }

            return $anyMatch ? null : ($last ?? null);
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($condition, $data)) {
                return $condition;
            }
        }

        return null;
    }

    protected function evaluateGroup(string $operator, array $conditions, array $data): bool
    {
        return $this->firstFailingInGroup($operator, $conditions, $data) === null;
    }

    protected function evaluateCondition(array $condition, array $data): bool
    {
        if (isset($condition['operator'], $condition['conditions'])) {
            return $this->evaluateGroup($condition['operator'], $condition['conditions'], $data);
        }

        $field = $condition['field'] ?? null;
        $op = $condition['op'] ?? 'eq';
        $expected = $condition['value'] ?? null;
        $actual = data_get($data, $field);

        return match ($op) {
            'eq', '=' => (string) $actual === (string) $expected,
            'neq', '!=' => (string) $actual !== (string) $expected,
            'gt', '>' => is_numeric($actual) && (float) $actual > (float) $expected,
            'gte', '>=' => is_numeric($actual) && (float) $actual >= (float) $expected,
            'lt', '<' => is_numeric($actual) && (float) $actual < (float) $expected,
            'lte', '<=' => is_numeric($actual) && (float) $actual <= (float) $expected,
            'in' => in_array((string) $actual, $this->valueList($expected), true),
            'not_in' => ! in_array((string) $actual, $this->valueList($expected), true),
            'contains' => str_contains((string) $actual, (string) $expected),
            'regex' => $this->matchesRegex((string) $expected, (string) $actual),
            'exists' => filled($actual),
            'empty' => blank($actual),
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    protected function valueList(mixed $expected): array
    {
        if (is_array($expected)) {
            return array_map('strval', $expected);
        }

        return array_values(array_filter(array_map(
            static fn (string $part) => trim($part),
            explode(',', (string) $expected)
        ), static fn (string $part) => $part !== ''));
    }

    protected function valueListLabel(mixed $expected): string
    {
        return implode(', ', $this->valueList($expected));
    }

    protected function matchesRegex(string $pattern, string $actual): bool
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return false;
        }

        if (! preg_match('/^\/.+\/[imsxADSUXJu]*$/', $pattern)) {
            $pattern = '/'.$pattern.'/i';
        }

        return (bool) @preg_match($pattern, $actual);
    }
}
