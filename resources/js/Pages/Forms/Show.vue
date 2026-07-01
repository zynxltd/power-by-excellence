<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    form: Object,
    steps: Array,
    multiStep: Boolean,
    submitUrl: String,
    statusUrl: String,
    thankYou: Object,
    consent: { type: Object, default: () => ({}) },
    submitted: Boolean,
    submission: Object,
    embed: { type: Boolean, default: false },
    tracking: { type: Object, default: () => ({}) },
    trackingParams: { type: Array, default: () => [] },
});

const currentStep = ref(0);
const activeSteps = computed(() => props.steps ?? []);
const isLastStep = computed(() => currentStep.value >= activeSteps.value.length - 1);

const initialFieldData = () => {
    const data = {};
    for (const step of activeSteps.value) {
        for (const field of step.fields ?? []) {
            data[field.name] = field.type === 'checkbox' ? false : '';
        }
    }
    return data;
};

const trackingPayload = () => {
    const payload = { ...(props.tracking ?? {}) };
    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        for (const key of props.trackingParams ?? []) {
            const value = params.get(key);
            if (value) payload[key] = value;
        }
    }
    if (payload.subid && !payload.ssid) payload.ssid = payload.subid;
    return payload;
};

const leadForm = useForm({
    ...initialFieldData(),
    ...trackingPayload(),
    embed: props.embed ? '1' : '',
    consent_accepted: false,
    channel_consent: {
        email: false,
        sms: false,
        phone: false,
    },
});

onMounted(() => {
    Object.assign(leadForm, trackingPayload());
    if (props.embed) leadForm.embed = '1';
});

const progress = computed(() => ((currentStep.value + 1) / Math.max(activeSteps.value.length, 1)) * 100);

const next = () => {
    if (!isLastStep.value) currentStep.value++;
};

const back = () => {
    if (currentStep.value > 0) currentStep.value--;
};

const submit = () => {
    if (props.embed) leadForm.embed = '1';
    leadForm.post(props.submitUrl, { preserveScroll: true });
};

const resetForm = () => {
    const query = window.location.search;
    router.visit(route('forms.show', props.form.slug) + query, { preserveState: false });
};

const notifyParent = () => {
    if (!props.embed || window.parent === window || !props.submitted) return;
    window.parent.postMessage({
        type: 'pbe:form:submitted',
        slug: props.form.slug,
        queue_id: props.submission?.queue_id ?? null,
        uuid: props.submission?.uuid ?? null,
    }, '*');
};

watch(() => props.submitted, (value) => {
    if (value) {
        notifyParent();
        if (isPollRedirectMode.value && props.submission?.uuid) {
            startPolling();
        }
    }
}, { immediate: true });

const isPollRedirectMode = computed(() => props.thankYou?.mode === 'poll_redirect');
const isProcessing = ref(false);
const pollComplete = ref(false);
const pollError = ref(null);

const showThankYou = computed(() => props.submitted && (!isPollRedirectMode.value || pollComplete.value));
const showProcessing = computed(() => props.submitted && isPollRedirectMode.value && isProcessing.value && !pollComplete.value);

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const resolveStatusUrl = (uuid) => (props.statusUrl ?? '').replace('__UUID__', uuid);

const redirectTo = (url) => {
    if (!url) return;
    window.location.assign(url);
};

