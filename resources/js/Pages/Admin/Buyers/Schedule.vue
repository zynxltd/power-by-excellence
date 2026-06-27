<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
    buyers: Array,
});

const capOverrides = reactive({});

watch(
    () => props.buyers,
    (buyers) => {
        for (const buyer of buyers ?? []) {
            const overrides = buyer.caps?.today_override ?? {};
            capOverrides[buyer.id] = {
                daily_cap: overrides.daily ?? buyer.caps?.daily ?? '',
                monthly_cap: overrides.monthly ?? buyer.caps?.monthly ?? '',
            };
        }
    },
    { immediate: true },
);

const pause = (buyer) => router.post(route('buyers.pause', buyer.id));
const resume = (buyer) => router.post(route('buyers.resume', buyer.id));

const saveCaps = (buyer) => {
    router.post(route('buyers.override-caps', buyer.id), capOverrides[buyer.id], { preserveScroll: true });
};
</script>

<template>
    <Head title="Buyer schedule" />
    <AuthenticatedLayout>
        <PageHeader title="Buyer operations" description="Pause buyers, override today's caps, and monitor delivery volume.">
            <template #actions>
                <AppButton variant="secondary" :href="route('buyers.index')">All buyers</AppButton>
            </template>
        </PageHeader>

        <div class="space-y-4">
            <Panel v-for="buyer in buyers" :key="buyer.id">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-900 dark:text-white">{{ buyer.name }}</h3>
                            <span class="font-mono text-xs text-slate-500">{{ buyer.reference }}</span>
                            <StatusBadge :status="buyer.status" />
                        </div>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                            {{ buyer.deliveries_count }} {{ buyer.deliveries_count === 1 ? 'delivery' : 'deliveries' }}
                        </p>
                        <Link :href="route('buyers.show', buyer.id)" class="mt-2 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                            View buyer →
                        </Link>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <AppButton v-if="buyer.status === 'active'" variant="secondary" @click="pause(buyer)">Pause</AppButton>
                        <AppButton v-else @click="resume(buyer)">Resume</AppButton>
                    </div>
                </div>

                <form class="mt-4 grid gap-4 border-t border-slate-100 pt-4 dark:border-slate-800 md:grid-cols-[1fr_1fr_auto]" @submit.prevent="saveCaps(buyer)">
                    <div>
                        <InputLabel value="Today's daily cap override" />
                        <TextInput v-model="capOverrides[buyer.id].daily_cap" type="number" min="0" class="mt-1 w-full" />
                    </div>
                    <div>
                        <InputLabel value="Today's monthly cap override" />
                        <TextInput v-model="capOverrides[buyer.id].monthly_cap" type="number" min="0" class="mt-1 w-full" />
                    </div>
                    <div class="flex items-end">
                        <PrimaryButton>Save override</PrimaryButton>
                    </div>
                    <p class="md:col-span-3 text-xs text-slate-500">Overrides apply for today only and reset on the next calendar day.</p>
                </form>
            </Panel>

            <Panel v-if="!buyers?.length">
                <p class="text-sm text-slate-500">No buyers configured yet. <Link :href="route('buyers.create')" class="font-medium text-indigo-600 hover:underline">Create a buyer</Link>.</p>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
