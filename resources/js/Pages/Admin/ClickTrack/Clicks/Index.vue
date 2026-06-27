<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({ entitlement: Object, clicks: Object, campaigns: Array, suppliers: Array, filters: Object });

const apply = (key, value) => router.get(route('click-track.clicks.index'), { ...props.filters, [key]: value || undefined }, { preserveState: true });
</script>

<template>
    <Head title="Clicks Report" />
    <AuthenticatedLayout>
        <PageHeader title="Clicks" description="Raw click log with sub IDs, uniqueness, and lead attribution.">
            <template #actions>
                <AppButton :href="route('click-track.clicks.export')" variant="secondary" external>Export CSV</AppButton>
            </template>
        </PageHeader>

        <Panel>
            <div class="mb-4 flex flex-wrap gap-2">
                <select class="form-select text-sm" :value="filters.campaign_id" @change="apply('campaign_id', $event.target.value)">
                    <option value="">All campaigns</option>
                    <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <select class="form-select text-sm" :value="filters.supplier_id" @change="apply('supplier_id', $event.target.value)">
                    <option value="">All affiliates</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b text-left text-xs uppercase text-slate-500">
                        <th class="px-3 py-2">Date</th><th class="px-3 py-2">Offer</th><th class="px-3 py-2">Affiliate</th><th class="px-3 py-2">Click ID</th><th class="px-3 py-2">Sub1</th><th class="px-3 py-2">Unique</th><th class="px-3 py-2">Lead</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="click in clicks.data" :key="click.id" class="border-b border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-2"><FormattedDate :date="click.clicked_at" /></td>
                            <td class="px-3 py-2">{{ click.tracking_link?.name }}</td>
                            <td class="px-3 py-2">{{ click.supplier?.name ?? '—' }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ click.click_uuid }}</td>
                            <td class="px-3 py-2">{{ click.sub1 ?? '—' }}</td>
                            <td class="px-3 py-2">{{ click.is_unique ? 'Yes' : 'No' }}</td>
                            <td class="px-3 py-2">{{ click.lead?.uuid ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="clicks.links" class="mt-4" />
        </Panel>
    </AuthenticatedLayout>
</template>
