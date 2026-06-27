<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    batch: Object,
});

const results = computed(() => props.batch?.results ?? []);

const statStrip = computed(() => [
    { label: 'Total rows', value: props.batch?.total_rows ?? 0, accent: 'indigo' },
    { label: 'Valid', value: props.batch?.valid_rows ?? '—', accent: 'emerald' },
    { label: 'Invalid', value: props.batch?.invalid_rows ?? '—', accent: 'rose' },
    { label: 'Status', value: props.batch?.status ?? 'pending', accent: 'amber' },
]);

const processBatch = () => router.post(route('verify-batches.process', props.batch.id));
</script>

<template>
    <Head :title="`Verify batch: ${batch?.filename}`" />
    <AuthenticatedLayout>
        <PageHeader :title="batch?.filename" :description="`Uploaded by ${batch?.user?.name ?? 'Unknown'} · ${batch?.total_rows ?? 0} rows`">
            <template #actions>
                <AppButton v-if="batch?.status !== 'completed'" @click="processBatch">Process batch</AppButton>
                <AppButton variant="secondary" :href="route('verify-batches.index')">All batches</AppButton>
            </template>
        </PageHeader>

        <CompactStatStrip :items="statStrip" :columns="4" class="mb-6" />

        <Panel class="mb-6">
            <dl class="grid gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Status</dt>
                    <dd class="mt-1"><StatusBadge :status="batch?.status" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Uploaded</dt>
                    <dd class="mt-1"><FormattedDate :value="batch?.created_at" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-500">Uploaded by</dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">{{ batch?.user?.name ?? '—' }}</dd>
                </div>
            </dl>
        </Panel>

        <Panel title="Row results" :padding="false">
            <DataTable :empty="!results.length" empty-message="No rows parsed. Upload a valid CSV or process the batch.">
                <template #head>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Phone</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Valid</th>
                </template>
                <tr v-for="(row, index) in results" :key="index" class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 text-slate-500">{{ index + 1 }}</td>
                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">{{ row.email || '—' }}</td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ row.phone || row.phone1 || '—' }}</td>
                    <td class="px-6 py-4">
                        <span
                            v-if="batch?.status === 'completed'"
                            :class="[
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                row.valid ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
                            ]"
                        >
                            {{ row.valid ? 'Valid' : 'Invalid' }}
                        </span>
                        <span v-else class="text-xs text-slate-400">Pending</span>
                    </td>
                </tr>
            </DataTable>
        </Panel>
    </AuthenticatedLayout>
</template>
