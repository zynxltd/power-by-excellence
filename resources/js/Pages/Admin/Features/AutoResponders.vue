<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import TenantContextBanner from '@/Components/UI/TenantContextBanner.vue';
import SequenceBuilder from '@/Components/Automation/SequenceBuilder.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { MERGE_TAGS, SAMPLE_FIELDS, interpolatePreview, normalizeMergeTags } from '@/Composables/useMergeTags';

const props = defineProps({
    responders: Array,
    sequences: { type: Array, default: () => [] },
    campaigns: Array,
    providers: Object,
    providerStatus: Object,
    testResult: Object,
});

const activeMainTab = ref('single');
const editingId = ref(null);
const expandedId = ref(null);
const listFilter = ref({ channel: '', trigger: '', status: '' });

const form = useForm({
    name: '',
    campaign_id: '',
    channel: 'email',
    trigger_event: 'on_lead_received',
    delay_minutes: 0,
    status: 'active',
    config: {
        subject: '',
        body: '',
        to_field: 'email',
        provider: '',
    },
});

const statusOptions = [
    { value: 'active', label: 'Active', description: 'Fires on matching lead events.', tone: 'emerald' },
    { value: 'inactive', label: 'Paused', description: 'Saved but will not send until re-enabled.', tone: 'amber' },
];

const triggerOptions = [
    {
        value: 'on_lead_received',
        label: 'Lead received',
        description: 'Fires when a lead is ingested - confirmation or welcome message.',
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
        tone: 'indigo',
    },
    {
        value: 'on_lead_sold',
        label: 'Lead sold',
        description: 'Fires after a buyer accepts - partner handoff or thank-you.',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        tone: 'emerald',
    },
];

const channelOptions = [
    {
        value: 'email',
        label: 'Email',
        description: 'Subject + body via SMTP or transactional provider.',
        icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        tone: 'cyan',
    },
    {
        value: 'sms',
        label: 'SMS',
        description: 'Short text to the lead phone field - keep under 160 chars.',
        icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
        tone: 'violet',
    },
];

const delayPresets = [
    { label: 'Instant', minutes: 0 },
    { label: '1 hour', minutes: 60 },
    { label: '24 hours', minutes: 1440 },
    { label: '3 days', minutes: 4320 },
    { label: '7 days', minutes: 10080 },
];

const formatDelay = (minutes) => {
    if (!minutes) return 'Instant';
    if (minutes < 60) return `${minutes}m after trigger`;
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h < 24) return m ? `${h}h ${m}m after trigger` : `${h}h after trigger`;
    const d = Math.floor(h / 24);
    const rh = h % 24;
    return rh ? `${d}d ${rh}h after trigger` : `${d}d after trigger`;
};

const presets = [
    {
        name: 'Application received',
        trigger_event: 'on_lead_received',
        channel: 'email',
        config: {
            subject: 'Thanks [firstname] - we received your application',
            body: 'Hi [firstname],\n\nThank you for your enquiry. Our team will review your details and follow up shortly.\n\n- The team',
            to_field: 'email',
        },
    },
    {
        name: 'Sold - partner handoff',
        trigger_event: 'on_lead_sold',
        channel: 'email',
        config: {
            subject: 'Your request has been matched, [firstname]',
            body: 'Hi [firstname],\n\nGood news - we matched you with a partner who can help. They may contact you at [phone1] or [email].\n\n- The team',
            to_field: 'email',
        },
    },
    {
        name: 'Quick SMS confirmation',
        trigger_event: 'on_lead_received',
        channel: 'sms',
        config: {
            body: 'Hi [firstname], thanks for your enquiry. We received your request and will be in touch soon.',
            to_field: 'phone1',
        },
    },
    {
        name: 'Sold SMS handoff',
        trigger_event: 'on_lead_sold',
        channel: 'sms',
        config: {
            body: 'Hi [firstname], your request was matched. A partner may contact you at [phone1] shortly.',
            to_field: 'phone1',
        },
    },
    {
        name: '24h remarketing email',
        trigger_event: 'on_lead_received',
        channel: 'email',
        delay_minutes: 1440,
        config: {
            subject: 'Still interested, [firstname]?',
            body: 'Hi [firstname],\n\nWe noticed you enquired recently. If you still need help, reply to this email and we will match you with the right partner.\n\n- The team',
            to_field: 'email',
        },
    },
    {
        name: '3-day SMS nudge',
        trigger_event: 'on_lead_received',
        channel: 'sms',
        delay_minutes: 4320,
        config: {
            body: 'Hi [firstname], still looking for help? Reply YES and we will retry matching you today.',
            to_field: 'phone1',
        },
    },
    {
        name: 'Reminder - still interested',
        trigger_event: 'on_lead_received',
        channel: 'email',
        config: {
            subject: 'Following up on your enquiry, [firstname]',
            body: 'Hi [firstname],\n\nWe wanted to check you still need help with your request. Reply to this email or call us back.\n\n- The team',
            to_field: 'email',
        },
    },
];

