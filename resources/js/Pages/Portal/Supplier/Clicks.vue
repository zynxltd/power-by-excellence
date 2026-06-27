<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ supplier: Object, account: Object, stats: Object, currency: String });
</script>

<template>
    <Head title="Click stats" />
    <AuthenticatedLayout>
        <div class="mb-4">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Click stats</h1>
            <p class="text-xs text-slate-500">Tracking links and clicks for {{ supplier.name }}</p>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <Panel title="Today"><p class="text-2xl font-bold">{{ stats.clicks_today }}</p><p class="text-xs text-slate-500">clicks</p></Panel>
            <Panel title="7 days"><p class="text-2xl font-bold">{{ stats.conversions_7d }}</p><p class="text-xs text-slate-500">approved conversions</p></Panel>
            <Panel title="Links"><p class="text-2xl font-bold">{{ stats.links?.length ?? 0 }}</p><p class="text-xs text-slate-500">active tracking links</p></Panel>
        </div>

        <Panel title="Your tracking links" class="mb-6">
            <div v-for="link in stats.links" :key="link.id" class="border-b border-slate-100 py-3 text-sm dark:border-slate-800">
                <p class="font-semibold">{{ link.name }}</p>
                <p class="font-mono text-xs text-indigo-600">{{ route('click.redirect', link.token) }}</p>
                <p class="text-xs text-slate-500">{{ link.clicks_count }} clicks · {{ link.conversions_count }} conversions</p>
            </div>
            <p v-if="!stats.links?.length" class="text-sm text-slate-500">No links assigned to your affiliate account yet.</p>
        </Panel>

        <Panel title="Recent clicks">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-2">Time</th><th class="py-2">Offer</th><th class="py-2">Sub1</th><th class="py-2">Unique</th></tr></thead>
                <tbody>
                    <tr v-for="click in stats.recent_clicks" :key="click.id" class="border-t border-slate-100 dark:border-slate-800">
                        <td class="py-2"><FormattedDate :date="click.clicked_at" /></td>
                        <td class="py-2">{{ click.tracking_link?.name }}</td>
                        <td class="py-2">{{ click.sub1 ?? '—' }}</td>
                        <td class="py-2">{{ click.is_unique ? 'Yes' : 'No' }}</td>
                    </tr>
                </tbody>
            </table>
        </Panel>
    </AuthenticatedLayout>
</template>
