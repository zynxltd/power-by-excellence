const OPERATOR_LABELS = {
    eq: 'equals',
    neq: 'does not equal',
    in: 'is one of',
    not_in: 'is not one of',
    contains: 'contains',
    regex: 'matches regex',
    gt: 'is greater than',
    gte: 'is at least',
    lt: 'is less than',
    lte: 'is at most',
    exists: 'has a value',
    empty: 'is empty',
};

export function normalizeRules(rules) {
    if (!rules) {
        return { operator: 'and', conditions: [] };
    }
    if (!rules.conditions) {
        return { operator: rules.operator ?? 'and', conditions: [] };
    }
    if (!rules.operator) {
        return { ...rules, operator: 'and' };
    }
    return rules;
}

export function describeCondition(condition) {
    if (!condition?.field) {
        return '';
    }

    const op = OPERATOR_LABELS[condition.op] ?? condition.op;
    const value = condition.value ?? '';

    if (condition.op === 'exists' || condition.op === 'empty') {
        return `${condition.field} ${op}`;
    }

    if (condition.op === 'in' || condition.op === 'not_in') {
        return `${condition.field} ${op} (${String(value).replace(/,/g, ', ')})`;
    }

    return `${condition.field} ${op} "${value}"`;
}

export function summarizeRules(rules) {
    const normalized = normalizeRules(rules);
    if (!normalized.conditions?.length) {
        return [];
    }

    const joiner = normalized.operator === 'or' ? ' OR ' : ' AND ';

    return normalized.conditions.map((condition, index) => {
        const line = describeCondition(condition);
        return index === 0 ? line : `${joiner}${line}`;
    });
}

export function rulesSummaryText(rules) {
    const lines = summarizeRules(rules);
    if (!lines.length) {
        return null;
    }

    return lines.join('');
}

export function hasActiveRules(rules) {
    return (normalizeRules(rules).conditions?.length ?? 0) > 0;
}

export const FILTER_PRESETS = [
    { key: 'postcode', field: 'postcode', op: 'contains', value: 'SW', label: 'UK postcode prefix' },
    { key: 'zipcode', field: 'zipcode', op: 'contains', value: '902', label: 'US zip prefix' },
    { key: 'loan_min', field: 'loan_amount', op: 'gte', value: '5000', label: 'Min loan amount' },
    { key: 'state', field: 'state', op: 'in', value: 'CA,TX,FL', label: 'Allowed states' },
    { key: 'email', field: 'email', op: 'contains', value: '@gmail.com', label: 'Email contains' },
    { key: 'phone', field: 'phone', op: 'exists', value: '', label: 'Phone provided' },
    { key: 'make_bmw', field: 'vehicle_make', op: 'regex', value: '/^BMW/i', label: 'Vehicle make BMW' },
];

export { OPERATOR_LABELS };
