<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import CompactStatStrip from '@/Components/UI/CompactStatStrip.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    summary: Object,
    hourlyOpens: Array,
    campaignStats: Array,
    segments: Array,
    templates: Array,
    sendingProfiles: Array,
    recentCampaigns: Array,
    providers: Object,
});

const statStrip = computed(() => [
    { label: 'Sent (30d)', value: props.summary?.total_sent ?? 0 },
    { label: 'Open rate', value: `${props.summary?.open_rate ?? 0}%`, accent: 'emerald' },
    { label: 'Click rate', value: `${props.summary?.click_rate ?? 0}%`, accent: 'indigo' },
    { label: 'Bounce rate', value: `${props.summary?.bounce_rate ?? 0}%`, accent: 'rose' },
]);

const segmentForm = useForm({ name: '', rules: { has_email: true } });
const templateForm = useForm({ name: '', channel: 'email', subject: '', body: '', html_body: '' });
const profileForm = useForm({ name: '', provider: 'smtp', domain_match: '', from_name: '', from_email: '', is_default: false });

const submitSegment = () => segmentForm.post(route('e-delivery.segments.store'), { preserveScroll: true, onSuccess: () => segmentForm.reset() });
const submitTemplate = () => templateForm.post(route('e-delivery.templates.store'), { preserveScroll: true, onSuccess: () => templateForm.reset() });
const submitProfile = () => profileForm.post(route('e-delivery.sending-profiles.store'), { preserveScroll: true, onSuccess: () => profileForm.reset() });

const maxHourlyOpens = computed(() => Math.max(1, ...(props.hourlyOpens ?? []).map((h) => h.opens)));
</script>

<template>
    <Head title="E-Delivery" />
    <AuthenticatedLayout>
        <PageHeader
            title="E-Delivery"
            description="Email & SMS marketing — deliverability, segments, templates, and multi-channel campaigns."
        >
            <template #actions>
                <AppButton :href="route('integrations.messaging')" variant="secondary">ESP settings</AppButton>
                <AppButton :href="route('automation.index', { tab: 'bulk-sms' })">Bulk campaigns</AppButton>
                <AppButton :href="route('features.auto-responders')" variant="secondary">Auto-responders</AppButton>
            </template>
        </PageHeader>

        <CompactStatStrip :items="statStrip" :columns="4" class="mb-6" />

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel title="Hourly opens (30d)">
                <div class="flex h-40 items-end gap-1">
                    <div
                        v-for="h in 24"
                        :key="h - 1"
                        class="flex-1 rounded-t bg-indigo-500/80 dark:bg-indigo-400/70"
                        :style="{ height: `${Math.max(4, ((hourlyOpens?.find((x) => x.hour === h - 1)?.opens ?? 0) / maxHourlyOpens) * 100)}%` }"
                        :title="`${h - 1}:00 — ${hourlyOpens?.find((x) => x.hour === h - 1)?.opens ?? 0} opens`"
                    />
                </div>
                <p class="mt-2 text-xs text-slate-500">Peak send windows based on when recipients open your emails.</p>
            </Panel>

            <Panel title="Deliverability by provider">
                <ul class="space-y-2 text-sm">
                    <li v-for="(count, provider) in summary?.by_provider ?? {}" :key="provider" class="flex justify-between">
                        <span class="font-medium capitalize">{{ provider }}</span>
                        <span class="text-slate-600 dark:text-slate-400">{{ count }} sent</span>
                    </li>
                    <li v-if="!Object.keys(summary?.by_provider ?? {}).length" class="text-slate-500">No sends recorded yet.</li>
                </ul>
            </Panel>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <Panel title="Segments">
                <ul class="mb-4 space-y-1 text-sm">
                    <li v-for="s in segments" :key="s.id" class="flex items-center justify-between">
                        <span>{{ s.name }}</span>
                        <button type="button" class="text-xs text-rose-600" @click="router.delete(route('e-delivery.segments.destroy', s.id))">Remove</button>
                    </li>
                </ul>
                <form class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-700" @submit.prevent="submitSegment">
                    <InputLabel value="New segment" />
                    <TextInput v-model="segmentForm.name" class="w-full" placeholder="Engaged — opened 7d" required />
                    <AppButton type="submit" size="sm" :disabled="segmentForm.processing">Add</AppButton>
                </form>
            </Panel>

            <Panel title="Templates">
                <ul class="mb-4 space-y-1 text-sm">
                    <li v-for="t in templates" :key="t.id" class="flex items-center justify-between">
                        <span>{{ t.name }} <span class="text-slate-400">({{ t.channel }})</span></span>
                        <button type="button" class="text-xs text-rose-600" @click="router.delete(route('e-delivery.templates.destroy', t.id))">Remove</button>
                    </li>
                </ul>
                <form class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-700" @submit.prevent="submitTemplate">
                    <TextInput v-model="templateForm.name" class="w-full" placeholder="Template name" required />
                    <TextInput v-model="templateForm.subject" class="w-full" placeholder="Subject" />
                    <textarea v-model="templateForm.body" rows="2" class="form-input w-full" placeholder="Plain text body" />
                    <AppButton type="submit" size="sm" :disabled="templateForm.processing">Save template</AppButton>
                </form>
            </Panel>

            <Panel title="Sending profiles">
                <ul class="mb-4 space-y-1 text-sm">
                    <li v-for="p in sendingProfiles" :key="p.id" class="flex items-center justify-between">
                        <span>{{ p.name }} <span v-if="p.is_default" class="text-emerald-600">default</span></span>
                        <button type="button" class="text-xs text-rose-600" @click="router.delete(route('e-delivery.sending-profiles.destroy', p.id))">Remove</button>
                    </li>
                </ul>
                <form class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-700" @submit.prevent="submitProfile">
                    <TextInput v-model="profileForm.name" class="w-full" placeholder="Profile name" required />
                    <TextInput v-model="profileForm.domain_match" class="w-full" placeholder="Domain match e.g. gmail.com" />
                    <TextInput v-model="profileForm.from_email" class="w-full" placeholder="From email" />
                    <AppButton type="submit" size="sm" :disabled="profileForm.processing">Add profile</AppButton>
                </form>
            </Panel>
        </div>

        <Panel title="Recent campaigns" class="mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="pb-2 pr-4">Name</th>
                            <th class="pb-2 pr-4">Channel</th>
                            <th class="pb-2 pr-4">Status</th>
                            <th class="pb-2 pr-4">Sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in recentCampaigns" :key="c.id" class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-2 pr-4 font-medium">{{ c.name }}</td>
                            <td class="py-2 pr-4 capitalize">{{ c.channel ?? 'sms' }}</td>
                            <td class="py-2 pr-4">{{ c.status }}</td>
                            <td class="py-2 pr-4">{{ c.sent_count ?? 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-sm text-slate-500">
                <Link :href="route('automation.index', { tab: 'bulk-sms' })" class="text-indigo-600 hover:underline">Manage bulk campaigns →</Link>
            </p>
        </Panel>
    </AuthenticatedLayout>
</template>
