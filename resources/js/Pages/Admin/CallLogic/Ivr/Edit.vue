<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    flow: Object,
    campaigns: Array,
    stepTypes: { type: Array, default: () => ['say', 'gather', 'redirect', 'hangup', 'route'] },
});

const defaultSteps = () => ({
    start: { type: 'say', message: 'Welcome. Please hold while we connect you.', next: 'route' },
    route: { type: 'route' },
});

const form = useForm({
    name: props.flow?.name ?? '',
    campaign_id: props.flow?.campaign_id ?? '',
    entry_node: props.flow?.entry_node ?? 'start',
    nodes: props.flow?.nodes ?? defaultSteps(),
    is_active: props.flow?.is_active ?? true,
});

const stepIds = computed(() => Object.keys(form.nodes));

const addStep = () => {
    const id = `step_${Date.now()}`;
    form.nodes = {
        ...form.nodes,
        [id]: { type: 'say', message: 'New message', next: '' },
    };
};

const removeStep = (id) => {
    if (id === form.entry_node || id === 'route') {
        return;
    }

    const { [id]: _removed, ...rest } = form.nodes;
    form.nodes = rest;

    Object.keys(form.nodes).forEach((stepId) => {
        const node = form.nodes[stepId];
        if (node.next === id) {
            node.next = '';
        }
        if (node.default_next === id) {
            node.default_next = '';
        }
        if (node.branches) {
            Object.keys(node.branches).forEach((digit) => {
                if (node.branches[digit] === id) {
                    node.branches[digit] = '';
                }
            });
        }
    });

    if (!form.nodes[form.entry_node]) {
        form.entry_node = Object.keys(form.nodes)[0] ?? 'start';
    }
};

const ensureBranches = (step) => {
    if (!step.branches) {
        step.branches = { '1': '' };
    }
    return step.branches;
};

const addBranch = (step) => {
    const branches = ensureBranches(step);
    const nextDigit = String(Object.keys(branches).length + 1);
    branches[nextDigit] = '';
};

const removeBranch = (step, digit) => {
    if (!step.branches) {
        return;
    }
    const { [digit]: _removed, ...rest } = step.branches;
    step.branches = rest;
};

const stepLabel = (type) => {
    const labels = {
        say: 'Say',
        gather: 'Gather (DTMF)',
        redirect: 'Redirect',
        hangup: 'Hang up',
        route: 'Route call',
        play: 'Say',
    };
    return labels[type] ?? type;
};

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
        <PageHeader
            :title="flow ? 'Edit IVR flow' : 'New IVR flow'"
            description="Build caller journeys with say, gather, redirect, and hangup steps."
        />
        <Panel>
            <form class="space-y-6 max-w-3xl" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
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
                </div>

                <div>
                    <label class="block text-sm font-medium">Entry step</label>
                    <select v-model="form.entry_node" class="mt-1 w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800">
                        <option v-for="id in stepIds" :key="id" :value="id">{{ id }}</option>
                    </select>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Steps</h3>
                        <AppButton type="button" variant="secondary" size="sm" @click="addStep">Add step</AppButton>
                    </div>

                    <div
                        v-for="(step, stepId) in form.nodes"
                        :key="stepId"
                        class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                    >
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-medium text-indigo-600">{{ stepId }}</span>
                                <span
                                    v-if="stepId === form.entry_node"
                                    class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200"
                                >Entry</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <select v-model="step.type" class="rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                                    <option v-for="t in stepTypes" :key="t" :value="t">{{ stepLabel(t) }}</option>
                                </select>
                                <button
                                    v-if="stepId !== 'route'"
                                    type="button"
                                    class="text-xs text-red-600 hover:underline"
                                    @click="removeStep(stepId)"
                                >Remove</button>
                            </div>
                        </div>

                        <div v-if="step.type === 'say' || step.type === 'play'" class="space-y-3">
                            <div>
                                <label class="block text-xs text-slate-500">Message</label>
                                <textarea v-model="step.message" rows="2" class="mt-1 w-full rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500">Next step</label>
                                <select v-model="step.next" class="mt-1 w-full rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                                    <option value="">—</option>
                                    <option v-for="id in stepIds" :key="id" :value="id">{{ id }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-else-if="step.type === 'gather'" class="space-y-3">
                            <div>
                                <label class="block text-xs text-slate-500">Prompt</label>
                                <textarea v-model="step.prompt" rows="2" class="mt-1 w-full rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500">Store input as</label>
                                <input v-model="step.store_as" placeholder="choice" class="mt-1 w-full rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800" />
                            </div>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <label class="text-xs text-slate-500">DTMF branches</label>
                                    <button type="button" class="text-xs text-indigo-600 hover:underline" @click="addBranch(step)">Add branch</button>
                                </div>
                                <div v-for="(target, digit) in ensureBranches(step)" :key="digit" class="mb-2 flex items-center gap-2">
                                    <input :value="digit" class="w-12 rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800" disabled />
                                    <span class="text-slate-400">→</span>
                                    <select v-model="step.branches[digit]" class="flex-1 rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                                        <option value="">—</option>
                                        <option v-for="id in stepIds" :key="id" :value="id">{{ id }}</option>
                                    </select>
                                    <button type="button" class="text-xs text-red-600" @click="removeBranch(step, digit)">×</button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500">Default next (no match)</label>
                                <select v-model="step.default_next" class="mt-1 w-full rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                                    <option value="">—</option>
                                    <option v-for="id in stepIds" :key="id" :value="id">{{ id }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-else-if="step.type === 'redirect'" class="space-y-3">
                            <p class="text-xs text-slate-500">Immediately continues to the next step in the flow.</p>
                            <div>
                                <label class="block text-xs text-slate-500">Next step</label>
                                <select v-model="step.next" required class="mt-1 w-full rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                                    <option value="">—</option>
                                    <option v-for="id in stepIds" :key="id" :value="id">{{ id }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-else-if="step.type === 'hangup'" class="space-y-3">
                            <div>
                                <label class="block text-xs text-slate-500">Goodbye message (optional)</label>
                                <textarea v-model="step.message" rows="2" class="mt-1 w-full rounded border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800" />
                            </div>
                        </div>

                        <div v-else-if="step.type === 'route'" class="text-xs text-slate-500">
                            Connects the caller to the campaign routing engine (waterfall or auction).
                        </div>
                    </div>
                </div>

                <p v-if="form.errors.nodes" class="text-sm text-red-600">{{ form.errors.nodes }}</p>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="rounded" /> Active
                </label>

                <AppButton type="submit" :disabled="form.processing">Save flow</AppButton>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
