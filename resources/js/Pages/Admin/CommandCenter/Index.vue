<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import FormattedDate from '@/Components/UI/FormattedDate.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    platformStats: Object,
    healthSummary: Object,
    currentAccountId: Number,
    tenants: Array,
    recentEvents: Array,
    recentAlerts: Array,
    opsChecks: Array,
    platformStatus: Object,
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);

const today = new Date().toISOString().slice(0, 10);

const deliveryLogUrl = (params = {}) => route('logs.delivery', { days: 1, ...params });

const leadUrl = (params = {}) => route('leads.index', { from_date: today, to_date: today, ...params });

const switchTenant = (accountId) => {
    router.post(route('accounts.switch'), { account_id: accountId }, { preserveScroll: true });
};

const copied = ref('');
const copyText = async (text, key) => {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 2000);
};

const overallHealth = computed(() => {
    if (props.healthSummary?.critical > 0) return { label: 'Critical issues', class: 'text-rose-600' };
    if (props.healthSummary?.warning > 0) return { label: 'Some warnings', class: 'text-amber-600' };
    return { label: 'All systems nominal', class: 'text-emerald-600' };
});

const checkStatusClass = (status) => ({
    ok: 'text-emerald-600',
    warning: 'text-amber-600',
    critical: 'text-rose-600',
}[status] ?? 'text-slate-500');

const checkStatusLabel = (status) => ({
    ok: 'OK',
    warning: 'Warning',
    critical: 'Critical',
}[status] ?? status);

const opsCategories = [
    { id: 'infrastructure', title: 'Infrastructure', description: 'Tenancy, database, cache, queue, storage, and scheduler.' },
    { id: 'speed', title: 'Speed', description: 'Lead pipeline latency vs platform target.' },
    { id: 'quality', title: 'Quality', description: 'Post success and platform-side delivery errors.' },
];

const checksForCategory = (categoryId) => (props.opsChecks ?? []).filter((c) => c.category === categoryId);

const healthClass = (health) => ({
    healthy: 'text-emerald-600',
    warning: 'text-amber-600',
    critical: 'text-rose-600',
    idle: 'text-slate-500',
}[health] ?? 'text-slate-500');

const statLinkClass = (highlight = false) => [
    'hover:underline',
    highlight ? 'font-medium text-rose-600' : 'text-indigo-600 dark:text-indigo-400',
];

const platformStatItems = computed(() => {
    const s = props.platformStats ?? {};

    return [
        { label: 'Tenants', value: s.tenants, href: isSuperAdmin.value ? route('accounts.index') : null, title: 'Partner platforms' },
        { label: 'Leads', value: s.leads_today, href: leadUrl(), title: 'Leads today' },
        { label: 'Sold', value: s.sold_today, href: leadUrl({ status: 'sold' }), accent: 'emerald', title: 'Sold today' },
        { label: 'Pings', value: s.pings_today, href: deliveryLogUrl({ has_ping: 1 }), accent: 'violet', title: 'Pings today' },
        { label: 'Posts', value: s.posts_today, href: deliveryLogUrl({ has_post: 1 }), accent: 'cyan', title: 'Posts today' },
        {
            label: 'Post %',
            value: s.post_success_rate != null ? `${s.post_success_rate}%` : '—',
            href: deliveryLogUrl({ has_post: 1, status: 'success' }),
            accent: s.post_success_rate != null && s.post_success_rate >= 95 ? 'emerald' : (s.post_success_rate != null && s.post_success_rate < 90 ? 'amber' : undefined),
            title: 'Post success rate today (target ≥95%)',
        },
        {
            label: 'Errors',
            value: s.internal_failed_today ?? 0,
            href: deliveryLogUrl({ status: 'failed' }),
            accent: (s.internal_failed_today ?? 0) > 0 ? 'rose' : 'emerald',
            title: 'Platform delivery errors today (config, timeout, exception)',
        },
        { label: 'Pending', value: s.pending_queue, href: route('leads.index', { status: 'pending' }), accent: 'amber', title: 'Queue depth' },
        { label: 'Failed jobs', value: s.failed_jobs, href: route('operations.index'), accent: s.failed_jobs > 0 ? 'rose' : undefined, title: 'Failed queue jobs' },
        {
            label: 'Avg ms',
            value: `${s.avg_processing_ms ?? 0}ms`,
            accent: s.processing_on_target ? 'emerald' : 'amber',
            title: `Target <${s.processing_target_ms}ms`,
        },
        { label: 'P95', value: `${s.p95_processing_ms ?? 0}ms`, title: 'P95 processing' },
        { label: 'Users', value: s.users, href: route('users.index'), title: 'Platform users' },
    ];
});
</script>

