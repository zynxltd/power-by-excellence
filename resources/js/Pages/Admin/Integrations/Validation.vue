<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { pushToast } from '@/Composables/useToast';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    settings: Object,
    driver: String,
    hasIpqsKey: Boolean,
    fraudProtection: Object,
    planFeatures: Array,
    demoHints: Array,
});

const ipqsDefaults = {
    api_key: '',
    fraud_score_threshold: 85,
    url_risk_threshold: 85,
    email_timeout: 7,
    email_fast: false,
    email_abuse_strictness: 0,
    block_disposable_email: true,
    block_spam_trap_email: true,
    block_catch_all_email: false,
    block_recent_abuse_email: true,
    block_leaked_email: false,
    phone_countries: 'GB,US,IE',
    block_voip: false,
    block_prepaid: false,
    block_risky_phone: true,
    block_recent_abuse_phone: true,
    block_spammer_phone: true,
    strictness: 1,
    allow_public_access_points: true,
    lighter_penalties: false,
    ip_fast: false,
    block_vpn: true,
    block_proxy: true,
    block_tor: true,
    block_bots: false,
    block_recent_abuse_ip: true,
    allow_crawlers: true,
    lower_penalty_for_mobiles: false,
    pass_user_agent: true,
    url_strictness: 0,
    block_phishing_url: true,
    block_malware_url: true,
    block_suspicious_url: false,
    block_parked_url: false,
    block_spam_url: true,
};

const mergeIpqs = (source = {}) => ({ ...ipqsDefaults, ...source });

const page = usePage();
const testResults = page.props.flash?.testResults;

const form = useForm({
    enabled: props.settings?.enabled ?? true,
    provider: props.settings?.provider ?? props.driver ?? 'demo',
    email_validation: props.settings?.email_validation ?? true,
    hlr_validation: props.settings?.hlr_validation ?? true,
    ip_validation: props.settings?.ip_validation ?? true,
    url_validation: props.settings?.url_validation ?? false,
    quarantine_on_fail: props.settings?.quarantine_on_fail ?? true,
    ipqs: mergeIpqs(props.settings?.ipqs),
});

watch(() => props.settings, (settings) => {
    if (!settings) {
        return;
    }

    form.enabled = settings.enabled ?? true;
    form.provider = settings.provider ?? props.driver ?? 'demo';
    form.email_validation = settings.email_validation ?? true;
    form.hlr_validation = settings.hlr_validation ?? true;
    form.ip_validation = settings.ip_validation ?? true;
    form.url_validation = settings.url_validation ?? false;
    form.quarantine_on_fail = settings.quarantine_on_fail ?? true;
    form.ipqs = mergeIpqs(settings.ipqs);
}, { deep: true });

const settingsSummary = computed(() => {
    const parts = [];
    if (!form.enabled) {
        return 'Validation off';
    }
    if (form.email_validation) parts.push('Email');
    if (form.hlr_validation) parts.push('Phone');
    if (form.ip_validation) parts.push('IP');
    if (form.url_validation) parts.push('URL');
    parts.push(form.quarantine_on_fail ? 'Quarantine' : 'Reject');

    return parts.join(' · ');
});

const effectiveProvider = computed(() => form.provider || 'demo');
const ipqsReady = computed(() => effectiveProvider.value === 'ipqs' && (props.hasIpqsKey || (form.ipqs.api_key && form.ipqs.api_key !== '••••••••')));
const fraud = computed(() => props.fraudProtection ?? {});
const fraudBlocked = computed(() => !fraud.value.entitled || fraud.value.cap_reached);
const showPlanUpgradeNotice = computed(() => !fraud.value.plan_entitled && !fraud.value.admin_override);
const showAdminOverrideNotice = computed(() => fraud.value.admin_override && !fraud.value.plan_entitled);

const lookupsPerLead = computed(() => {
    let n = 0;
    if (form.email_validation) n++;
    if (form.hlr_validation) n++;
    if (form.ip_validation) n++;
    if (form.url_validation) n++;

    return n;
});

const testForm = useForm({
    email: '',
    phone: '',
    ip: '',
    url: '',
    user_agent: typeof navigator !== 'undefined' ? navigator.userAgent : '',
});

const submit = () => {
    form.put(route('integrations.validation.update'), {
        preserveScroll: true,
        onSuccess: () => pushToast('Validation & fraud settings saved.', 'success'),
        onError: () => pushToast('Could not save settings — check the form.', 'error'),
    });
};

const runTest = () => {
    testForm.post(route('integrations.validation.test'), {
        preserveScroll: true,
        onError: () => pushToast('Validation test failed.', 'error'),
    });
};
</script>

