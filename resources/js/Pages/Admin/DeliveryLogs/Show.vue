<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ log: Object });
</script>

<template>
    <Head :title="`Delivery Log #${log.id}`" />
    <AuthenticatedLayout>
        <PageHeader :title="`Delivery Log #${log.id}`" :description="`${log.delivery?.name ?? 'Unknown'} · ${log.method}`">
            <template #actions>
                <AppButton :href="route('logs.delivery')" variant="secondary">← All logs</AppButton>
                <AppButton v-if="log.lead" :href="route('leads.show', log.lead.id)" variant="secondary">View lead</AppButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Status</p>
                <div class="mt-2"><StatusBadge :status="log.status" /></div>
                <p v-if="log.skipped_reason" class="mt-2 text-sm text-rose-600">{{ log.skipped_reason }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Duration</p>
                <p class="mt-2 text-2xl font-bold" :class="log.duration_ms > 1500 ? 'text-rose-600' : 'text-slate-900 dark:text-white'">{{ log.duration_ms ?? '—' }}<span class="text-sm font-normal text-slate-500"> ms</span></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">HTTP</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ log.http_status ?? '—' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Revenue</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600">£{{ log.revenue ?? '0.00' }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Ping request / response" v-if="log.method === 'ping-post'">
                <div class="space-y-4">
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase text-slate-500">Ping request</p>
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-cyan-300">{{ JSON.stringify(log.ping_request, null, 2) ?? '—' }}</pre>
                    </div>
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase text-slate-500">Ping response</p>
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-emerald-300">{{ JSON.stringify(log.ping_response, null, 2) ?? '—' }}</pre>
                    </div>
                </div>
            </Panel>

            <Panel :title="log.method === 'ping-post' ? 'Post request / response' : 'Delivery payload'">
                <div class="space-y-4">
                    <div v-if="log.post_request">
                        <p class="mb-1 text-xs font-semibold uppercase text-slate-500">Post request</p>
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-violet-300">{{ JSON.stringify(log.post_request, null, 2) }}</pre>
                    </div>
                    <div v-if="log.post_response">
                        <p class="mb-1 text-xs font-semibold uppercase text-slate-500">Post response</p>
                        <pre class="overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-amber-300">{{ JSON.stringify(log.post_response, null, 2) }}</pre>
                    </div>
                    <p v-if="!log.post_request && !log.post_response" class="text-sm text-slate-500">No post payload recorded.</p>
                </div>
            </Panel>
        </div>

        <Panel title="Context" class="mt-6">
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-xs text-slate-500">Timestamp</dt>
                    <dd class="font-medium"><FormattedDate :value="log.created_at" /></dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Buyer</dt>
                    <dd class="font-medium">{{ log.buyer?.name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Tier</dt>
                    <dd class="font-medium">{{ log.delivery?.tier ?? '—' }}</dd>
                </div>
                <div v-if="log.lead">
                    <dt class="text-xs text-slate-500">Lead</dt>
                    <dd>
                        <Link :href="route('leads.show', log.lead.id)" class="font-mono text-sm text-indigo-600 hover:underline">{{ log.lead.uuid?.slice(0, 16) }}…</Link>
                        <StatusBadge :status="log.lead.status" class="ml-2" />
                    </dd>
                </div>
            </dl>
        </Panel>
    </AuthenticatedLayout>
</template>
