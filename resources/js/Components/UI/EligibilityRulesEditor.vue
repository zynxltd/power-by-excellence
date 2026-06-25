<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed } from 'vue';
import { FILTER_PRESETS, hasActiveRules, normalizeRules, rulesSummaryText } from '@/utils/ruleFormat';

const props = defineProps({
    fieldOptions: { type: Array, default: () => [] },
    scope: { type: String, default: 'tier' }, // tier | delivery
});

const model = defineModel({ type: Object, default: () => ({ operator: 'and', conditions: [] }) });

const operators = [
    { value: 'eq', label: 'equals' },
    { value: 'neq', label: 'does not equal' },
    { value: 'in', label: 'is one of (comma-separated)' },
    { value: 'not_in', label: 'is not one of' },
    { value: 'contains', label: 'contains' },
    { value: 'gt', label: 'greater than' },
    { value: 'gte', label: 'at least' },
    { value: 'lt', label: 'less than' },
    { value: 'lte', label: 'at most' },
    { value: 'exists', label: 'has a value' },
    { value: 'empty', label: 'is empty' },
];

const scopeCopy = computed(() => (
    props.scope === 'delivery'
        ? {
            intro: 'Only leads matching these rules will be pinged/posted on this delivery.',
            empty: 'No filters — all leads that reach this delivery are eligible.',
            summaryTitle: 'Delivery will only accept leads where:',
        }
        : {
            intro: 'Leads must match these rules before this tier is tried. Per-delivery filters are configured on each delivery.',
            empty: 'No filters — every lead can enter this tier.',
            summaryTitle: 'This tier only accepts leads where:',
        }
));

const summaryText = computed(() => rulesSummaryText(model.value));

const ensureShape = () => {
    model.value = normalizeRules(model.value);
};

const addCondition = (preset = null) => {
    ensureShape();
    const defaultField = preset?.field ?? props.fieldOptions?.[0]?.name ?? '';
    model.value.conditions.push({
        field: defaultField,
        op: preset?.op ?? 'eq',
        value: preset?.value ?? '',
    });
};

const addPreset = (preset) => addCondition(preset);

const addFieldQuick = (fieldName) => {
    ensureShape();
    model.value.conditions.push({ field: fieldName, op: 'eq', value: '' });
};

const removeCondition = (i) => {
    ensureShape();
    model.value.conditions.splice(i, 1);
};

const valuePlaceholder = (op) => {
    if (op === 'in' || op === 'not_in') {
        return 'CA, TX, FL — comma-separated';
    }
    if (op === 'contains') {
        return 'e.g. @gmail.com or SW';
    }
    return 'Value to match';
};

const needsValue = (op) => !['exists', 'empty'].includes(op);

ensureShape();
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            {{ scopeCopy.intro }}
            Filters check <strong>lead field values</strong> at ping/post time — use the same field names as your campaign schema
            <span v-if="fieldOptions?.length">({{ fieldOptions.length }} fields available)</span>.
        </p>

        <div
            v-if="hasActiveRules(model)"
            class="rounded-lg border border-amber-200 bg-amber-50/80 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/30"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">
                {{ scopeCopy.summaryTitle }}
            </p>
            <p class="mt-1 text-sm font-medium text-amber-900 dark:text-amber-100">
                {{ summaryText }}
            </p>
        </div>

        <div v-if="fieldOptions?.length" class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quick add by field</p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="f in fieldOptions.slice(0, 12)"
                    :key="f.name"
                    type="button"
                    class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-600 dark:hover:bg-indigo-950/40"
                    @click="addFieldQuick(f.name)"
                >
                    + {{ f.label || f.name }}
                </button>
            </div>
        </div>

        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Common presets</p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="preset in FILTER_PRESETS"
                    :key="preset.key"
                    type="button"
                    class="rounded-full border border-dashed border-slate-300 px-2.5 py-1 text-xs text-slate-600 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-slate-600 dark:text-slate-400"
                    @click="addPreset(preset)"
                >
                    + {{ preset.label }}
                </button>
            </div>
        </div>

        <div v-if="model.conditions?.length > 1" class="flex items-center gap-3">
            <InputLabel value="Match logic" class="!mb-0 shrink-0" />
            <div class="inline-flex rounded-lg border border-slate-200 p-0.5 text-xs dark:border-slate-700">
                <button
                    type="button"
                    :class="[
                        'rounded-md px-3 py-1.5 font-semibold transition',
                        model.operator === 'and' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400',
                    ]"
                    @click="model.operator = 'and'"
                >
                    All conditions (AND)
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-md px-3 py-1.5 font-semibold transition',
                        model.operator === 'or' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400',
                    ]"
                    @click="model.operator = 'or'"
                >
                    Any condition (OR)
                </button>
            </div>
        </div>

        <div v-if="!model.conditions?.length" class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-600">
            {{ scopeCopy.empty }}
        </div>

        <div
            v-for="(cond, i) in model.conditions"
            :key="i"
            class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700"
        >
            <span
                v-if="i > 0"
                class="w-full pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400"
            >
                {{ model.operator === 'or' ? 'OR' : 'AND' }}
            </span>
            <div class="min-w-[7rem] flex-1">
                <InputLabel value="Field" />
                <select v-if="fieldOptions?.length" v-model="cond.field" class="form-select mt-1 w-full">
                    <option value="" disabled>Select field…</option>
                    <option v-for="f in fieldOptions" :key="f.name" :value="f.name">{{ f.label || f.name }}</option>
                </select>
                <TextInput v-else v-model="cond.field" class="mt-1 w-full" placeholder="state, zipcode…" />
            </div>
            <div class="min-w-[7rem]">
                <InputLabel value="Operator" />
                <select v-model="cond.op" class="form-select mt-1 w-full">
                    <option v-for="op in operators" :key="op.value" :value="op.value">{{ op.label }}</option>
                </select>
            </div>
            <div v-if="needsValue(cond.op)" class="min-w-[7rem] flex-1">
                <InputLabel value="Value" />
                <TextInput v-model="cond.value" class="mt-1 w-full" :placeholder="valuePlaceholder(cond.op)" />
            </div>
            <button type="button" class="pb-2 text-sm text-rose-500 hover:text-rose-600" @click="removeCondition(i)">
                Remove
            </button>
        </div>

        <button
            type="button"
            class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300 dark:hover:bg-indigo-950/60"
            @click="addCondition()"
        >
            + Add filter condition
        </button>
    </div>
</template>
