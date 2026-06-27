<script setup>
import TextInput from '@/Components/TextInput.vue';
import { computed, ref, watch } from 'vue';
import { FILTER_PRESETS, hasActiveRules, normalizeRules, rulesSummaryText } from '@/utils/ruleFormat';

const props = defineProps({
    fieldOptions: { type: Array, default: () => [] },
    scope: { type: String, default: 'tier' }, // tier | delivery
    compact: { type: Boolean, default: false },
});

const model = defineModel({ type: Object, default: () => ({ operator: 'and', conditions: [] }) });

const valueInput = ref(null);

const operators = [
    { value: 'eq', label: 'equals' },
    { value: 'neq', label: 'does not equal' },
    { value: 'in', label: 'is one of' },
    { value: 'not_in', label: 'is not one of' },
    { value: 'contains', label: 'contains' },
    { value: 'regex', label: 'matches regex' },
    { value: 'gt', label: 'greater than' },
    { value: 'gte', label: 'at least' },
    { value: 'lt', label: 'less than' },
    { value: 'lte', label: 'at most' },
    { value: 'exists', label: 'has a value' },
    { value: 'empty', label: 'is empty' },
];

const quickAdd = ref({
    field: '',
    op: 'eq',
    value: '',
});

const showPresets = ref(false);

const scopeCopy = computed(() => (
    props.scope === 'delivery'
        ? {
            intro: 'Only leads matching these rules will be pinged/posted on this delivery.',
            empty: 'No filters - all leads that reach this delivery are eligible.',
            summaryTitle: 'Delivery will only accept leads where:',
        }
        : {
            intro: props.compact
                ? 'Only leads matching these rules enter this tier.'
                : 'Leads must match these rules before this tier is tried. Per-delivery filters are configured on each delivery.',
            empty: 'No filters - every lead can enter this tier.',
            summaryTitle: 'This tier only accepts leads where:',
        }
));

const summaryText = computed(() => rulesSummaryText(model.value));

const ensureShape = () => {
    model.value = normalizeRules(model.value);
};

const defaultOpForField = (fieldName) => {
    const field = props.fieldOptions?.find((f) => f.name === fieldName);
    const type = field?.type ?? '';
    const name = String(fieldName).toLowerCase();

    if (name.includes('postcode') || name.includes('zip')) {
        return 'contains';
    }
    if (type === 'number' || name.includes('amount') || name.includes('year') || name.includes('price')) {
        return 'gte';
    }
    if (type === 'email' || name === 'email') {
        return 'contains';
    }
    if (type === 'phone' || name.includes('phone')) {
        return 'exists';
    }

    return 'eq';
};

const valuePlaceholder = (op, fieldName = '') => {
    if (op === 'in' || op === 'not_in') {
        return 'CA, TX, FL';
    }
    if (op === 'contains') {
        if (String(fieldName).toLowerCase().includes('postcode') || String(fieldName).toLowerCase().includes('zip')) {
            return 'e.g. SW1';
        }
        return 'e.g. @gmail.com';
    }
    if (op === 'regex') {
        return 'e.g. /^SW\\d+/ or ^BMW';
    }
    if (op === 'gte' || op === 'gt') {
        return 'Minimum value';
    }
    return 'Value';
};

const needsValue = (op) => !['exists', 'empty'].includes(op);

const canSubmitQuickAdd = computed(() => {
    if (!quickAdd.value.field) {
        return false;
    }

    return needsValue(quickAdd.value.op) ? String(quickAdd.value.value).trim() !== '' : true;
});

const addCondition = (preset = null) => {
    ensureShape();
    const defaultField = preset?.field ?? props.fieldOptions?.[0]?.name ?? '';
    model.value.conditions.push({
        field: defaultField,
        op: preset?.op ?? 'eq',
        value: preset?.value ?? '',
    });
};

const submitQuickAdd = () => {
    if (!canSubmitQuickAdd.value) {
        return;
    }

    addCondition({
        field: quickAdd.value.field,
        op: quickAdd.value.op,
        value: quickAdd.value.value,
    });

    const field = quickAdd.value.field;
    quickAdd.value = {
        field,
        op: defaultOpForField(field),
        value: '',
    };

    valueInput.value?.focus();
};

const pickField = (fieldName) => {
    quickAdd.value = {
        field: fieldName,
        op: defaultOpForField(fieldName),
        value: '',
    };
    valueInput.value?.focus();
};

const addPreset = (preset) => {
    addCondition(preset);
};

const removeCondition = (i) => {
    ensureShape();
    model.value.conditions.splice(i, 1);
};

watch(
    () => quickAdd.value.field,
    (field) => {
        if (field) {
            quickAdd.value.op = defaultOpForField(field);
        }
    },
);

ensureShape();
</script>

