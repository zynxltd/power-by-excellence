<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    responders: Array,
    campaigns: Array,
});

const form = useForm({
    name: '',
    campaign_id: '',
    channel: 'email',
    trigger_event: 'on_lead_received',
    status: 'active',
    config: {
        subject: '',
        body: '',
        to_field: 'email',
        provider: '',
    },
});

const expandedId = ref(null);

const mergeTags = ['[firstname]', '[lastname]', '[email]', '[phone1]', '[zipcode]'];

const sampleFields = {
    firstname: 'Alex',
    lastname: 'Morgan',
    email: 'alex@example.com',
    phone1: '555-0142',
    zipcode: 'M5H 2N2',
};

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
];

const triggerLabel = (value) => triggerOptions.find((t) => t.value === value)?.label ?? value?.replace(/_/g, ' ');

const interpolatePreview = (text) => {
    if (!text) {
        return '';
    }

    return text.replace(/\[([a-zA-Z0-9_]+)\]/g, (_, key) => sampleFields[key] ?? `[${key}]`);
};

const previewSubject = computed(() => interpolatePreview(form.config.subject));
const previewBody = computed(() => interpolatePreview(form.config.body));
const smsCharCount = computed(() => (form.config.body ?? '').length);

const statsStrip = computed(() => {
    const list = props.responders ?? [];
    const active = list.filter((r) => r.status === 'active').length;

    return [
        { label: 'Total responders', value: list.length, accent: 'indigo' },
        { label: 'Active', value: active, accent: 'emerald' },
        { label: 'Email', value: list.filter((r) => r.channel === 'email').length, accent: 'cyan' },
        { label: 'SMS', value: list.filter((r) => r.channel === 'sms').length, accent: 'violet' },
    ];
});

const toneClasses = (tone, selected) => {
    const map = {
        indigo: selected
            ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200 dark:border-indigo-500 dark:bg-indigo-950/40 dark:ring-indigo-800'
            : 'border-slate-200 hover:border-indigo-300 dark:border-slate-700 dark:hover:border-indigo-700',
        emerald: selected
            ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200 dark:border-emerald-500 dark:bg-emerald-950/40 dark:ring-emerald-800'
            : 'border-slate-200 hover:border-emerald-300 dark:border-slate-700 dark:hover:border-emerald-700',
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
    form.name = preset.name;
    form.trigger_event = preset.trigger_event;
    form.channel = preset.channel;
    form.config = {
        ...form.config,
        ...preset.config,
        provider: form.config.provider ?? '',
    };
};

const duplicateResponder = (responder) => {
    form.name = `${responder.name} (copy)`;
    form.campaign_id = responder.campaign_id ?? '';
    form.channel = responder.channel;
    form.trigger_event = responder.trigger_event;
    form.config = {
        subject: responder.config?.subject ?? '',
        body: responder.config?.body ?? responder.config?.message ?? '',
        to_field: responder.config?.to_field ?? (responder.channel === 'email' ? 'email' : 'phone1'),
        provider: responder.config?.provider ?? '',
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const submit = () => {
    form.post(route('features.auto-responders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.channel = 'email';
            form.trigger_event = 'on_lead_received';
            form.status = 'active';
            form.config = { subject: '', body: '', to_field: 'email', provider: '' };
        },
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
            description="Send automated email or SMS when leads are received or sold. Messages use [field] merge tags from lead data."
        >
            <template #actions>
                <Link :href="route('features.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">
                    ← All features
                </Link>
                <AppButton :href="route('automation.index')" variant="secondary">Automation hub</AppButton>
            </template>
        </PageHeader>

        <CompactStatStrip :items="statsStrip" :columns="4" class="mb-6" />

        <div class="mb-6 rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50/80 via-white to-cyan-50/60 p-4 dark:border-indigo-900/50 dark:from-indigo-950/30 dark:via-slate-900 dark:to-cyan-950/20">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">How it works</p>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400">
                <span class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800">Lead event</span>
                <span class="text-slate-400">→</span>
                <span class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-200">Matching responder</span>
                <span class="text-slate-400">→</span>
                <span class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-cyan-800 dark:border-cyan-800 dark:bg-cyan-950/50 dark:text-cyan-200">Email or SMS</span>
                <span class="text-slate-400">→</span>
                <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">Logged on lead</span>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-5">
            <div class="space-y-6 xl:col-span-3">
                <Panel title="Build responder">
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

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="provider" value="Provider (optional)" />
                                <select id="provider" v-model="form.config.provider" class="form-select mt-1 w-full">
                                    <option value="">Platform default</option>
                                    <template v-if="form.channel === 'email'">
                                        <option value="smtp">SMTP</option>
                                        <option value="sendgrid">SendGrid</option>
                                        <option value="mailgun">Mailgun</option>
                                        <option value="postmark">Postmark</option>
                                        <option value="resend">Resend</option>
                                    </template>
                                    <template v-else>
                                        <option value="log">Log (dev)</option>
                                        <option value="twilio">Twilio</option>
                                        <option value="vonage">Vonage</option>
                                    </template>
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
                                    v-for="tag in mergeTags"
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
                                <button v-for="tag in mergeTags" :key="`sub-${tag}`" type="button" class="rounded border px-1.5 py-0.5 text-[10px] text-indigo-600 dark:text-indigo-400" @click="insertTag('subject', tag)">{{ tag }}</button>
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
                                <button v-for="tag in mergeTags" :key="`body-${tag}`" type="button" class="rounded border px-1.5 py-0.5 text-[10px] text-indigo-600 dark:text-indigo-400" @click="insertTag('body', tag)">{{ tag }}</button>
                            </div>
                            <InputError :message="form.errors['config.body']" class="mt-1" />
                        </div>

                        <AppButton type="submit" :disabled="form.processing" :loading="form.processing">
                            Create responder
                        </AppButton>
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
                            </p>
                        </div>

                        <div v-if="form.channel === 'email'" class="p-4">
                            <p class="text-xs text-slate-500">To: {{ sampleFields.email }}</p>
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
                            <p class="mt-3 text-right text-[11px] text-slate-500">To: {{ sampleFields.phone1 }}</p>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Preview uses sample data - real sends use each lead's field values.</p>
                </Panel>

                <Panel title="Configured responders">
                    <div v-if="!responders?.length" class="rounded-xl border border-dashed border-slate-300 py-10 text-center dark:border-slate-700">
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">No auto responders yet</p>
                        <p class="mt-1 text-xs text-slate-500">Pick a quick-start template or build one on the left.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <article
                            v-for="r in responders"
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
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                        @click="duplicateResponder(r)"
                                    >
                                        Duplicate to builder
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
