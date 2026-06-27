<script setup>
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { computed } from 'vue';

const props = defineProps({
    checklist: { type: Array, default: () => [] },
});

const completedCount = computed(() => props.checklist.filter((item) => item.complete).length);
const allComplete = computed(() => props.checklist.length > 0 && completedCount.value === props.checklist.length);
</script>

<template>
    <Panel v-if="checklist?.length" title="Go-live checklist" class="mb-6">
        <template #header>
            <span
                :class="[
                    'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    allComplete
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                ]"
            >
                {{ completedCount }}/{{ checklist.length }} complete
            </span>
        </template>

        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Complete these steps before expecting live lead flow on this campaign.
        </p>

        <ul class="space-y-2">
            <li
                v-for="item in checklist"
                :key="item.key"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700"
            >
                <div class="flex items-start gap-3">
                    <span
                        :class="[
                            'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                            item.complete
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-400 dark:bg-slate-800',
                        ]"
                    >
                        <svg v-if="item.complete" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else>·</span>
                    </span>
                    <span :class="['text-sm', item.complete ? 'text-slate-600 dark:text-slate-400' : 'font-medium text-slate-900 dark:text-white']">
                        {{ item.label }}
                    </span>
                </div>
                <AppButton v-if="item.route && !item.complete" :href="item.route" variant="secondary" class="!px-3 !py-1.5 !text-xs">
                    Set up
                </AppButton>
            </li>
        </ul>
    </Panel>
</template>
