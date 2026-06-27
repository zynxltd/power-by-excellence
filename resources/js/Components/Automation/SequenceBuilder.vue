<script setup>
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { MERGE_TAGS, SAMPLE_FIELDS, interpolatePreview, normalizeMergeTags } from '@/Composables/useMergeTags';

const props = defineProps({
    sequences: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    providers: { type: Object, default: () => ({ sms: [], email: [] }) },
});

const editingId = ref(null);
const expandedId = ref(null);
const previewStepIndex = ref(0);

const defaultStep = (channel = 'email') => ({
    delay_minutes: 0,
    channel,
    config: channel === 'email'
        ? { subject: 'Thanks for your enquiry', body: 'Hi [firstname],\n\nWe received your request and will follow up shortly.', to_field: 'email', provider: '' }
        : { body: 'Hi [firstname], thanks for your enquiry. We will be in touch soon.', to_field: 'phone1', provider: '' },
});

const form = useForm({
    name: '',
    campaign_id: '',
    trigger_event: 'on_lead_received',
    status: 'active',
    steps: [defaultStep()],
});

const triggerOptions = [
    {
        value: 'on_lead_received',
        label: 'Lead received',
        description: 'Starts when a lead is ingested - welcome and nurture flows.',
        tone: 'indigo',
    },
    {
        value: 'on_lead_sold',
        label: 'Lead sold',
        description: 'Starts after a buyer accepts - handoff or thank-you series.',
        tone: 'emerald',
    },
    {
        value: 'on_lead_unsold',
        label: 'Lead unsold',
        description: 'Starts when distribution completes without a sale - recovery outreach.',
        tone: 'amber',
    },
];

const presets = [
    {
        name: 'Welcome + 1 day follow-up',
        trigger_event: 'on_lead_received',
        steps: [
            defaultStep('email'),
            { delay_minutes: 1440, channel: 'email', config: { subject: 'Still interested, [firstname]?', body: 'Hi [firstname],\n\nJust checking in on your enquiry. Reply if you still need help.', to_field: 'email', provider: '' } },
        ],
    },
    {
        name: 'SMS confirm + sold email',
        trigger_event: 'on_lead_sold',
        steps: [
            { delay_minutes: 0, channel: 'sms', config: { body: 'Hi [firstname], your request was matched. A partner may contact you at [phone1].', to_field: 'phone1', provider: '' } },
            { delay_minutes: 30, channel: 'email', config: { subject: 'Your match details, [firstname]', body: 'Hi [firstname],\n\nGood news - we matched you with a partner. They may reach you at [email] or [phone1].', to_field: 'email', provider: '' } },
        ],
    },
    {
        name: 'Unsold recovery (3 touch)',
        trigger_event: 'on_lead_unsold',
        steps: [
            { delay_minutes: 60, channel: 'email', config: { subject: 'Can we still help, [firstname]?', body: 'Hi [firstname],\n\nWe could not match your request yet. Would you like us to try again?', to_field: 'email', provider: '' } },
            { delay_minutes: 1440, channel: 'sms', config: { body: 'Hi [firstname], still interested? Reply YES and we will retry matching you.', to_field: 'phone1', provider: '' } },
            { delay_minutes: 4320, channel: 'email', config: { subject: 'Last chance to reconnect', body: 'Hi [firstname],\n\nThis is our final follow-up on your enquiry.', to_field: 'email', provider: '' } },
        ],
    },
];

const triggerLabels = Object.fromEntries(triggerOptions.map((t) => [t.value, t.label]));
const channelIcons = { email: '✉️', sms: '💬' };

const statsStrip = computed(() => {
    const list = props.sequences ?? [];
    const active = list.filter((s) => (s.status ?? 'active') === 'active').length;
    const stepCount = list.reduce((sum, s) => sum + (s.steps?.length ?? 0), 0);

    return [
        { label: 'Sequences', value: list.length, accent: 'indigo' },
        { label: 'Active', value: active, accent: 'emerald' },
        { label: 'Total steps', value: stepCount, accent: 'cyan' },
        { label: 'SMS steps', value: list.flatMap((s) => s.steps ?? []).filter((st) => st.channel === 'sms').length, accent: 'violet' },
    ];
});

