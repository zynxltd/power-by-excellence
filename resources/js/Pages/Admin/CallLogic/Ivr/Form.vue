<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ flow: Object, campaigns: Array });

const defaultNodes = {
    start: { type: 'play', message: 'Welcome. Please hold while we connect you.', next: 'route' },
};

const form = useForm({
    name: props.flow?.name ?? '',
    campaign_id: props.flow?.campaign_id ?? '',
    entry_node: props.flow?.entry_node ?? 'start',
    nodes: props.flow?.nodes ?? defaultNodes,
    is_active: props.flow?.is_active ?? true,
});

const nodesJson = computed({
    get: () => JSON.stringify(form.nodes, null, 2),
    set: (v) => {
        try { form.nodes = JSON.parse(v); } catch { /* keep */ }
    },
});

const submit = () => {
    if (props.flow) {
        form.put(route('call-logic.ivr.update', props.flow.id));
    } else {
        form.post(route('call-logic.ivr.store'));
    }
};
</script>

<template>
    <Head :title="flow ? 'Edit IVR' : 'New IVR'" />
    <AuthenticatedLayout>
        <PageHeader :title="flow ? 'Edit IVR flow' : 'New IVR flow'" />
        <Panel>
            <form class="space-y-4 max-w-2xl" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium">Name</label>
                    <input v-model="form.name" required class="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium">Campaign</label>
                    <select v-model="form.campaign_id" class="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800">
                        <option value="">None</option>
                        <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Nodes (JSON)</label>
                    <textarea v-model="nodesJson" rows="12" class="mt-1 w-full font-mono text-xs rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="rounded" /> Active
                </label>
                <AppButton type="submit" :disabled="form.processing">Save</AppButton>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