const startPolling = async () => {
    const uuid = props.submission?.uuid;
    if (!uuid || !props.statusUrl) return;

    isProcessing.value = true;
    pollComplete.value = false;
    pollError.value = null;

    const interval = props.thankYou?.poll_interval_ms ?? 1500;
    const maxAttempts = props.thankYou?.poll_max_attempts ?? 40;

    for (let attempt = 0; attempt < maxAttempts; attempt++) {
        try {
            const response = await fetch(resolveStatusUrl(uuid), {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Status check failed');
            }

            const data = await response.json();

            if (data.redirect_url) {
                redirectTo(data.redirect_url);
                return;
            }

            if (data.terminal) {
                if (data.decline_url) {
                    redirectTo(data.decline_url);
                    return;
                }

                if (props.thankYou?.fallback_redirect_url) {
                    redirectTo(props.thankYou.fallback_redirect_url);
                    return;
                }

                isProcessing.value = false;
                pollComplete.value = true;
                return;
            }
        } catch {
            pollError.value = 'We could not confirm your application status. Please try again in a moment.';
            break;
        }

        await sleep(interval);
    }

    isProcessing.value = false;
    if (!pollComplete.value && !pollError.value) {
        pollError.value = 'This is taking longer than expected. You can close this page — we will email you if we need anything else.';
        pollComplete.value = true;
    }
};

const showConfetti = computed(() => showThankYou.value && (props.thankYou?.confetti ?? true) && !props.embed);
const showSubmitAnother = computed(() => props.thankYou?.show_submit_another ?? true);

const consentRequired = computed(() => props.consent?.require_consent ?? false);
const consentChannels = computed(() => props.consent?.channel_consent_channels ?? []);
const channelLabel = (channel) => ({
    email: 'Email',
    sms: 'SMS',
    phone: 'Phone calls',
}[channel] ?? channel);
</script>

<template>
    <Head :title="showThankYou ? thankYou?.title ?? 'Thank you' : form.name" />
    <div
        :class="[
            'relative min-h-dvh overflow-hidden',
            embed
                ? 'min-h-dvh bg-white px-3 py-3 sm:py-4 dark:bg-slate-950'
                : 'min-h-dvh bg-gradient-to-b from-slate-50 to-slate-100 px-4 py-6 sm:py-10 dark:from-slate-950 dark:to-slate-900',
        ]"
    >
        <div v-if="showConfetti" class="confetti pointer-events-none absolute inset-0 z-10" aria-hidden="true">
            <span v-for="n in 24" :key="n" class="confetti-piece" :style="{ '--i': n }" />
        </div>

        <div :class="['relative z-20 mx-auto', embed ? 'max-w-full' : 'max-w-lg']">
            <!-- Processing (poll & redirect) -->
            <div
                v-if="showProcessing"
                class="thank-you-card rounded-2xl border border-indigo-200 bg-white p-6 text-center shadow-xl sm:p-8 dark:border-indigo-900/50 dark:bg-slate-900"
            >
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/40">
                    <svg class="h-8 w-8 animate-spin text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ thankYou?.processing_title ?? 'Processing your application…' }}</h1>
                <p class="mt-3 text-slate-600 dark:text-slate-400">{{ thankYou?.processing_message ?? 'Please wait while we match you with a provider.' }}</p>
                <p v-if="thankYou?.show_reference && submission?.queue_id" class="mt-4 rounded-lg bg-slate-50 px-4 py-3 font-mono text-xs text-slate-500 dark:bg-slate-800">
                    Reference: {{ submission.queue_id }}
                </p>
            </div>

            <!-- Thank you screen -->
            <div
                v-else-if="showThankYou"
                class="thank-you-card rounded-2xl border border-emerald-200 bg-white p-6 text-center shadow-xl sm:p-8 dark:border-emerald-900/50 dark:bg-slate-900"
            >
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                    <svg class="h-8 w-8 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ thankYou?.title ?? 'Thank you!' }}</h1>
                <p class="mt-3 text-slate-600 dark:text-slate-400">{{ thankYou?.message }}</p>
                <p v-if="pollError" class="mt-3 text-sm text-amber-700 dark:text-amber-300">{{ pollError }}</p>
                <p v-if="thankYou?.show_reference && submission?.queue_id" class="mt-4 rounded-lg bg-slate-50 px-4 py-3 font-mono text-xs text-slate-500 dark:bg-slate-800">
                    Reference: {{ submission.queue_id }}
                </p>
                <button
                    v-if="showSubmitAnother"
                    type="button"
                    class="mt-6 w-full rounded-xl bg-indigo-600 px-4 py-3 font-semibold text-white transition hover:bg-indigo-500"
                    @click="resetForm"
                >
                    {{ thankYou?.button_text ?? 'Submit another response' }}
                </button>
            </div>

            <!-- Form -->
            <template v-else>
                <div v-if="multiStep && activeSteps.length > 1" class="mb-6">
                    <div class="mb-2 flex justify-between text-xs font-medium text-slate-500">
                        <span>Step {{ currentStep + 1 }} of {{ activeSteps.length }}</span>
                        <span>{{ Math.round(progress) }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-gradient-to-r from-violet-600 to-cyan-500 transition-all duration-300" :style="{ width: progress + '%' }" />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-lg sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ activeSteps[currentStep]?.title ?? form.name }}</h1>
                    <p v-if="activeSteps[currentStep]?.description" class="mt-1 text-sm text-slate-500">{{ activeSteps[currentStep].description }}</p>

                    <form class="mt-6 space-y-5" @submit.prevent="isLastStep ? submit() : next()">
                        <input v-for="key in trackingParams" :key="key" type="hidden" :name="key" :value="leadForm[key] ?? ''" />
                        <input v-if="embed" type="hidden" name="embed" value="1" />

                        <div v-for="field in activeSteps[currentStep]?.fields ?? []" :key="field.name">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ field.label }}<span v-if="field.required" class="text-rose-500"> *</span>
                            </label>

                            <input
                                v-if="['text','email','tel','number','postcode','date'].includes(field.type)"
                                v-model="leadForm[field.name]"
                                :type="field.type === 'postcode' ? 'text' : field.type"
                                :required="field.required"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-base dark:border-slate-700 dark:bg-slate-800"
                            />

                            <textarea
                                v-else-if="field.type === 'textarea'"
                                v-model="leadForm[field.name]"
                                :required="field.required"
                                rows="3"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-base dark:border-slate-700 dark:bg-slate-800"
                            />

                            <select
                                v-else-if="field.type === 'select'"
                                v-model="leadForm[field.name]"
                                :required="field.required"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-base dark:border-slate-700 dark:bg-slate-800"
                            >
                                <option value="">Select…</option>
                                <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                            </select>

                            <div v-else-if="field.type === 'radio'" class="mt-2 space-y-2">
                                <label v-for="opt in field.options" :key="opt" class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-indigo-400 dark:border-slate-700">
                                    <input v-model="leadForm[field.name]" type="radio" :value="opt" :required="field.required" class="text-indigo-600" />
                                    <span class="text-sm">{{ opt }}</span>
                                </label>
                            </div>

                            <label v-else-if="field.type === 'checkbox'" class="mt-2 flex items-center gap-2 text-sm">
                                <input v-model="leadForm[field.name]" type="checkbox" class="rounded text-indigo-600" />
                                {{ field.options?.[0] ?? 'Yes' }}
                            </label>
                        </div>

                        <div
                            v-if="consentRequired && isLastStep"
                            class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Consent</p>
                            <p class="text-sm text-slate-600 dark:text-slate-300">{{ consent.consent_text }}</p>
                            <p v-if="consent.lawful_basis" class="text-xs text-slate-500">
                                Lawful basis: {{ consent.lawful_basis.replace(/_/g, ' ') }}
                            </p>
                            <label class="flex items-start gap-3 text-sm">
                                <input v-model="leadForm.consent_accepted" type="checkbox" class="mt-1 rounded text-indigo-600" required />
                                <span>I agree to the statement above<span class="text-rose-500"> *</span></span>
                            </label>
                            <p v-if="leadForm.errors.consent_accepted" class="text-sm text-rose-600">{{ leadForm.errors.consent_accepted }}</p>
                            <div v-if="consentChannels.length" class="space-y-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                                <p class="text-xs font-medium text-slate-500">Channel preferences (optional)</p>
                                <label
                                    v-for="channel in consentChannels"
                                    :key="channel"
                                    class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"
                                >
                                    <input v-model="leadForm.channel_consent[channel]" type="checkbox" class="rounded text-indigo-600" />
                                    {{ channelLabel(channel) }}
                                </label>
                            </div>
                        </div>

                        <p v-if="leadForm.hasErrors" class="text-sm text-rose-600">Please check your answers and try again.</p>

                        <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                            <button v-if="currentStep > 0" type="button" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold dark:border-slate-700 sm:order-none" @click="back">Back</button>
                            <button type="submit" class="w-full rounded-xl bg-indigo-600 px-4 py-3.5 text-base font-semibold text-white hover:bg-indigo-500 disabled:opacity-60 sm:flex-1" :disabled="leadForm.processing">
                                {{ isLastStep ? (leadForm.processing ? 'Submitting…' : 'Submit') : 'Continue →' }}
                            </button>
                        </div>
                    </form>
                </div>
            </template>

            <p v-if="!embed" class="mt-4 text-center text-xs text-slate-400">Powered by PowerByExcellence</p>
        </div>
    </div>
</template>

<style scoped>
.thank-you-card {
    animation: thank-you-in 0.5s ease-out;
}
@keyframes thank-you-in {
    from { opacity: 0; transform: translateY(12px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.confetti-piece {
    position: absolute;
    top: -10%;
    left: calc(var(--i) * 4%);
    width: 8px;
    height: 14px;
    background: hsl(calc(var(--i) * 15), 80%, 55%);
    opacity: 0;
    animation: confetti-fall 2.5s ease-out forwards;
    animation-delay: calc(var(--i) * 0.05s);
    transform: rotate(calc(var(--i) * 24deg));
}
@keyframes confetti-fall {
    0% { opacity: 1; transform: translateY(0) rotate(0deg); }
    100% { opacity: 0; transform: translateY(100vh) rotate(720deg); }
}
</style>