const toneClasses = (tone, selected) => {
    const map = {
        indigo: selected ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200 dark:border-indigo-500 dark:bg-indigo-950/40 dark:ring-indigo-800' : 'border-slate-200 hover:border-indigo-300 dark:border-slate-700',
        emerald: selected ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200 dark:border-emerald-500 dark:bg-emerald-950/40 dark:ring-emerald-800' : 'border-slate-200 hover:border-emerald-300 dark:border-slate-700',
        amber: selected ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-200 dark:border-amber-500 dark:bg-amber-950/40 dark:ring-amber-800' : 'border-slate-200 hover:border-amber-300 dark:border-slate-700',
    };

    return map[tone] ?? map.indigo;
};

const formatDelay = (minutes) => {
    if (!minutes) return 'Immediately';
    if (minutes < 60) return `${minutes}m`;
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h < 24) return m ? `${h}h ${m}m` : `${h}h`;
    const d = Math.floor(h / 24);
    const rh = h % 24;
    return rh ? `${d}d ${rh}h` : `${d}d`;
};

const cumulativeDelay = (steps, index) => steps.slice(0, index + 1).reduce((sum, s) => sum + (Number(s.delay_minutes) || 0), 0);

const totalSpan = computed(() => cumulativeDelay(form.steps, form.steps.length - 1));

const previewStep = computed(() => form.steps[previewStepIndex.value] ?? form.steps[0]);

const previewSubject = computed(() => interpolatePreview(previewStep.value?.config?.subject ?? ''));
const previewBody = computed(() => interpolatePreview(previewStep.value?.config?.body ?? ''));
const smsCharCount = computed(() => (previewStep.value?.config?.body ?? '').length);

const normalizeFormSteps = () => {
    form.steps = form.steps.map((step) => ({
        ...step,
        config: {
            ...step.config,
            subject: step.config?.subject ? normalizeMergeTags(step.config.subject) : step.config?.subject,
            body: step.config?.body ? normalizeMergeTags(step.config.body) : step.config?.body,
        },
    }));
};

const ensureStepConfig = (step) => {
    if (!step.config) step.config = {};
    if (step.channel === 'email') {
        step.config.subject ??= 'Thanks for your enquiry';
        step.config.body ??= 'Hi [firstname], we received your request.';
        step.config.to_field ??= 'email';
        step.config.provider ??= '';
    } else {
        step.config.body ??= 'Hi [firstname], thanks for your enquiry.';
        step.config.to_field ??= 'phone1';
        step.config.provider ??= '';
    }
};

const addStep = (channel = 'email') => {
    const step = defaultStep(channel);
    step.delay_minutes = form.steps.length ? 60 : 0;
    form.steps.push(step);
    previewStepIndex.value = form.steps.length - 1;
};

const removeStep = (index) => {
    if (form.steps.length <= 1) return;
    form.steps.splice(index, 1);
    previewStepIndex.value = Math.min(previewStepIndex.value, form.steps.length - 1);
};

const moveStep = (index, direction) => {
    const target = index + direction;
    if (target < 0 || target >= form.steps.length) return;
    const steps = [...form.steps];
    [steps[index], steps[target]] = [steps[target], steps[index]];
    form.steps = steps;
    previewStepIndex.value = target;
};

const insertMergeTag = (step, field, tag) => {
    ensureStepConfig(step);
    step.config[field] = `${step.config[field] ?? ''}${tag}`;
};

const applyPreset = (preset) => {
    editingId.value = null;
    form.name = preset.name;
    form.trigger_event = preset.trigger_event;
    form.steps = preset.steps.map((s) => ({ ...s, config: { ...s.config } }));
    previewStepIndex.value = 0;
};

const resetBuilder = () => {
    editingId.value = null;
    form.reset();
    form.trigger_event = 'on_lead_received';
    form.status = 'active';
    form.steps = [defaultStep()];
    previewStepIndex.value = 0;
};