<template>
    <div class="space-y-3">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            {{ scopeCopy.intro }}
            <span v-if="!compact && fieldOptions?.length" class="text-slate-500">
                ({{ fieldOptions.length }} campaign fields)
            </span>
        </p>

        <div
            v-if="hasActiveRules(model)"
            class="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 dark:border-amber-900/50 dark:bg-amber-950/30"
        >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">
                {{ scopeCopy.summaryTitle }}
            </p>
            <p class="mt-0.5 text-sm font-medium text-amber-900 dark:text-amber-100">
                {{ summaryText }}
            </p>
        </div>

        <div class="rounded-xl border border-indigo-200 bg-indigo-50/40 p-3 dark:border-indigo-900/50 dark:bg-indigo-950/20">
            <p class="mb-2 text-xs font-semibold text-indigo-900 dark:text-indigo-200">Add filter</p>
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-[8rem] flex-1">
                    <label class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Field</label>
                    <select v-if="fieldOptions?.length" v-model="quickAdd.field" class="form-select w-full text-sm">
                        <option value="" disabled>Choose field…</option>
                        <option v-for="f in fieldOptions" :key="f.name" :value="f.name">{{ f.label || f.name }}</option>
                    </select>
                    <TextInput v-else v-model="quickAdd.field" class="w-full text-sm" placeholder="field_name" />
                </div>
                <div class="min-w-[7rem]">
                    <label class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Rule</label>
                    <select v-model="quickAdd.op" class="form-select w-full text-sm">
                        <option v-for="op in operators" :key="op.value" :value="op.value">{{ op.label }}</option>
                    </select>
                </div>
                <div v-if="needsValue(quickAdd.op)" class="min-w-[8rem] flex-1">
                    <label class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-slate-500">Value</label>
                    <TextInput
                        ref="valueInput"
                        v-model="quickAdd.value"
                        class="w-full text-sm"
                        :placeholder="valuePlaceholder(quickAdd.op, quickAdd.field)"
                        @keyup.enter="submitQuickAdd"
                    />
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canSubmitQuickAdd"
                    @click="submitQuickAdd"
                >
                    Add
                </button>
            </div>

            <div v-if="fieldOptions?.length" class="mt-3">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Quick pick field</p>
                <div class="flex flex-wrap gap-1">
                    <button
                        v-for="f in fieldOptions.slice(0, 14)"
                        :key="f.name"
                        type="button"
                        :class="[
                            'rounded-full border px-2 py-0.5 text-xs font-medium transition',
                            quickAdd.field === f.name
                                ? 'border-indigo-400 bg-indigo-100 text-indigo-800 dark:border-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-200'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300',
                        ]"
                        @click="pickField(f.name)"
                    >
                        {{ f.label || f.name }}
                    </button>
                </div>
            </div>

            <div class="mt-2">
                <button
                    type="button"
                    class="text-xs font-medium text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400"
                    @click="showPresets = !showPresets"
                >
                    {{ showPresets ? 'Hide' : 'Show' }} common presets
                </button>
                <div v-if="showPresets" class="mt-1.5 flex flex-wrap gap-1">
                    <button
                        v-for="preset in FILTER_PRESETS"
                        :key="preset.key"
                        type="button"
                        class="rounded-full border border-dashed border-slate-300 px-2 py-0.5 text-xs text-slate-600 transition hover:border-indigo-400 hover:bg-white hover:text-indigo-700 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-800"
                        @click="addPreset(preset)"
                    >
                        + {{ preset.label }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="model.conditions?.length > 1" class="flex items-center gap-2">
            <span class="text-xs font-medium text-slate-500">Match</span>
            <div class="inline-flex rounded-lg border border-slate-200 p-0.5 text-xs dark:border-slate-700">
                <button
                    type="button"
                    :class="[
                        'rounded-md px-2.5 py-1 font-semibold transition',
                        model.operator === 'and' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400',
                    ]"
                    @click="model.operator = 'and'"
                >
                    All (AND)
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-md px-2.5 py-1 font-semibold transition',
                        model.operator === 'or' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400',
                    ]"
                    @click="model.operator = 'or'"
                >
                    Any (OR)
                </button>
            </div>
        </div>

        <div v-if="!model.conditions?.length" class="rounded-lg border border-dashed border-slate-300 px-3 py-2.5 text-sm text-slate-500 dark:border-slate-600">
            {{ scopeCopy.empty }}
        </div>

        <ul v-else class="space-y-1.5">
            <li
                v-for="(cond, i) in model.conditions"
                :key="i"
                class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-900/40"
            >
                <span
                    v-if="i > 0"
                    class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-500 dark:bg-slate-800"
                >
                    {{ model.operator === 'or' ? 'OR' : 'AND' }}
                </span>
                <div class="grid min-w-0 flex-1 grid-cols-[minmax(0,1.5fr)_minmax(7rem,9rem)_minmax(0,1fr)] items-center gap-2">
                    <select v-if="fieldOptions?.length" v-model="cond.field" class="form-select min-w-0 w-full text-xs">
                        <option v-for="f in fieldOptions" :key="f.name" :value="f.name">{{ f.label || f.name }}</option>
                    </select>
                    <TextInput v-else v-model="cond.field" class="min-w-0 w-full text-xs" />
                    <select v-model="cond.op" class="form-select min-w-0 w-full text-xs">
                        <option v-for="op in operators" :key="op.value" :value="op.value">{{ op.label }}</option>
                    </select>
                    <TextInput
                        v-if="needsValue(cond.op)"
                        v-model="cond.value"
                        class="min-w-0 w-full text-xs"
                        :placeholder="valuePlaceholder(cond.op, cond.field)"
                    />
                    <span v-else class="text-xs text-slate-400">-</span>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded px-1.5 py-0.5 text-xs text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30"
                    title="Remove filter"
                    @click="removeCondition(i)"
                >
                    Remove
                </button>
            </li>
        </ul>
    </div>
</template>
