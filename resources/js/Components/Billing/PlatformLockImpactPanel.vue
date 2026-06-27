<script setup>
defineProps({
    compact: { type: Boolean, default: false },
});

const rows = [
    {
        status: 'Active',
        ingest: 'Accepting',
        processing: 'Operational',
        admin: 'Full access',
        api: 'Normal',
        accent: 'emerald',
    },
    {
        status: 'Past due',
        ingest: 'Accepting (warning)',
        processing: 'Operational',
        admin: 'Full access (warning banner)',
        api: 'Normal',
        accent: 'amber',
    },
    {
        status: 'Locked',
        ingest: 'Suspended',
        processing: 'Blocked',
        admin: 'Billing lock page only',
        api: '402 on ingest',
        accent: 'rose',
    },
];
</script>

<template>
    <div
        class="rounded-xl border border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/40"
        :class="compact ? 'p-3' : 'p-4'"
    >
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Platform lock impact</h3>
        <p v-if="!compact" class="mt-1 text-xs text-slate-500">
            What each billing status means for this partner platform. Use <strong>Lock platform</strong> for hard suspension; use <strong>Past due</strong> to warn without blocking processing.
        </p>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="pb-2 pr-3 font-semibold">Status</th>
                        <th class="pb-2 pr-3 font-semibold">Ingest</th>
                        <th class="pb-2 pr-3 font-semibold">Processing</th>
                        <th class="pb-2 pr-3 font-semibold">Admin UI</th>
                        <th class="pb-2 font-semibold">API</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    <tr v-for="row in rows" :key="row.status">
                        <td class="py-2 pr-3 font-medium text-slate-900 dark:text-white">{{ row.status }}</td>
                        <td class="py-2 pr-3 text-slate-600 dark:text-slate-400">{{ row.ingest }}</td>
                        <td class="py-2 pr-3 text-slate-600 dark:text-slate-400">{{ row.processing }}</td>
                        <td class="py-2 pr-3 text-slate-600 dark:text-slate-400">{{ row.admin }}</td>
                        <td class="py-2 text-slate-600 dark:text-slate-400">{{ row.api }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