const loadSequence = (sequence) => {
    editingId.value = sequence.id;
    form.name = sequence.name;
    form.campaign_id = sequence.campaign_id ?? '';
    form.trigger_event = sequence.trigger_event;
    form.status = sequence.status ?? 'active';
    form.steps = (sequence.steps ?? []).map((step) => ({
        delay_minutes: step.delay_minutes ?? 0,
        channel: step.channel,
        config: { ...(step.config ?? {}) },
    }));
    if (!form.steps.length) form.steps = [defaultStep()];
    previewStepIndex.value = 0;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const duplicateSequence = (sequence) => {
    loadSequence(sequence);
    editingId.value = null;
    form.name = `${sequence.name} (copy)`;
};

const submit = () => {
    form.steps.forEach(ensureStepConfig);
    normalizeFormSteps();

    const options = {
        preserveScroll: true,
        onSuccess: () => resetBuilder(),
    };

    if (editingId.value) {
        form.patch(route('automation.sequences.update', editingId.value), options);
    } else {
        form.post(route('automation.sequences.store'), options);
    }
};

const destroySequence = (id) => {
    if (confirm('Delete this automation sequence?')) {
        router.delete(route('automation.sequences.destroy', id));
    }
};

const toggleExpanded = (id) => {
    expandedId.value = expandedId.value === id ? null : id;
};

watch(() => form.steps.length, (len) => {
    if (previewStepIndex.value >= len) previewStepIndex.value = Math.max(0, len - 1);
});
</script>

<template>
    <CompactStatStrip :items="statsStrip" :columns="4" class="mb-6" />

    <div class="mb-6 rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50/80 via-white to-violet-50/60 p-4 dark:border-indigo-900/50 dark:from-indigo-950/30 dark:via-slate-900 dark:to-violet-950/20">
        <p class="text-sm font-semibold text-slate-900 dark:text-white">Multi-step nurture flows</p>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400">
            <span class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800">Trigger event</span>
            <span class="text-slate-400">→</span>
            <span class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-200">Step 1 (instant or delayed)</span>
            <span class="text-slate-400">→</span>
            <span class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-violet-800 dark:border-violet-800 dark:bg-violet-950/50 dark:text-violet-200">Step 2+ (cumulative wait)</span>
            <span class="text-slate-400">→</span>
            <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">Email / SMS via provider</span>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-5">
        <div class="space-y-6 xl:col-span-3">
            <Panel :title="editingId ? 'Edit sequence' : 'Build sequence'">
                <form class="space-y-6" @submit.prevent="submit">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Quick start templates</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="preset in presets"
                                :key="preset.name"
                                type="button"
                                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                @click="applyPreset(preset)"
                            >
                                {{ preset.name }}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Name" />
                            <TextInput v-model="form.name" class="mt-1 block w-full" required placeholder="e.g. Welcome nurture - auto insurance" />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel value="Campaign scope" />
                            <select v-model="form.campaign_id" class="form-select mt-1 w-full">
                                <option value="">All campaigns on this platform</option>
                                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Required when no tenant is selected (super admin central view).</p>
                            <InputError class="mt-1" :message="form.errors.campaign_id" />
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">When should this sequence start?</p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <button
                                v-for="trigger in triggerOptions"
                                :key="trigger.value"
                                type="button"
                                :class="['rounded-xl border p-3 text-left transition', toneClasses(trigger.tone, form.trigger_event === trigger.value)]"
                                @click="form.trigger_event = trigger.value"
                            >
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ trigger.label }}</p>
                                <p class="mt-1 text-[11px] leading-snug text-slate-500">{{ trigger.description }}</p>
                            </button>
                        </div>
                    </div>

                    <div class="rounded-xl border border-violet-200 bg-violet-50/50 p-3 dark:border-violet-900 dark:bg-violet-950/20">
                        <p class="text-xs font-semibold uppercase text-violet-700 dark:text-violet-300">Merge tags - click to insert into selected step</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="tag in MERGE_TAGS"
                                :key="tag"
                                type="button"
                                class="rounded-md border border-violet-200 bg-white px-2 py-1 font-mono text-xs text-violet-800 transition hover:bg-violet-100 dark:border-violet-800 dark:bg-slate-900 dark:text-violet-300"
                                @click="insertMergeTag(previewStep, previewStep.channel === 'email' ? 'body' : 'body', tag)"
                            >
                                {{ tag }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <InputLabel value="Sequence timeline" />
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400" @click="addStep('email')">+ Email step</button>
                                <button type="button" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400" @click="addStep('sms')">+ SMS step</button>
                            </div>
                        </div>

                        <div class="relative space-y-0">
                            <div v-for="(step, i) in form.steps" :key="i" class="relative">
                                <div
                                    v-if="i > 0"
                                    class="ml-8 flex h-10 items-center border-l-2 border-dashed border-indigo-300 pl-5 text-xs text-slate-500 dark:border-indigo-700"
                                >
                                    Wait {{ formatDelay(step.delay_minutes) }}
                                    <span class="ml-2 rounded bg-indigo-100 px-1.5 py-0.5 font-medium text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                        T+{{ formatDelay(cumulativeDelay(form.steps, i)) }}
                                    </span>
                                </div>

                                <div
                                    :class="[
                                        'rounded-2xl border-2 p-4 shadow-sm transition',
                                        previewStepIndex === i ? 'border-indigo-400 ring-2 ring-indigo-200 dark:ring-indigo-800' : 'border-slate-200 dark:border-slate-700',
                                        step.channel === 'email' ? 'border-l-4 border-l-indigo-500 bg-white dark:bg-slate-900' : 'border-l-4 border-l-emerald-500 bg-white dark:bg-slate-900',
                                    ]"
                                    @click="previewStepIndex = i"
                                >
                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">{{ i + 1 }}</span>
                                            <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ channelIcons[step.channel] }} {{ step.channel === 'email' ? 'Email' : 'SMS' }}</span>
                                            <span v-if="i === 0 && !step.delay_minutes" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">Instant</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button type="button" class="rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800" :disabled="i === 0" @click.stop="moveStep(i, -1)">↑</button>
                                            <button type="button" class="rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800" :disabled="i === form.steps.length - 1" @click.stop="moveStep(i, 1)">↓</button>
                                            <button v-if="form.steps.length > 1" type="button" class="rounded px-2 py-1 text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30" @click.stop="removeStep(i)">Remove</button>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1 block text-xs text-slate-500">Wait after {{ i === 0 ? 'trigger' : 'previous step' }} (minutes)</label>
                                            <input v-model.number="step.delay_minutes" type="number" min="0" class="form-input w-full" @focus="previewStepIndex = i" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs text-slate-500">Channel</label>
                                            <select v-model="step.channel" class="form-select w-full" @change="ensureStepConfig(step)" @focus="previewStepIndex = i">
                                                <option value="email">Email</option>
                                                <option value="sms">SMS</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs text-slate-500">Send to field</label>
                                            <select v-model="step.config.to_field" class="form-select w-full" @focus="ensureStepConfig(step); previewStepIndex = i">
                                                <option value="email">email</option>
                                                <option value="phone1">phone1</option>
                                                <option value="phone2">phone2</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs text-slate-500">Provider (optional)</label>
                                            <select v-model="step.config.provider" class="form-select w-full" @focus="ensureStepConfig(step); previewStepIndex = i">
                                                <option value="">Platform default</option>
                                                <option v-for="p in (providers?.[step.channel] ?? [])" :key="p" :value="p">{{ p }}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div v-if="step.channel === 'email'" class="mt-3 space-y-2">
                                        <div>
                                            <label class="mb-1 block text-xs text-slate-500">Subject</label>
                                            <input v-model="step.config.subject" type="text" class="form-input w-full font-mono text-sm" @focus="ensureStepConfig(step); previewStepIndex = i" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs text-slate-500">Body</label>
                                            <textarea v-model="step.config.body" rows="4" class="form-input w-full font-mono text-sm" @focus="ensureStepConfig(step); previewStepIndex = i" />
                                        </div>
                                    </div>
                                    <div v-else class="mt-3">
                                        <div class="flex items-center justify-between">
                                            <label class="mb-1 block text-xs text-slate-500">SMS message</label>
                                            <span :class="['text-xs font-medium', (step.config.body?.length ?? 0) > 160 ? 'text-amber-600' : 'text-slate-500']">
                                                {{ step.config.body?.length ?? 0 }} / 160
                                            </span>
                                        </div>
                                        <textarea v-model="step.config.body" rows="3" class="form-input w-full font-mono text-sm" maxlength="320" @focus="ensureStepConfig(step); previewStepIndex = i" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">
                            Total span from trigger to final step: <strong>{{ formatDelay(totalSpan) }}</strong>.
                            Delays are cumulative - step 2 at 60m runs 60m after trigger if step 1 is instant.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <AppButton type="submit" :disabled="form.processing" :loading="form.processing">
                            {{ editingId ? 'Save changes' : 'Create sequence' }}
                        </AppButton>
                        <AppButton v-if="editingId || form.name" type="button" variant="secondary" @click="resetBuilder">Cancel</AppButton>
                    </div>
                </form>
            </Panel>
        </div>

        <div class="space-y-6 xl:col-span-2">
            <Panel title="Step preview">
                <div class="mb-3 flex flex-wrap gap-1">
                    <button
                        v-for="(step, i) in form.steps"
                        :key="`preview-${i}`"
                        type="button"
                        :class="[
                            'rounded-lg px-2.5 py-1 text-xs font-semibold transition',
                            previewStepIndex === i ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                        ]"
                        @click="previewStepIndex = i"
                    >
                        Step {{ i + 1 }}
                    </button>
                </div>

                <div
                    :class="[
                        'overflow-hidden rounded-xl border shadow-sm',
                        previewStep?.channel === 'email'
                            ? 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900'
                            : 'border-violet-200 bg-gradient-to-br from-violet-50 to-white dark:border-violet-900 dark:from-violet-950/30 dark:to-slate-900',
                    ]"
                >
                    <div class="border-b border-slate-100 px-4 py-2.5 dark:border-slate-800">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                            {{ previewStep?.channel === 'email' ? 'Email preview' : 'SMS preview' }}
                            · T+{{ formatDelay(cumulativeDelay(form.steps, previewStepIndex)) }}
                        </p>
                    </div>

                    <div v-if="previewStep?.channel === 'email'" class="p-4">
                        <p class="text-xs text-slate-500">To: {{ SAMPLE_FIELDS.email }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ previewSubject || 'Subject line…' }}</p>
                        <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ previewBody || 'Message body with sample merge data.' }}</p>
                    </div>
                    <div v-else class="p-4">
                        <div class="ml-auto max-w-[85%] rounded-2xl rounded-br-md bg-violet-600 px-4 py-3 text-sm leading-relaxed text-white shadow-md">
                            {{ previewBody || 'SMS preview…' }}
                        </div>
                        <p class="mt-3 text-right text-[11px] text-slate-500">To: {{ SAMPLE_FIELDS.phone1 }} · {{ smsCharCount }} chars</p>
                    </div>
                </div>
            </Panel>

            <Panel title="Active sequences">
                <div v-if="!sequences?.length" class="rounded-xl border border-dashed border-slate-300 py-10 text-center dark:border-slate-700">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">No sequences yet</p>
                    <p class="mt-1 text-xs text-slate-500">Pick a template or build a multi-step flow on the left.</p>
                </div>

                <div v-else class="space-y-3">
                    <article
                        v-for="seq in sequences"
                        :key="seq.id"
                        class="rounded-xl border border-slate-200 bg-slate-50/50 transition hover:border-indigo-200 dark:border-slate-700 dark:bg-slate-800/30 dark:hover:border-indigo-800"
                    >
                        <button type="button" class="flex w-full items-start gap-3 p-4 text-left" @click="toggleExpanded(seq.id)">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-lg dark:bg-indigo-900/40">⚡</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ seq.name }}</p>
                                    <StatusBadge :status="seq.status ?? 'active'" />
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ triggerLabels[seq.trigger_event] ?? seq.trigger_event }}
                                    · {{ seq.steps?.length ?? 0 }} steps
                                    · {{ seq.campaign?.name ?? 'All campaigns' }}
                                </p>
                            </div>
                        </button>

                        <div v-if="expandedId === seq.id" class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">
                            <ol class="space-y-2 border-l-2 border-indigo-200 pl-4 dark:border-indigo-800">
                                <li v-for="(step, si) in seq.steps" :key="si" class="text-xs text-slate-600 dark:text-slate-400">
                                    <span class="font-semibold text-slate-800 dark:text-slate-200">Step {{ si + 1 }}</span>
                                    - {{ channelIcons[step.channel] }} {{ step.channel }}
                                    <span v-if="step.delay_minutes"> after {{ formatDelay(step.delay_minutes) }}</span>
                                    <span v-else-if="si === 0"> immediately</span>
                                    <span class="text-slate-400"> · T+{{ formatDelay(cumulativeDelay(seq.steps, si)) }}</span>
                                    <p v-if="step.config?.subject" class="truncate text-slate-500">Subject: {{ step.config.subject }}</p>
                                </li>
                            </ol>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300" @click="loadSequence(seq)">Edit</button>
                                <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300" @click="duplicateSequence(seq)">Duplicate</button>
                                <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/30" @click="destroySequence(seq.id)">Delete</button>
                            </div>
                        </div>
                    </article>
                </div>
            </Panel>
        </div>
    </div>
</template>
