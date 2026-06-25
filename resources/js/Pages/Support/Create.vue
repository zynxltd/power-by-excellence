<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    subject: '',
    body: '',
    priority: 'normal',
});

const submit = () => {
    form.post(route('support.store'));
};
</script>

<template>
    <Head title="New Support Ticket" />
    <AuthenticatedLayout>
        <PageHeader
            title="New Support Ticket"
            description="Describe your issue and our team will respond as soon as possible."
        >
            <template #actions>
                <AppButton variant="secondary" :href="route('support.index')">Cancel</AppButton>
            </template>
        </PageHeader>

        <Panel title="Ticket Details">
            <form class="mx-auto max-w-2xl space-y-5" @submit.prevent="submit">
                <div>
                    <InputLabel for="subject" value="Subject" />
                    <TextInput
                        id="subject"
                        v-model="form.subject"
                        class="mt-1 block w-full"
                        required
                        placeholder="Brief summary of your issue"
                    />
                    <InputError class="mt-1" :message="form.errors.subject" />
                </div>

                <div>
                    <InputLabel for="priority" value="Priority" />
                    <select id="priority" v-model="form.priority" class="form-select mt-1 w-full">
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.priority" />
                </div>

                <div>
                    <InputLabel for="body" value="Message" />
                    <textarea
                        id="body"
                        v-model="form.body"
                        rows="8"
                        required
                        class="form-input mt-1 w-full"
                        placeholder="Include as much detail as possible — campaign names, error messages, steps to reproduce..."
                    />
                    <InputError class="mt-1" :message="form.errors.body" />
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <AppButton type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Submitting...' : 'Submit Ticket' }}
                    </AppButton>
                    <AppButton variant="secondary" :href="route('support.index')">Back to tickets</AppButton>
                </div>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
