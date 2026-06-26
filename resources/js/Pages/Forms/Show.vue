<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    form: Object,
    steps: Array,
    multiStep: Boolean,
    submitUrl: String,
    thankYou: Object,
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
    if (value) notifyParent();
}, { immediate: true });

const showConfetti = computed(() => props.submitted && (props.thankYou?.confetti ?? true) && !props.embed);
</script>

<template>
    <Head :title="submitted ? thankYou?.title ?? 'Thank you' : form.name" />
    <div
        :class="[
            'relative min-h-screen overflow-hidden',
            embed
                ? 'bg-white px-3 py-4 dark:bg-slate-950'
                : 'bg-gradient-to-b from-slate-50 to-slate-100 px-4 py-10 dark:from-slate-950 dark:to-slate-900',
        ]"
    >
        <div v-if="showConfetti" class="confetti pointer-events-none absolute inset-0 z-10" aria-hidden="true">
            <span v-for="n in 24" :key="n" class="confetti-piece" :style="{ '--i': n }" />
        </div>

        <div :class="['relative z-20 mx-auto', embed ? 'max-w-full' : 'max-w-lg']">
            <!-- Thank you screen -->
            <div
                v-if="submitted"
                class="thank-you-card rounded-2xl border border-emerald-200 bg-white p-8 text-center shadow-xl dark:border-emerald-900/50 dark:bg-slate-900"
            >
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                    <svg class="h-8 w-8 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ thankYou?.title ?? 'Thank you!' }}</h1>
                <p class="mt-3 text-slate-600 dark:text-slate-400">{{ thankYou?.message }}</p>
                <p v-if="thankYou?.show_reference && submission?.queue_id" class="mt-4 rounded-lg bg-slate-50 px-4 py-3 font-mono text-xs text-slate-500 dark:bg-slate-800">
                    Reference: {{ submission.queue_id }}
                </p>
                <button
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

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-lg dark:border-slate-800 dark:bg-slate-900">
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
                                class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-800"
                            />

                            <textarea
                                v-else-if="field.type === 'textarea'"
                                v-model="leadForm[field.name]"
                                :required="field.required"
                                rows="3"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-800"
                            />

                            <select
                                v-else-if="field.type === 'select'"
                                v-model="leadForm[field.name]"
                                :required="field.required"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-800"
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

                        <p v-if="leadForm.hasErrors" class="text-sm text-rose-600">Please check your answers and try again.</p>

                        <div class="flex gap-3 pt-2">
                            <button v-if="currentStep > 0" type="button" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold dark:border-slate-700" @click="back">Back</button>
                            <button type="submit" class="flex-1 rounded-xl bg-indigo-600 px-4 py-3 font-semibold text-white hover:bg-indigo-500 disabled:opacity-60" :disabled="leadForm.processing">
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