const triggerLabel = (value) => triggerOptions.find((t) => t.value === value)?.label ?? value?.replace(/_/g, ' ');

const previewSubject = computed(() => interpolatePreview(form.config.subject));
const previewBody = computed(() => interpolatePreview(form.config.body));
const smsCharCount = computed(() => (form.config.body ?? '').length);

const filteredResponders = computed(() => {
    let list = props.responders ?? [];

    if (listFilter.value.channel) {
        list = list.filter((r) => r.channel === listFilter.value.channel);
    }

    if (listFilter.value.trigger) {
        list = list.filter((r) => r.trigger_event === listFilter.value.trigger);
    }

    if (listFilter.value.status) {
        list = list.filter((r) => r.status === listFilter.value.status);
    }

    return list;
});

const statsStrip = computed(() => {
    const list = props.responders ?? [];
    const active = list.filter((r) => r.status === 'active').length;
    const delayed = list.filter((r) => (r.delay_minutes ?? 0) > 0).length;
    const flows = props.sequences?.length ?? 0;

    return [
        { label: 'Single touch', value: list.length, accent: 'indigo', href: '#responders-list', title: 'Instant or delayed responders' },
        { label: 'Delayed', value: delayed, accent: 'amber', href: '#responders-list', title: 'Remarketing with a wait time' },
        { label: 'Multi-step flows', value: flows, accent: 'cyan', title: 'Switch to remarketing flows tab' },
        { label: 'Active', value: active, accent: 'emerald', href: '#responders-list', title: 'Currently sending' },
    ];
});

