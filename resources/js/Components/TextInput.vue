<script setup>
import { onMounted, ref } from 'vue';

defineOptions({ inheritAttrs: false });

const model = defineModel({
    type: [String, Number],
    default: '',
});

const input = ref(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        v-bind="$attrs"
        ref="input"
        v-model="model"
        class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 shadow-sm placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500"
        :class="{ 'border-rose-500 ring-1 ring-rose-500/30': $attrs['aria-invalid'] === true || $attrs['aria-invalid'] === 'true' }"
    />
</template>
