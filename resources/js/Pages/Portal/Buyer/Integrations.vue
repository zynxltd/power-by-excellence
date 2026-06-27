<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    apiBaseUrl: String,
    tenantHost: String,
    currency: String,
    partner: Object,
    webhooks: { type: Array, default: () => [] },
    endpoints: { type: Array, default: () => [] },
    guides: { type: Array, default: () => [] },
    samples: Object,
});

const copied = ref('');

const feedbackCurl = computed(() => {
    const url = `${props.apiBaseUrl}/buyers/${props.partner?.reference}/feedback`;
    const body = JSON.stringify(props.samples?.feedback ?? {}, null, 2);
    return `curl -X POST '${url}' \\\n`
        + `  -H 'Authorization: Bearer your_prefix|your_secret' \\\n`
        + `  -H 'Content-Type: application/json' \\\n`
        + `  -d '${body.replace(/'/g, "'\\''")}'`;
});

const copyText = async (text, key) => {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 2000);
};

const methodClass = (method) => {
    const verb = method?.split('/')[0]?.toUpperCase();
    return {
        GET: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        POST: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    }[verb] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
};
</script>

<template>
    <Head title="Integrations & API" />
    <AuthenticatedLayout>
        <PageHeader
            title="Integrations & API"
            :description="`Pull lead data, push conversion feedback, and connect webhooks for ${partner?.name ?? 'your buyer account'}.`"
        >
            <template #actions>
                <AppButton :href="route('portal.buyer.leads.download')" variant="secondary" external>Download CSV</AppButton>
                <AppButton :href="route('help.show', 'buyer-portal-feedback-returns')" variant="secondary">Help guide</AppButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">API base URL</p>
                <p class="mt-1 break-all font-mono text-sm text-indigo-600 dark:text-indigo-400">{{ apiBaseUrl }}</p>
                <button type="button" class="mt-2 text-xs font-medium text-indigo-600 hover:underline" @click="copyText(apiBaseUrl, 'base')">
                    {{ copied === 'base' ? 'Copied' : 'Copy' }}
                </button>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Buyer reference</p>
                <p class="mt-1 font-mono text-sm text-slate-900 dark:text-white">{{ partner?.reference }}</p>
                <p class="mt-1 text-xs text-slate-500">Used in REST paths and delivery config</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500">Active webhooks</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ webhooks.length }}</p>
                <p class="text-xs text-slate-500">Outbound events to your systems</p>
            </div>
        </div>

        <Panel title="Endpoints" class="mb-6">
            <div class="space-y-4">
                <div v-for="endpoint in endpoints" :key="endpoint.key" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded px-2 py-0.5 text-xs font-bold uppercase" :class="methodClass(endpoint.method)">{{ endpoint.method.split('/')[0] }}</span>
                        <code class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ endpoint.path }}</code>
                        <span v-if="endpoint.scope" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ endpoint.scope }}</span>
                        <span v-if="endpoint.type === 'portal'" class="rounded-full bg-violet-100 px-2 py-0.5 text-xs text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">Portal session</span>
                    </div>
                    <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ endpoint.summary }}</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ endpoint.description }}</p>
                </div>
            </div>
        </Panel>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Push feedback (REST)">
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                    Automate conversion reporting from your CRM. Requires an API key with <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">buyers.manage</code> — request one from your platform administrator.
                </p>
                <code class="block overflow-x-auto rounded-xl bg-slate-50 p-4 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ feedbackCurl }}</code>
                <button type="button" class="mt-3 text-sm font-semibold text-indigo-600 hover:underline" @click="copyText(feedbackCurl, 'feedback')">
                    {{ copied === 'feedback' ? 'Copied' : 'Copy curl example' }}
                </button>
            </Panel>

            <Panel title="Webhooks (receive data)">
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                    When configured, the platform POSTs JSON to your endpoint on lead lifecycle events. Contact your administrator to add or change webhook URLs.
                </p>
                <div v-if="!webhooks?.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700">
                    No active webhooks configured for your account yet.
                </div>
                <ul v-else class="space-y-3">
                    <li v-for="webhook in webhooks" :key="webhook.name" class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50">
                        <p class="font-medium text-slate-900 dark:text-white">{{ webhook.name }}</p>
                        <p class="font-mono text-xs text-slate-500">{{ webhook.url_host }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Events: {{ webhook.events?.join(', ') || '—' }}
                            <span v-if="webhook.scoped_to_you" class="ml-1 text-indigo-600">· scoped to you</span>
                        </p>
                    </li>
                </ul>
            </Panel>
        </div>

        <Panel title="Guides" class="mt-6">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div v-for="guide in guides" :key="guide.title">
                    <dt class="text-sm font-semibold text-slate-900 dark:text-white">{{ guide.title }}</dt>
                    <dd class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ guide.body }}</dd>
                </div>
            </dl>
        </Panel>

        <p class="mt-6 text-sm text-slate-500">
            Prefer the UI?
            <Link :href="route('portal.buyer.leads')" class="font-semibold text-indigo-600 hover:underline">Report feedback and returns on My Leads</Link>
            without an API key.
        </p>
    </AuthenticatedLayout>
</template>
