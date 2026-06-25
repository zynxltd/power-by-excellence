<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    fieldOptions: { type: Array, default: () => [] },
});

const model = defineModel({ type: Object, default: () => ({ operator: 'and', conditions: [] }) });

const operators = [
    { value: 'eq', label: 'equals' },
    { value: 'neq', label: 'not equals' },
    { value: 'in', label: 'in list' },
    { value: 'not_in', label: 'not in list' },
    { value: 'contains', label: 'contains' },
    { value: 'gt', label: 'greater than' },
    { value: 'gte', label: '≥' },
    { value: 'lt', label: 'less than' },
    { value: 'exists', label: 'has value' },
    { value: 'empty', label: 'is empty' },
];

const ensureShape = () => {
    if (!model.value) model.value = { operator: 'and', conditions: [] };
    if (!model.value.conditions) model.value.conditions = [];
    if (!model.value.operator) model.value.operator = 'and';
};

const addCondition = () => {
    ensureShape();
    const defaultField = props.fieldOptions?.[0]?.name ?? 'state';
    model.value.conditions.push({ field: defaultField, op: 'eq', value: '' });
};

const removeCondition = (i) => {
    ensureShape();
    model.value.conditions.splice(i, 1);
};

ensureShape();
</script>

<template>
    <div class="space-y-3">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Filters evaluate <strong>lead field values</strong> at ping/post time — use the same field names as your campaign schema and API spec
            <span v-if="fieldOptions?.length">({{ fieldOptions.length }} fields available)</span>.
            Leave empty to accept all leads in this tier.
        </p>
        <div v-if="!model.conditions?.length" class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-600">
            No filters — all leads in this tier can be pinged/posted.
        </div>
        <div
            v-for="(cond, i) in model.conditions"
            :key="i"
            class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700"
        >
            <div class="min-w-[7rem] flex-1">
                <InputLabel value="Field" />
                <select v-if="fieldOptions?.length" v-model="cond.field" class="form-select mt-1 w-full">
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
            <div v-if="!['exists', 'empty'].includes(cond.op)" class="min-w-[7rem] flex-1">
                <InputLabel value="Value" />
                <TextInput v-model="cond.value" class="mt-1 w-full" placeholder="CA or SW,EC1" />
            </div>
            <button type="button" class="pb-2 text-sm text-rose-500" @click="removeCondition(i)">Remove</button>
        </div>
        <button type="button" class="text-sm font-medium text-indigo-600 hover:underline" @click="addCondition">+ Add filter condition</button>
    </div>
</template>
