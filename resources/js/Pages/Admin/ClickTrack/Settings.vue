<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ entitlement: Object, capUsage: Object, settings: Object, plans: Object, pricingModuleFlags: Object });

const form = useForm({
    enabled: props.settings?.enabled ?? false,
    clicks_cap: props.settings?.clicks_cap ?? '',
    conversions_cap: props.settings?.conversions_cap ?? '',
    cap_hourly: props.settings?.cap_hourly ?? '',
    cap_soft_limit_pct: props.settings?.cap_soft_limit_pct ?? 80,
    fraud_block_duplicates: props.settings?.fraud_block_duplicates ?? true,
    fraud_duplicate_window_minutes: props.settings?.fraud_duplicate_window_minutes ?? 60,
});

const meterClass = (pct, soft) => {
    if (pct >= 100) return 'bg-red-500';
    if (soft) return 'bg-amber-500';
    return 'bg-indigo-500';
};

const submit = () => form.patch(route('click-track.settings.update'));
</script>

<template>
    <Head title="Click Track Settings" />
    <AuthenticatedLayout>
        <PageHeader title="Click Track settings" description="Plan caps, hourly limits, soft/hard enforcement, and fraud protection." />

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Usage meters">
                <dl class="space-y-3 text-sm">
                    <div>
                        <div class="mb-1 flex justify-between"><dt class="text-slate-500">Hourly clicks</dt><dd>{{ capUsage?.clicks_hour ?? 0 }} / {{ capUsage?.cap_hourly ?? '∞' }}</dd></div>
                        <div v-if="capUsage?.cap_hourly" class="h-2 overflow-hidden rounded bg-slate-200 dark:bg-slate-700">
                            <div class="h-full transition-all" :class="meterClass(capUsage.clicks_hour_pct, capUsage.clicks_hour_soft)" :style="{ width: `${capUsage.clicks_hour_pct ?? 0}%` }" />
                        </div>
                        <p v-if="capUsage?.clicks_hour_soft && !capUsage?.clicks_hour_hard" class="mt-1 text-xs text-amber-600">Soft cap warning ({{ capUsage.soft_limit_pct }}%)</p>
                    </div>
                    <div>
                        <div class="mb-1 flex justify-between"><dt class="text-slate-500">Period clicks</dt><dd>{{ capUsage?.clicks_used ?? 0 }} / {{ capUsage?.clicks_cap ?? '∞' }}</dd></div>
                        <div v-if="capUsage?.clicks_cap" class="h-2 overflow-hidden rounded bg-slate-200 dark:bg-slate-700">
                            <div class="h-full transition-all" :class="meterClass(capUsage.clicks_pct, capUsage.clicks_soft)" :style="{ width: `${capUsage.clicks_pct ?? 0}%` }" />
                        </div>
                    </div>
                    <div>
                        <div class="mb-1 flex justify-between"><dt class="text-slate-500">Period conversions</dt><dd>{{ capUsage?.conversions_used ?? 0 }} / {{ capUsage?.conversions_cap ?? '∞' }}</dd></div>
                        <div v-if="capUsage?.conversions_cap" class="h-2 overflow-hidden rounded bg-slate-200 dark:bg-slate-700">
                            <div class="h-full transition-all" :class="meterClass(capUsage.conversions_pct, capUsage.conversions_soft)" :style="{ width: `${capUsage.conversions_pct ?? 0}%` }" />
                        </div>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">Soft limit at {{ capUsage?.soft_limit_pct ?? 80 }}% triggers EventAlert metrics. Hard limit blocks new clicks.</p>
            </Panel>

            <Panel title="Plan & entitlement">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Plan</dt><dd class="font-semibold">{{ entitlement?.plan_label }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Entitled</dt><dd class="font-semibold">{{ entitlement?.entitled ? 'Yes' : 'No' }}</dd></div>
                </dl>
            </Panel>

            <Panel title="Cap & fraud configuration" class="lg:col-span-2">
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <label class="flex items-center gap-2 text-sm md:col-span-2"><input v-model="form.enabled" type="checkbox" /> Enable Click Track add-on</label>
                    <div><InputLabel value="Hourly click cap (account)" /><TextInput v-model="form.cap_hourly" type="number" min="0" class="mt-1 block w-full" placeholder="0 = unlimited" /></div>
                    <div><InputLabel value="Period clicks cap" /><TextInput v-model="form.clicks_cap" type="number" min="0" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Period conversions cap" /><TextInput v-model="form.conversions_cap" type="number" min="0" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Soft limit %" /><TextInput v-model="form.cap_soft_limit_pct" type="number" min="50" max="99" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Fraud duplicate window (minutes)" /><TextInput v-model="form.fraud_duplicate_window_minutes" type="number" min="1" class="mt-1 block w-full" /></div>
                    <label class="flex items-center gap-2 text-sm md:col-span-2"><input v-model="form.fraud_block_duplicates" type="checkbox" /> Block duplicate clicks (same IP + sub1 within window)</label>
                    <div class="md:col-span-2"><PrimaryButton :disabled="form.processing">Save settings</PrimaryButton></div>
                </form>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