<template>
    <Head title="Command Center" />
    <AuthenticatedLayout>
        <PageHeader
            title="Command Center"
            description="Cross-tenant health, delivery volume, queue depth, and platform-wide activity."
        >
            <template #actions>
                <Link v-if="isSuperAdmin" :href="route('accounts.index')" class="text-sm font-medium text-indigo-600 hover:underline">Partner platforms →</Link>
                <Link :href="route('operations.index')" class="text-sm font-medium text-indigo-600 hover:underline">Live ops →</Link>
            </template>
        </PageHeader>

        <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-5 text-white shadow-lg dark:border-slate-700">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-indigo-300">Platform operations</p>
                    <p class="mt-1 text-2xl font-bold">{{ platformStats?.tenants ?? 0 }} active tenants</p>
                    <p class="mt-1 text-sm text-slate-300">
                        {{ platformStats?.leads_today ?? 0 }} leads today · {{ platformStats?.sold_today ?? 0 }} sold · {{ platformStats?.pending_queue ?? 0 }} in queue
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-slate-300">Overall health</p>
                    <p class="text-xl font-semibold" :class="overallHealth.class">{{ overallHealth.label }}</p>
                    <Link :href="route('live-feed.index')" class="mt-2 inline-block text-xs text-indigo-300 hover:text-white">Live feed →</Link>
                </div>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-800 dark:bg-slate-900">
            <p class="text-slate-600 dark:text-slate-400">
                Health:
                <span class="font-semibold" :class="overallHealth.class">{{ overallHealth.label }}</span>
                <span class="mx-2 text-slate-300">·</span>
                <span class="text-emerald-600">{{ healthSummary?.healthy ?? 0 }} ok</span>
                <span class="mx-1 text-slate-300">/</span>
                <span class="text-amber-600">{{ healthSummary?.warning ?? 0 }} warn</span>
                <span class="mx-1 text-slate-300">/</span>
                <span class="text-rose-600">{{ healthSummary?.critical ?? 0 }} crit</span>
            </p>
            <p class="text-slate-500">Post success &amp; sold rate — not ping rejections</p>
        </div>

        <CompactStatStrip :items="platformStatItems" class="mb-6" />

        <Panel title="Platform operations" class="mb-6">
            <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                Infrastructure, speed, and quality gates for the whole network.
                Checks are cached for 60s; DNS lookups for 5m. Status snapshots every 15 minutes.
                <span v-if="platformStatus?.checked_at" class="block mt-1 text-xs text-slate-500">
                    Last status refresh: <FormattedDate :value="platformStatus.checked_at" />
                    · <Link :href="route('status.index')" class="text-indigo-600 hover:underline">Public status page</Link>
                </span>
                Run <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-indigo-600 dark:bg-slate-800">php artisan platform:check</code> for a fresh CLI report.
            </p>

            <div v-for="cat in opsCategories" :key="cat.id" class="mb-6 last:mb-0">
                <div class="mb-3">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ cat.title }}</h3>
                    <p class="text-xs text-slate-500">{{ cat.description }}</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div
                        v-for="check in checksForCategory(cat.id)"
                        :key="check.key"
                        class="flex min-h-[7rem] flex-col rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-800/40"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ check.label }}</p>
                            <span class="shrink-0 text-xs font-semibold uppercase" :class="checkStatusClass(check.status)">
                                {{ checkStatusLabel(check.status) }}
                            </span>
                        </div>
                        <p class="mt-2 flex-1 text-sm text-slate-600 dark:text-slate-400">{{ check.message }}</p>
                        <p v-if="check.hint" class="mt-2 text-xs text-slate-500">{{ check.hint }}</p>
                        <button
                            v-if="check.command"
                            type="button"
                            class="mt-3 w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-left font-mono text-[10px] text-indigo-600 hover:bg-indigo-50 dark:border-slate-600 dark:bg-slate-900 dark:hover:bg-slate-800"
                            @click="copyText(check.command, check.key)"
                        >
                            {{ copied === check.key ? '✓ Copied' : check.command }}
                        </button>
                    </div>
                </div>
            </div>

            <p class="mt-4 text-xs text-slate-500">
                <Link :href="route('live-feed.index')" class="text-indigo-600 hover:underline">Open live feed →</Link>
                ·
                <Link :href="route('notifications.admin.index')" class="text-indigo-600 hover:underline">Notifications →</Link>
            </p>
        </Panel>

        <Panel title="Tenant overview" class="mt-6" :padding="false">
            <p class="border-b border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800">
                <strong class="text-slate-700 dark:text-slate-300">Errors</strong> = platform issues (missing URL, timeout, exception) — target 0.
                <strong class="ml-2 text-slate-700 dark:text-slate-300">Buyer fail</strong> = buyer rejected a post (normal in routing).
                <strong class="ml-2 text-slate-700 dark:text-slate-300">Skipped / Outbid</strong> = ping-tree waterfall (expected).
                Post success % target ≥95%.
            </p>
            <DataTable :empty="!tenants?.length">
                <template #head>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Tenant</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Health</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Leads</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Sold</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Pings</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Posts</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500" title="Post success rate today">Post %</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500" title="Platform delivery errors — target 0">Errors</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500" title="Buyer post rejections">Buyer fail</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Skipped</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Pending</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Actions</th>
                </template>
                <tr v-for="t in tenants" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-900 dark:text-white">{{ t.name }}</p>
                        <a :href="t.portal_url" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline">{{ t.domain }}</a>
                    </td>
                    <td class="px-4 py-3 capitalize" :class="healthClass(t.health)">{{ t.health }}</td>
                    <td class="px-4 py-3">
                        <Link :href="leadUrl({ account_id: t.id })" :class="statLinkClass()">
                            {{ t.leads_today }}
                        </Link>
                        <span class="text-slate-400"> / {{ t.leads_count }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <Link :href="leadUrl({ account_id: t.id, status: 'sold' })" :class="statLinkClass()">{{ t.sold_today }}</Link>
                    </td>
                    <td class="px-4 py-3">
                        <Link :href="deliveryLogUrl({ account_id: t.id, has_ping: 1 })" :class="statLinkClass()">{{ t.pings_today }}</Link>
                    </td>
                    <td class="px-4 py-3">
                        <Link :href="deliveryLogUrl({ account_id: t.id, has_post: 1 })" :class="statLinkClass()">{{ t.posts_today }}</Link>
                    </td>
                    <td class="px-4 py-3">
                        <span
                            v-if="t.post_success_rate != null"
                            :class="t.post_success_rate >= 95 ? 'text-emerald-600' : (t.post_success_rate < 90 ? 'text-amber-600 font-medium' : 'text-slate-700 dark:text-slate-300')"
                        >
                            {{ t.post_success_rate }}%
                        </span>
                        <span v-else class="text-slate-400">—</span>
                    </td>
                    <td class="px-4 py-3">
                        <Link
                            :href="deliveryLogUrl({ account_id: t.id, status: 'failed' })"
                            :class="statLinkClass(t.internal_failed_today > 0)"
                        >
                            {{ t.internal_failed_today }}
                        </Link>
                    </td>
                    <td class="px-4 py-3">
                        <Link
                            :href="deliveryLogUrl({ account_id: t.id, status: 'failed' })"
                            class="text-slate-500 hover:underline"
                        >
                            {{ t.buyer_failed_today }}
                        </Link>
                    </td>
                    <td class="px-4 py-3">
                        <Link :href="deliveryLogUrl({ account_id: t.id, status: 'skipped' })" class="text-slate-500 hover:underline">
                            {{ t.skipped_today }}
                        </Link>
                    </td>
                    <td class="px-4 py-3">
                        <Link :href="route('leads.index', { account_id: t.id, status: 'pending' })" :class="statLinkClass()">{{ t.pending }}</Link>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap justify-end gap-1">
                            <span v-if="t.is_active_context" class="text-xs font-semibold text-indigo-600">Active</span>
                            <AppButton v-else variant="secondary" class="!px-2 !py-1 !text-xs" @click="switchTenant(t.id)">Switch</AppButton>
                            <AppButton
                                :href="route('accounts.visit', t.id)"
                                method="post"
                                variant="secondary"
                                class="!px-2 !py-1 !text-xs"
                            >
                                Portal ↗
                            </AppButton>
                            <AppButton
                                v-if="t.admin_user"
                                :href="route('impersonate.start', t.admin_user.id)"
                                method="post"
                                variant="secondary"
                                class="!px-2 !py-1 !text-xs"
                            >
                                Login as admin
                            </AppButton>
                        </div>
                    </td>
                </tr>
            </DataTable>
        </Panel>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <Panel title="Recent platform events" :padding="false">
                <div v-if="!recentEvents?.length" class="p-6 text-sm text-slate-500">No events yet.</div>
                <div v-for="e in recentEvents" :key="e.id" class="border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ e.event_type }}</p>
                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ e.message }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ e.tenant }} · <Link v-if="e.lead_id" :href="route('leads.show', e.lead_id)" class="text-indigo-600 hover:underline">{{ e.lead_uuid?.slice(0, 8) }}</Link></p>
                        </div>
                        <FormattedDate :value="e.created_at" class="shrink-0 text-xs" />
                    </div>
                </div>
            </Panel>

            <Panel title="Recent alert fires" :padding="false">
                <div v-if="!recentAlerts?.length" class="p-6 text-sm text-slate-500">No alerts fired.</div>
                <div v-for="a in recentAlerts" :key="a.id" class="border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium">{{ a.alert?.name ?? 'Alert' }}</p>
                            <p class="text-xs text-slate-500">{{ a.account?.name }} · {{ a.metric }} = {{ a.value }}</p>
                        </div>
                        <StatusBadge :status="a.status" />
                    </div>
                    <FormattedDate :value="a.created_at" class="mt-1 text-xs" />
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
