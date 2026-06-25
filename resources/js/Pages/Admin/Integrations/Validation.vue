<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    settings: Object,
    driver: String,
    demoHints: Array,
});

const page = usePage();
const testResults = page.props.flash?.testResults;

const form = useForm({
    enabled: props.settings?.enabled ?? true,
    email_validation: props.settings?.email_validation ?? true,
    hlr_validation: props.settings?.hlr_validation ?? true,
    quarantine_on_fail: props.settings?.quarantine_on_fail ?? true,
});

const testForm = useForm({
    email: '',
    phone: '',
});

const submit = () => form.put(route('integrations.validation.update'));

const runTest = () => testForm.post(route('integrations.validation.test'), { preserveScroll: true });
</script>

<template>
    <Head title="Email & HLR Validation" />
    <AuthenticatedLayout>
        <PageHeader
            title="Email Validation (HLR)"
            description="Real-time email deliverability and mobile HLR checks on every lead ingest."
        >
            <template #actions>
                <Link :href="route('integrations.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    ← Integrations
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Validation settings">
                <form class="space-y-5" @submit.prevent="submit">
                    <label class="flex items-center gap-3">
                        <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Enable validation on ingest</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input v-model="form.email_validation" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm text-slate-600 dark:text-slate-400">Email deliverability (SMTP check)</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input v-model="form.hlr_validation" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm text-slate-600 dark:text-slate-400">Mobile HLR (reachability)</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input v-model="form.quarantine_on_fail" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm text-slate-600 dark:text-slate-400">Quarantine on validation failure (vs reject)</span>
                    </label>
                    <p class="text-xs text-slate-500">Driver: <span class="font-mono">{{ driver }}</span></p>
                    <AppButton type="submit" :disabled="form.processing">Save settings</AppButton>
                </form>
            </Panel>

            <Panel title="Test validation">
                <form class="space-y-4" @submit.prevent="runTest">
                    <div>
                        <InputLabel for="email" value="Test email" />
                        <input id="email" v-model="testForm.email" type="email" class="form-input mt-1 w-full" placeholder="user@example.com" />
                    </div>
                    <div>
                        <InputLabel for="phone" value="Test phone (HLR)" />
                        <input id="phone" v-model="testForm.phone" type="text" class="form-input mt-1 w-full" placeholder="07700900123" />
                    </div>
                    <AppButton type="submit" variant="secondary" :disabled="testForm.processing">Run test</AppButton>
                </form>

                <div v-if="testResults" class="mt-6 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <div v-if="testResults.email">
                        <p class="text-xs font-semibold uppercase text-slate-500">Email</p>
                        <p :class="testResults.email.passed ? 'text-emerald-600' : 'text-rose-600'">
                            {{ testResults.email.passed ? 'Passed' : testResults.email.reason }}
                        </p>
                        <pre class="mt-1 text-xs text-slate-500">{{ JSON.stringify(testResults.email.meta, null, 2) }}</pre>
                    </div>
                    <div v-if="testResults.phone">
                        <p class="text-xs font-semibold uppercase text-slate-500">HLR</p>
                        <p :class="testResults.phone.passed ? 'text-emerald-600' : 'text-rose-600'">
                            {{ testResults.phone.passed ? 'Passed' : testResults.phone.reason }}
                        </p>
                        <pre class="mt-1 text-xs text-slate-500">{{ JSON.stringify(testResults.phone.meta, null, 2) }}</pre>
                    </div>
                </div>

                <ul class="mt-6 list-disc space-y-1 pl-5 text-xs text-slate-500">
                    <li v-for="(hint, i) in demoHints" :key="i">{{ hint }}</li>
                </ul>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