<template>
    <Head title="Fraud Detection" />
    <AuthenticatedLayout>
        <PageHeader
            title="Fraud Detection"
            description="Email, phone HLR, IP/proxy/VPN, and URL scanning on lead ingest."
        >
            <template #actions>
                <Link :href="route('integrations.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    ← Integrations
                </Link>
            </template>
        </PageHeader>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="feature in planFeatures"
                :key="feature.id"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900/50"
                :class="feature.coming_soon ? 'opacity-60' : ''"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ feature.name }}</p>
                    <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ feature.min_plan }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ feature.description }}</p>
                <p v-if="feature.lookups_per_lead" class="mt-1 text-[10px] text-indigo-600 dark:text-indigo-300">
                    {{ feature.lookups_per_lead }} lookup / lead when enabled
                </p>
                <p v-if="feature.coming_soon" class="mt-1 text-[10px] font-medium text-amber-600">Not wired for lead ingest</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Validation settings">
                <template #header>
                    <span class="text-xs text-slate-500">{{ settingsSummary }} · {{ lookupsPerLead }} lookup(s)/lead</span>
                </template>

                <form class="space-y-6" @submit.prevent="submit">
                    <div
                        v-if="showPlanUpgradeNotice"
                        class="rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-950 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-100"
                    >
                        <p class="font-semibold">Fraud Detection add-on not active</p>
                        <p class="mt-1 text-violet-800 dark:text-violet-200">
                            Your {{ fraud.plan_label }} plan does not include live fraud checks.
                            Add Fraud Detection (+£{{ fraud.addon_price }}/mo) or upgrade to Growth — contact your platform operator.
                        </p>
                        <p class="mt-2 text-xs text-violet-700 dark:text-violet-300">Demo validation still works for testing. Live fraud checks run only when entitled.</p>
                    </div>
                    <div
                        v-else-if="showAdminOverrideNotice"
                        class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-950 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-100"
                    >
                        <p class="font-semibold">Super admin — all Fraud Detection features unlocked</p>
                        <p class="mt-1 text-indigo-800 dark:text-indigo-200">
                            This tenant is on {{ fraud.plan_label }} without a live fraud add-on. You can configure and test every check; tenant users still need an active plan or add-on.
                        </p>
                    </div>
                    <div
                        v-else-if="fraud.cap_reached"
                        class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-950 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100"
                    >
                        <p class="font-semibold">Monthly fraud cap reached</p>
                        <p class="mt-1">Used {{ fraud.usage_count?.toLocaleString() }} / {{ fraud.monthly_cap?.toLocaleString() }} validated leads this month. New leads skip live fraud checks until next period or plan upgrade.</p>
                    </div>
                    <div
                        v-else
                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"
                    >
                        <p class="font-semibold">Fraud Detection active — {{ fraud.plan_label }}</p>
                        <p class="mt-1">
                            {{ fraud.usage_count?.toLocaleString() }} / {{ fraud.monthly_cap ? fraud.monthly_cap.toLocaleString() : '∞' }} validated leads this month
                            <span v-if="fraud.included"> (included)</span>
                        </p>
                    </div>

                    <div
                        v-if="form.recentlySuccessful"
                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                    >
                        Settings saved for this platform.
                    </div>

                    <div class="space-y-3">
                        <label class="flex items-center gap-3">
                            <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300" />
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Enable validation on ingest</span>
                        </label>
                        <div>
                            <InputLabel value="Provider" />
                            <select v-model="form.provider" class="form-input mt-1 w-full">
                            <option value="demo">Demo (simulated)</option>
                            <option value="ipqs" :disabled="!fraud.entitled">Live provider</option></select>
                        </div>
                        <label class="flex items-center gap-3">
                            <input v-model="form.quarantine_on_fail" type="checkbox" class="rounded border-slate-300" />
                            <span class="text-sm text-slate-600 dark:text-slate-400">Quarantine on failure (vs reject)</span>
                        </label>
                    </div>

                    <div class="space-y-2 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Plan checks (1 lookup each)</p>
                        <label class="flex items-center gap-3">
                            <input v-model="form.email_validation" type="checkbox" class="rounded border-slate-300" />
                            <span class="text-sm text-slate-700 dark:text-slate-300">Email validation</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input v-model="form.hlr_validation" type="checkbox" class="rounded border-slate-300" />
                            <span class="text-sm text-slate-700 dark:text-slate-300">Phone validation + HLR</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input v-model="form.ip_validation" type="checkbox" class="rounded border-slate-300" />
                            <span class="text-sm text-slate-700 dark:text-slate-300">IP / proxy / VPN / Tor / bot</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input v-model="form.url_validation" type="checkbox" class="rounded border-slate-300" :disabled="!fraud.supports_url_scanner" />
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                Malicious URL scanner
                                <span v-if="!fraud.supports_url_scanner" class="text-xs text-slate-500"> (Growth plan+)</span>
                            </span>
                        </label>
                        <p class="text-xs text-slate-500">URL scanned from lead fields: url, website, landing_url</p>
                    </div>

                    <div
                        v-if="effectiveProvider === 'ipqs'"
                        class="space-y-5 rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-500/30 dark:bg-indigo-500/10"
                    >
                        <div>
                            <InputLabel value="API key" />
                            <input v-model="form.ipqs.api_key" type="password" class="form-input mt-1 w-full font-mono text-sm" placeholder="Fraud detection API key" autocomplete="off" />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Fraud score threshold (email / phone / IP)" />
                                <input v-model.number="form.ipqs.fraud_score_threshold" type="number" min="0" max="100" class="form-input mt-1 w-full" />
                            </div>
                            <div>
                                <InputLabel value="URL risk threshold" />
                                <input v-model.number="form.ipqs.url_risk_threshold" type="number" min="0" max="100" class="form-input mt-1 w-full" />
                            </div>
                        </div>

                        <details class="group rounded-lg border border-indigo-200/80 bg-white/70 dark:border-indigo-500/20 dark:bg-slate-900/40">
                            <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-800 dark:text-slate-100">Email options</summary>
                            <div class="space-y-3 border-t border-indigo-100 px-3 py-3 dark:border-indigo-500/20">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <InputLabel value="SMTP timeout (seconds)" />
                                        <input v-model.number="form.ipqs.email_timeout" type="number" min="1" max="60" class="form-input mt-1 w-full" />
                                    </div>
                                    <div>
                                        <InputLabel value="Abuse strictness (0–2)" />
                                        <input v-model.number="form.ipqs.email_abuse_strictness" type="number" min="0" max="2" class="form-input mt-1 w-full" />
                                    </div>
                                </div>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.email_fast" type="checkbox" class="rounded border-slate-300" /> Fast mode (less accurate)</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_disposable_email" type="checkbox" class="rounded border-slate-300" /> Block disposable email</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_spam_trap_email" type="checkbox" class="rounded border-slate-300" /> Block spam trap / honeypot</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_catch_all_email" type="checkbox" class="rounded border-slate-300" /> Block catch-all domains</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_recent_abuse_email" type="checkbox" class="rounded border-slate-300" /> Block recent abuse</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_leaked_email" type="checkbox" class="rounded border-slate-300" /> Block leaked emails</label>
                            </div>
                        </details>

                        <details class="group rounded-lg border border-indigo-200/80 bg-white/70 dark:border-indigo-500/20 dark:bg-slate-900/40">
                            <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-800 dark:text-slate-100">Phone options</summary>
                            <div class="space-y-3 border-t border-indigo-100 px-3 py-3 dark:border-indigo-500/20">
                                <div>
                                    <InputLabel value="Preferred countries (comma-separated ISO codes)" />
                                    <input v-model="form.ipqs.phone_countries" type="text" class="form-input mt-1 w-full font-mono text-sm" placeholder="GB,US,IE" />
                                </div>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_voip" type="checkbox" class="rounded border-slate-300" /> Block VOIP lines</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_prepaid" type="checkbox" class="rounded border-slate-300" /> Block prepaid lines</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_risky_phone" type="checkbox" class="rounded border-slate-300" /> Block risky numbers</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_recent_abuse_phone" type="checkbox" class="rounded border-slate-300" /> Block recent abuse</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_spammer_phone" type="checkbox" class="rounded border-slate-300" /> Block known spammers</label>
                            </div>
                        </details>

                        <details class="group rounded-lg border border-indigo-200/80 bg-white/70 dark:border-indigo-500/20 dark:bg-slate-900/40">
                            <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-800 dark:text-slate-100">IP / proxy / VPN options</summary>
                            <div class="space-y-3 border-t border-indigo-100 px-3 py-3 dark:border-indigo-500/20">
                                <div>
                                    <InputLabel value="Strictness (0–3)" />
                                    <input v-model.number="form.ipqs.strictness" type="number" min="0" max="3" class="form-input mt-1 w-full" />
                                </div>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.pass_user_agent" type="checkbox" class="rounded border-slate-300" /> Send lead user-agent (recommended)</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.allow_public_access_points" type="checkbox" class="rounded border-slate-300" /> Allow public access points (schools, corp Wi‑Fi)</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.lighter_penalties" type="checkbox" class="rounded border-slate-300" /> Lighter penalties (fewer false positives)</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.ip_fast" type="checkbox" class="rounded border-slate-300" /> Fast IP mode</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.allow_crawlers" type="checkbox" class="rounded border-slate-300" /> Allow search engine crawlers</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.lower_penalty_for_mobiles" type="checkbox" class="rounded border-slate-300" /> Lower penalty for mobile IPs</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_vpn" type="checkbox" class="rounded border-slate-300" /> Block VPN</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_proxy" type="checkbox" class="rounded border-slate-300" /> Block proxy</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_tor" type="checkbox" class="rounded border-slate-300" /> Block Tor</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_bots" type="checkbox" class="rounded border-slate-300" /> Block bots</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_recent_abuse_ip" type="checkbox" class="rounded border-slate-300" /> Block recent IP abuse</label>
                                <p class="text-xs text-slate-500">Residential proxy detection is automatic on SMB+ plans.</p>
                            </div>
                        </details>

                        <details class="group rounded-lg border border-indigo-200/80 bg-white/70 dark:border-indigo-500/20 dark:bg-slate-900/40">
                            <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-800 dark:text-slate-100">URL scanner options</summary>
                            <div class="space-y-3 border-t border-indigo-100 px-3 py-3 dark:border-indigo-500/20">
                                <div>
                                    <InputLabel value="URL strictness (0–2)" />
                                    <input v-model.number="form.ipqs.url_strictness" type="number" min="0" max="2" class="form-input mt-1 w-full" />
                                </div>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_phishing_url" type="checkbox" class="rounded border-slate-300" /> Block phishing</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_malware_url" type="checkbox" class="rounded border-slate-300" /> Block malware</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_suspicious_url" type="checkbox" class="rounded border-slate-300" /> Block suspicious</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_parked_url" type="checkbox" class="rounded border-slate-300" /> Block parked domains</label>
                                <label class="flex items-center gap-2 text-sm"><input v-model="form.ipqs.block_spam_url" type="checkbox" class="rounded border-slate-300" /> Block spam domains</label>
                            </div>
                        </details>

                        <p v-if="!ipqsReady" class="text-xs text-amber-700 dark:text-amber-300">
                            Save an API key to enable live fraud checks. Missing key falls back to demo provider.
                        </p>
                    </div>

                    <div
                        v-else
                        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
                    >
                        Demo provider — no live API calls. Switch to the live provider for production.
                    </div>

                    <AppButton type="submit" :loading="form.processing" :disabled="form.processing">
                        Save settings
                    </AppButton>
                </form>
            </Panel>

            <Panel title="Test validation">
                <form class="space-y-4" @submit.prevent="runTest">
                    <div>
                        <InputLabel for="email" value="Test email" />
                        <input id="email" v-model="testForm.email" type="email" class="form-input mt-1 w-full" placeholder="user@example.com" />
                    </div>
                    <div>
                        <InputLabel for="phone" value="Test phone" />
                        <input id="phone" v-model="testForm.phone" type="text" class="form-input mt-1 w-full" placeholder="07700900123" />
                    </div>
                    <div>
                        <InputLabel for="ip" value="Test IP" />
                        <input id="ip" v-model="testForm.ip" type="text" class="form-input mt-1 w-full" placeholder="8.8.8.8" />
                    </div>
                    <div>
                        <InputLabel for="url" value="Test URL" />
                        <input id="url" v-model="testForm.url" type="text" class="form-input mt-1 w-full" placeholder="https://example.com" />
                    </div>
                    <div>
                        <InputLabel for="user_agent" value="User agent (for IP scoring)" />
                        <input id="user_agent" v-model="testForm.user_agent" type="text" class="form-input mt-1 w-full font-mono text-xs" />
                    </div>
                    <AppButton type="submit" variant="secondary" :loading="testForm.processing" :disabled="testForm.processing">
                        Run test
                    </AppButton>
                </form>

                <div v-if="testResults" class="mt-6 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <div v-for="(result, key) in testResults" :key="key">
                        <p class="text-xs font-semibold uppercase text-slate-500">{{ key }}</p>
                        <p :class="result.passed ? 'text-emerald-600' : 'text-rose-600'">
                            {{ result.passed ? 'Passed' : result.reason }}
                        </p>
                        <pre class="mt-1 max-h-40 overflow-auto text-xs text-slate-500">{{ JSON.stringify(result.meta, null, 2) }}</pre>
                    </div>
                </div>

                <ul class="mt-6 list-disc space-y-1 pl-5 text-xs text-slate-500">
                    <li v-for="(hint, i) in demoHints" :key="i">{{ hint }}</li>
                </ul>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
