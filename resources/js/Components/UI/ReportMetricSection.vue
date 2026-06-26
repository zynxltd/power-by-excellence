<script setup>
import Panel from '@/Components/UI/Panel.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    items: { type: Array, required: true },
});

const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${props.items.length}, minmax(6.5rem, 1fr))`,
}));

const valueClass = (accent) => {
    const map = {
        emerald: 'text-emerald-600 dark:text-emerald-400',
        amber: 'text-amber-600 dark:text-amber-400',
        rose: 'text-rose-600 dark:text-rose-400',
        orange: 'text-orange-600 dark:text-orange-400',
        cyan: 'text-cyan-600 dark:text-cyan-400',
        indigo: 'text-indigo-600 dark:text-indigo-400',
        violet: 'text-violet-600 dark:text-violet-400',
    };

    return map[accent] ?? 'text-slate-900 dark:text-white';
};

const accentGlow = (accent) => {
    const map = {
        emerald: 'from-emerald-500/8 to-transparent',
        amber: 'from-amber-500/8 to-transparent',
        rose: 'from-rose-500/8 to-transparent',
        orange: 'from-orange-500/8 to-transparent',
        cyan: 'from-cyan-500/8 to-transparent',
        indigo: 'from-indigo-500/8 to-transparent',
        violet: 'from-violet-500/8 to-transparent',
    };

    return map[accent] ?? 'from-slate-500/5 to-transparent';
};
</script>

<template>
    <Panel class="mb-4">
        <template #header>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ title }}</h3>
                <p v-if="description" class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ description }}</p>
            </div>
        </template>

        <div class="overflow-x-auto">
            <div
                class="grid min-w-full gap-2"
                :style="gridStyle"
            >
                <component
                    :is="item.href ? Link : 'div'"
                    v-for="item in items"
                    :key="item.label"
                    :href="item.href ?? undefined"
                    :title="item.title ?? undefined"
                    :class="[
                        'relative min-w-0 overflow-hidden rounded-xl border border-slate-200/80 bg-gradient-to-br to-white p-2.5 dark:border-slate-800 dark:to-slate-900',
                        accentGlow(item.accent),
                        item.href ? 'cursor-pointer transition hover:border-indigo-200 hover:shadow-md dark:hover:border-indigo-700' : '',
                    ]"
                >
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {{ item.label }}
                    </p>
                    <p
                        :class="[
                            'mt-1 truncate text-lg font-bold tabular-nums leading-tight tracking-tight',
                            valueClass(item.accent),
                        ]"
                    >
                        {{ item.value }}
                    </p>
                    <p
                        v-if="item.href"
                        class="mt-1 truncate text-[10px] font-medium text-indigo-600 dark:text-indigo-400"
                    >
                        View →
                    </p>
                </component>
            </div>
        </div>
    </Panel>
</template>