const openFlowsTab = () => {
    activeMainTab.value = 'flows';
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const toneClasses = (tone, selected) => {
    const map = {
        indigo: selected
            ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200 dark:border-indigo-500 dark:bg-indigo-950/40 dark:ring-indigo-800'
            : 'border-slate-200 hover:border-indigo-300 dark:border-slate-700 dark:hover:border-indigo-700',
        emerald: selected
            ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200 dark:border-emerald-500 dark:bg-emerald-950/40 dark:ring-emerald-800'
            : 'border-slate-200 hover:border-emerald-300 dark:border-slate-700 dark:hover:border-emerald-700',
        amber: selected
            ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-200 dark:border-amber-500 dark:bg-amber-950/40 dark:ring-amber-800'
            : 'border-slate-200 hover:border-amber-300 dark:border-slate-700 dark:hover:border-amber-700',
        cyan: selected
            ? 'border-cyan-500 bg-cyan-50 ring-2 ring-cyan-200 dark:border-cyan-500 dark:bg-cyan-950/40 dark:ring-cyan-800'
            : 'border-slate-200 hover:border-cyan-300 dark:border-slate-700 dark:hover:border-cyan-700',
        violet: selected
            ? 'border-violet-500 bg-violet-50 ring-2 ring-violet-200 dark:border-violet-500 dark:bg-violet-950/40 dark:ring-violet-800'
            : 'border-slate-200 hover:border-violet-300 dark:border-slate-700 dark:hover:border-violet-700',
    };

    return map[tone] ?? map.indigo;
};

const insertTag = (field, tag) => {
    form.config[field] = `${form.config[field] ?? ''}${tag}`;
};

const applyPreset = (preset) => {
    editingId.value = null;
    form.name = preset.name;
    form.trigger_event = preset.trigger_event;
    form.channel = preset.channel;
    form.delay_minutes = preset.delay_minutes ?? 0;
    form.status = 'active';
    form.config = {
        ...form.config,
        ...preset.config,
        provider: form.config.provider ?? '',
    };
};

const normalizeConfig = () => {
    form.config.subject = form.config.subject ? normalizeMergeTags(form.config.subject) : form.config.subject;
    form.config.body = form.config.body ? normalizeMergeTags(form.config.body) : form.config.body;
};

const resetBuilder = () => {
    editingId.value = null;
    form.reset();
    form.channel = 'email';
    form.trigger_event = 'on_lead_received';
    form.delay_minutes = 0;
    form.status = 'active';
    form.config = { subject: '', body: '', to_field: 'email', provider: '' };
};

const loadResponder = (responder) => {
    editingId.value = responder.id;
    form.name = responder.name;
    form.campaign_id = responder.campaign_id ?? '';
    form.channel = responder.channel;
    form.trigger_event = responder.trigger_event;
    form.delay_minutes = responder.delay_minutes ?? 0;
    form.status = responder.status ?? 'active';
    form.config = {
        subject: responder.config?.subject ?? '',
        body: responder.config?.body ?? responder.config?.message ?? '',
        to_field: responder.config?.to_field ?? (responder.channel === 'email' ? 'email' : 'phone1'),
        provider: responder.config?.provider ?? '',
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const duplicateResponder = (responder) => {
    loadResponder(responder);
    editingId.value = null;
    form.name = `${responder.name} (copy)`;
};

const submit = () => {
    normalizeConfig();

    const options = {
        preserveScroll: true,
        onSuccess: () => resetBuilder(),
    };

    if (editingId.value) {
        form.patch(route('features.auto-responders.update', editingId.value), options);
    } else {
        form.post(route('features.auto-responders.store'), options);
    }
};

const toggleStatus = (responder) => {
    const next = responder.status === 'active' ? 'inactive' : 'active';

    router.patch(route('features.auto-responders.update', responder.id), {
        name: responder.name,
        campaign_id: responder.campaign_id ?? '',
        channel: responder.channel,
        trigger_event: responder.trigger_event,
        delay_minutes: responder.delay_minutes ?? 0,
        status: next,
        config: responder.config ?? {},
    }, { preserveScroll: true });
};

const testForm = useForm({
    recipient: '',
});

const channelProviderReady = computed(() => {
    const status = props.providerStatus?.[form.channel];

    return Boolean(status?.configured);
});

const providerNotice = computed(() => {
    if (channelProviderReady.value) {
        return null;
    }

    return form.channel === 'email'
        ? 'Platform email provider will be connected shortly. Send a test to preview merge tags before creating the responder.'
        : 'Platform SMS provider will be connected shortly. Send a test to preview the message in the log before creating the responder.';
});

const sendTest = () => {
    testForm
        .transform((data) => ({
            ...data,
            channel: form.channel,
            config: { ...form.config },
        }))
        .post(route('features.auto-responders.test'), {
            preserveScroll: true,
        });
};

const destroy = (id) => {
    if (confirm('Remove this auto responder?')) {
        router.delete(route('features.auto-responders.destroy', id));
    }
};

const toggleExpanded = (id) => {
    expandedId.value = expandedId.value === id ? null : id;
};
</script>

<template>
    <Head title="Auto Responders" />
    <AuthenticatedLayout>
        <PageHeader
            title="Auto Responders"
            description="Instant confirmations, delayed remarketing, and multi-step nurture flows — email or SMS with merge tags."
        >
            <template #actions>
                <Link :href="route('features.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">
                    ← All features
                </Link>
                <AppButton :href="route('automation.index')" variant="secondary">Automation hub</AppButton>
            </template>
        </PageHeader>

        <TenantContextBanner />

        <CompactStatStrip :items="statsStrip" :columns="4" class="mb-6" />

        <div class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800">
            <button
                type="button"
                :class="[
                    'rounded-t-lg px-4 py-2.5 text-sm font-semibold transition',
                    activeMainTab === 'single'
                        ? 'border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
                ]"
                @click="activeMainTab = 'single'"
            >
                Single touch
            </button>
            <button
                type="button"
                :class="[
                    'rounded-t-lg px-4 py-2.5 text-sm font-semibold transition',
                    activeMainTab === 'flows'
                        ? 'border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
                ]"
                @click="activeMainTab = 'flows'"
            >
                Remarketing flows
                <span v-if="sequences?.length" class="ml-1 rounded-full bg-cyan-100 px-1.5 py-0.5 text-[10px] text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300">{{ sequences.length }}</span>
            </button>
        </div>

        <div v-show="activeMainTab === 'single'" class="mb-6 rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50/80 via-white to-cyan-50/60 p-4 dark:border-indigo-900/50 dark:from-indigo-950/30 dark:via-slate-900 dark:to-cyan-950/20">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Single-touch remarketing</p>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400">
                <span class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800">Lead event</span>
                <span class="text-slate-400">→</span>
                <span class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200">Optional delay</span>
                <span class="text-slate-400">→</span>
                <span class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-cyan-800 dark:border-cyan-800 dark:bg-cyan-950/50 dark:text-cyan-200">Email or SMS</span>
                <span class="text-slate-400">→</span>
                <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">Logged on lead</span>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                Set <strong>instant</strong> for welcome messages, or add a delay (e.g. 24h / 3d) for remarketing nudges.
                Need multiple steps? Switch to the <button type="button" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400" @click="openFlowsTab">Remarketing flows</button> tab.
            </p>
        </div>

        <SequenceBuilder
            v-if="activeMainTab === 'flows'"
            :sequences="sequences"
            :campaigns="campaigns"
            :providers="providers"
        />

        <div v-show="activeMainTab === 'single'" class="grid gap-6 xl:grid-cols-5">
            <div class="space-y-6 xl:col-span-3">
                <Panel :title="editingId ? 'Edit responder' : 'Build responder'">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Quick start</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="preset in presets"
                                    :key="preset.name"
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-700"
                                    @click="applyPreset(preset)"
                                >
                                    {{ preset.name }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <InputLabel for="name" value="Name" />
                            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required placeholder="e.g. Welcome email - auto insurance" />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="campaign_id" value="Campaign scope" />
                            <select id="campaign_id" v-model="form.campaign_id" class="form-select mt-1 w-full">
                                <option value="">All campaigns on this platform</option>
                                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Leave blank to run on every campaign, or scope to one vertical.</p>
                            <InputError :message="form.errors.campaign_id" class="mt-1" />
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">When should this fire?</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <button
                                    v-for="trigger in triggerOptions"
                                    :key="trigger.value"
                                    type="button"
                                    :class="['rounded-xl border p-4 text-left transition', toneClasses(trigger.tone, form.trigger_event === trigger.value)]"
                                    @click="form.trigger_event = trigger.value"
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm dark:bg-slate-800">
                                            <svg class="h-5 w-5 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="trigger.icon" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ trigger.label }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ trigger.description }}</p>
                                        </div>
                                    </div>
                                </button>
                            </div>
                            <InputError :message="form.errors.trigger_event" class="mt-1" />
                        </div>

                        <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-amber-800 dark:text-amber-300">Send timing</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="preset in delayPresets"
                                    :key="preset.label"
                                    type="button"
                                    :class="[
                                        'rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                                        form.delay_minutes === preset.minutes
                                            ? 'border-amber-500 bg-amber-100 text-amber-900 dark:border-amber-600 dark:bg-amber-950/50 dark:text-amber-200'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-amber-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300',
                                    ]"
                                    @click="form.delay_minutes = preset.minutes"
                                >
                                    {{ preset.label }}
                                </button>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs text-slate-500">Custom delay (minutes after trigger)</label>
                                    <input v-model.number="form.delay_minutes" type="number" min="0" class="form-input w-full" />
                                </div>
                                <div class="flex items-end">
                                    <p class="rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm font-medium text-amber-900 dark:border-amber-800 dark:bg-slate-900 dark:text-amber-200">
                                        {{ formatDelay(form.delay_minutes) }}
                                    </p>
                                </div>
                            </div>
                            <InputError :message="form.errors.delay_minutes" class="mt-1" />
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Channel</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <button
                                    v-for="channel in channelOptions"
                                    :key="channel.value"
                                    type="button"
                                    :class="['rounded-xl border p-4 text-left transition', toneClasses(channel.tone, form.channel === channel.value)]"
                                    @click="form.channel = channel.value; form.config.to_field = channel.value === 'email' ? 'email' : 'phone1'"
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm dark:bg-slate-800">
                                            <svg class="h-5 w-5 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="channel.icon" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ channel.label }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ channel.description }}</p>
                                        </div>
                                    </div>
                                </button>
                            </div>
                            <InputError :message="form.errors.channel" class="mt-1" />
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Status</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <button
                                    v-for="status in statusOptions"
                                    :key="status.value"
                                    type="button"
                                    :class="['rounded-xl border p-3 text-left transition', toneClasses(status.tone, form.status === status.value)]"
                                    @click="form.status = status.value"
                                >
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ status.label }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ status.description }}</p>
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="provider" value="Provider (optional)" />
                                <select id="provider" v-model="form.config.provider" class="form-select mt-1 w-full">
                                    <option value="">Platform default</option>
                                    <option v-for="p in (providers?.[form.channel] ?? [])" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="to_field" value="Send to field" />
                                <select id="to_field" v-model="form.config.to_field" class="form-select mt-1 w-full">
                                    <option value="email">email</option>
                                    <option value="phone1">phone1</option>
                                    <option value="phone2">phone2</option>
                                </select>
                            </div>
                        </div>

                        <div class="rounded-xl border border-violet-200 bg-violet-50/50 p-3 dark:border-violet-900 dark:bg-violet-950/20">
                            <p class="text-xs font-semibold uppercase text-violet-700 dark:text-violet-300">Merge tags - click to insert</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <button
                                    v-for="tag in MERGE_TAGS"
                                    :key="tag"
                                    type="button"
                                    class="rounded-md border border-violet-200 bg-white px-2 py-1 font-mono text-xs text-violet-800 transition hover:bg-violet-100 dark:border-violet-800 dark:bg-slate-900 dark:text-violet-300 dark:hover:bg-violet-950"
                                    @click="insertTag('body', tag)"
                                >
                                    {{ tag }}
                                </button>
                            </div>
                            <p class="mt-2 text-[11px] text-violet-700/80 dark:text-violet-300/80">Tags use square brackets, e.g. <code class="font-mono">[firstname]</code> - replaced from lead field data at send time.</p>
                        </div>

                        <div v-if="form.channel === 'email'">
                            <InputLabel for="subject" value="Subject" />
                            <TextInput id="subject" v-model="form.config.subject" class="mt-1 block w-full font-mono text-sm" placeholder="Thanks [firstname] - application received" />
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                <button v-for="tag in MERGE_TAGS" :key="`sub-${tag}`" type="button" class="rounded border px-1.5 py-0.5 text-[10px] text-indigo-600 dark:text-indigo-400" @click="insertTag('subject', tag)">{{ tag }}</button>
                            </div>
                            <InputError :message="form.errors['config.subject']" class="mt-1" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel for="body" :value="form.channel === 'sms' ? 'SMS message' : 'Email body'" />
                                <span v-if="form.channel === 'sms'" :class="['text-xs font-medium', smsCharCount > 160 ? 'text-amber-600' : 'text-slate-500']">
                                    {{ smsCharCount }} / 160 chars
                                </span>
                            </div>
                            <textarea
                                id="body"
                                v-model="form.config.body"
                                :rows="form.channel === 'sms' ? 4 : 6"
                                class="form-input mt-1 w-full font-mono text-sm"
                                :placeholder="form.channel === 'sms' ? 'Hi [firstname], thanks for your enquiry…' : 'Hi [firstname],\n\nThank you for your application…'"
                            />
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                <button v-for="tag in MERGE_TAGS" :key="`body-${tag}`" type="button" class="rounded border px-1.5 py-0.5 text-[10px] text-indigo-600 dark:text-indigo-400" @click="insertTag('body', tag)">{{ tag }}</button>
                            </div>
                            <InputError :message="form.errors['config.body']" class="mt-1" />
                        </div>

                        <div
                            v-if="providerNotice"
                            class="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
                        >
                            {{ providerNotice }}
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Send test before creating</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Delivers to your inbox or phone using the message above. Verify merge tags and copy before the responder goes live.
                                    </p>
                                </div>
                                <span
                                    v-if="channelProviderReady"
                                    class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                                >
                                    Provider ready
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                >
                                    Preview mode
                                </span>
                            </div>

                            <div class="mt-4">
                                <InputLabel
                                    for="test_recipient"
                                    :value="form.channel === 'email' ? 'Test email address' : 'Test phone number'"
                                />
                                <TextInput
                                    id="test_recipient"
                                    v-model="testForm.recipient"
                                    class="mt-1 block w-full"
                                    :type="form.channel === 'email' ? 'email' : 'tel'"
                                    :placeholder="form.channel === 'email' ? 'you@company.com' : '+44 7700 900123'"
                                />
                                <InputError :message="testForm.errors.recipient || testForm.errors['config.body'] || testForm.errors['config.subject']" class="mt-1" />
                            </div>

                            <div
                                v-if="testResult && testResult.channel === form.channel"
                                class="mt-4 rounded-lg border border-indigo-200 bg-white p-3 dark:border-indigo-800 dark:bg-slate-900"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                                    {{ testResult.mode === 'live' ? 'Test sent' : 'Test preview' }}
                                </p>
                                <p v-if="testResult.subject" class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ testResult.subject }}
                                </p>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">{{ testResult.body }}</p>
                                <p class="mt-2 text-xs text-slate-500">To: {{ testResult.recipient }}</p>
                                <p v-if="testResult.notice" class="mt-2 text-xs text-amber-700 dark:text-amber-300">{{ testResult.notice }}</p>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <AppButton
                                    type="button"
                                    variant="secondary"
                                    :disabled="testForm.processing"
                                    :loading="testForm.processing"
                                    @click="sendTest"
                                >
                                    Send test
                                </AppButton>
                                <AppButton type="submit" :disabled="form.processing" :loading="form.processing">
                                    {{ editingId ? 'Save changes' : 'Create responder' }}
                                </AppButton>
                                <AppButton v-if="editingId || form.name" type="button" variant="secondary" @click="resetBuilder">Cancel</AppButton>
                            </div>
                        </div>
                    </form>
                </Panel>
            </div>

            <div class="space-y-6 xl:col-span-2">
                <Panel title="Live preview">
                    <div
                        :class="[
                            'overflow-hidden rounded-xl border shadow-sm',
                            form.channel === 'email'
                                ? 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900'
                                : 'border-violet-200 bg-gradient-to-br from-violet-50 to-white dark:border-violet-900 dark:from-violet-950/30 dark:to-slate-900',
                        ]"
                    >
                        <div class="border-b border-slate-100 px-4 py-2.5 dark:border-slate-800">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                {{ form.channel === 'email' ? 'Email preview' : 'SMS preview' }}
                                · {{ formatDelay(form.delay_minutes) }}
                            </p>
                        </div>

                        <div v-if="form.channel === 'email'" class="p-4">
                            <p class="text-xs text-slate-500">To: {{ SAMPLE_FIELDS.email }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                                {{ previewSubject || 'Subject line preview…' }}
                            </p>
                            <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                {{ previewBody || 'Your message body will appear here with sample merge data.' }}
                            </p>
                        </div>

                        <div v-else class="p-4">
                            <div class="ml-auto max-w-[85%] rounded-2xl rounded-br-md bg-violet-600 px-4 py-3 text-sm leading-relaxed text-white shadow-md">
                                {{ previewBody || 'SMS preview with [firstname] replaced…' }}
                            </div>
                            <p class="mt-3 text-right text-[11px] text-slate-500">To: {{ SAMPLE_FIELDS.phone1 }}</p>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Preview uses sample data - real sends use each lead's field values.</p>
                </Panel>

                <Panel title="Messaging providers">
                    <div class="space-y-3">
                        <div
                            v-for="(status, channel) in providerStatus"
                            :key="channel"
                            class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5 dark:border-slate-700"
                        >
                            <div>
                                <p class="text-sm font-semibold capitalize text-slate-900 dark:text-white">{{ channel }}</p>
                                <p class="text-xs text-slate-500">{{ status.provider ?? 'default' }}</p>
                            </div>
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                                    status.configured
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                ]"
                            >
                                {{ status.configured ? 'Ready' : 'Preview only' }}
                            </span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Override per responder below, or configure platform defaults in tenant settings.</p>
                </Panel>

                <Panel id="responders-list" title="Configured responders">
                    <div class="mb-4 flex flex-wrap gap-2">
                        <select v-model="listFilter.channel" class="form-select text-xs">
                            <option value="">All channels</option>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                        </select>
                        <select v-model="listFilter.trigger" class="form-select text-xs">
                            <option value="">All triggers</option>
                            <option value="on_lead_received">Lead received</option>
                            <option value="on_lead_sold">Lead sold</option>
                        </select>
                        <select v-model="listFilter.status" class="form-select text-xs">
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Paused</option>
                        </select>
                    </div>
                    <div v-if="!responders?.length" class="rounded-xl border border-dashed border-slate-300 py-10 text-center dark:border-slate-700">
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">No auto responders yet</p>
                        <p class="mt-1 text-xs text-slate-500">Pick a quick-start template or build one on the left.</p>
                    </div>

                    <div v-else-if="!filteredResponders.length" class="rounded-xl border border-dashed border-slate-300 py-8 text-center dark:border-slate-700">
                        <p class="text-sm text-slate-500">No responders match the current filters.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <article
                            v-for="r in filteredResponders"
                            :key="r.id"
                            class="rounded-xl border border-slate-200 bg-slate-50/50 transition hover:border-indigo-200 dark:border-slate-700 dark:bg-slate-800/30 dark:hover:border-indigo-800"
                        >
                            <button
                                type="button"
                                class="flex w-full items-start gap-3 p-4 text-left"
                                @click="toggleExpanded(r.id)"
                            >
                                <span
                                    :class="[
                                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg',
                                        r.channel === 'email'
                                            ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300'
                                            : 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
                                    ]"
                                >
                                    {{ r.channel === 'email' ? '✉️' : '💬' }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ r.name }}</p>
                                        <StatusBadge :status="r.status" />
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ triggerLabel(r.trigger_event) }}
                                        · {{ r.campaign?.name ?? 'All campaigns' }}
                                        · {{ formatDelay(r.delay_minutes ?? 0) }}
                                    </p>
                                    <p v-if="!expandedId || expandedId !== r.id" class="mt-2 line-clamp-2 text-xs text-slate-600 dark:text-slate-400">
                                        {{ r.config?.subject ? `${r.config.subject} - ` : '' }}{{ r.config?.body ?? r.config?.message ?? 'No message configured' }}
                                    </p>
                                </div>
                                <svg
                                    :class="['mt-1 h-4 w-4 shrink-0 text-slate-400 transition', expandedId === r.id && 'rotate-180']"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div v-if="expandedId === r.id" class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">
                                <div v-if="r.channel === 'email' && r.config?.subject" class="mb-2">
                                    <p class="text-[10px] font-semibold uppercase text-slate-500">Subject</p>
                                    <p class="text-sm text-slate-800 dark:text-slate-200">{{ r.config.subject }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase text-slate-500">Message</p>
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ r.config?.body ?? r.config?.message ?? '-' }}</p>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300"
                                        @click="loadResponder(r)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                        @click="duplicateResponder(r)"
                                    >
                                        Duplicate
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                        @click="toggleStatus(r)"
                                    >
                                        {{ r.status === 'active' ? 'Pause' : 'Activate' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/30"
                                        @click="destroy(r.id)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </Panel>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
