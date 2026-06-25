<?php

namespace App\Services\Rules;

class RuleEngine
{
    public function matches(?array $rules, array $data): bool
    {
        if (empty($rules)) {
            return true;
        }

        $operator = $rules['operator'] ?? 'and';
        $conditions = $rules['conditions'] ?? $rules;

        if (! isset($rules['operator']) && isset($rules['field'])) {
            return $this->evaluateCondition($rules, $data);
        }

        if (! isset($rules['operator']) && isset($rules[0])) {
            return $this->evaluateGroup('and', $rules, $data);
        }

        return $this->evaluateGroup($operator, $conditions, $data);
    }

    protected function evaluateGroup(string $operator, array $conditions, array $data): bool
    {
        if ($operator === 'or') {
            foreach ($conditions as $condition) {
                if ($this->evaluateCondition($condition, $data)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($condition, $data)) {
                return false;
            }
        }

        return true;
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
            'in' => in_array((string) $actual, array_map('strval', (array) $expected), true),
            'not_in' => ! in_array((string) $actual, array_map('strval', (array) $expected), true),
            'contains' => str_contains((string) $actual, (string) $expected),
            'regex' => (bool) @preg_match($expected, (string) $actual),
            'exists' => filled($actual),
            'empty' => blank($actual),
            default => false,
        };
    }
}
