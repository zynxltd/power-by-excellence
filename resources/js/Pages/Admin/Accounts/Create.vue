<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import FormErrorSummary from '@/Components/UI/FormErrorSummary.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    baseDomain: String,
    timezones: Array,
    currencies: Array,
    countries: Object,
    reservedSlugs: Array,
});

const slugTouched = ref(false);

const form = useForm({
    name: '',
    slug: '',
    domain: '',
    timezone: 'Europe/London',
    default_country: 'GB',
    default_currency: 'GBP',
    admin_name: '',
    admin_email: '',
    admin_password: '',
    send_credentials: true,
});

const slugify = (value) => String(value ?? '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 63);

watch(() => form.name, (name) => {
    if (!slugTouched.value) {
        form.slug = slugify(name);
    }
});

const onSlugInput = () => {
    slugTouched.value = true;
    form.slug = slugify(form.slug);
};

const previewDomain = () => {
    if (form.domain?.trim()) {
        return form.domain.trim().toLowerCase();
    }
    if (form.slug) {
        return `${form.slug}.${props.baseDomain}`;
    }
    return `your-slug.${props.baseDomain}`;
};

const submit = () => {
    form.default_country = String(form.default_country).toUpperCase();
    form.default_currency = String(form.default_currency).toUpperCase();
    form.post(route('accounts.store'));
};
</script>

<template>
    <Head title="New Partner Platform" />
    <AuthenticatedLayout>
        <PageHeader
            title="New Partner Platform"
            description="Provision a tenant subdomain and its first account admin. Buyers, suppliers, and campaigns can be added after visiting the portal."
        >
            <template #actions>
                <AppButton :href="route('accounts.index')" variant="secondary">Back to list</AppButton>
            </template>
        </PageHeader>

        <Panel class="max-w-2xl">
            <FormErrorSummary :errors="form.errors" />
            <form @submit.prevent="submit" class="space-y-8">
                <section class="space-y-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Platform</h2>
                    <div>
                        <InputLabel value="Platform name" />
                        <TextInput v-model="form.name" class="mt-1" required placeholder="Excellence UK" />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Subdomain slug" />
                        <div class="mt-1 flex items-center gap-2">
                            <TextInput
                                v-model="form.slug"
                                class="font-mono"
                                required
                                placeholder="excellence-uk"
                                @input="onSlugInput"
                            />
                            <span class="shrink-0 text-sm text-slate-500">.{{ baseDomain }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Resolves to <span class="font-mono">{{ previewDomain() }}</span>
                        </p>
                        <InputError class="mt-1" :message="form.errors.slug" />
                    </div>
                    <div>
                        <InputLabel value="Custom domain (optional)" />
                        <TextInput v-model="form.domain" class="mt-1 font-mono" placeholder="leads.partner.com" />
                        <p class="mt-1 text-xs text-slate-500">Leave blank to use the subdomain above.</p>
                        <InputError class="mt-1" :message="form.errors.domain" />
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Default country" />
                            <select v-model="form.default_country" class="form-select">
                                <option v-for="(label, code) in countries" :key="code" :value="code">{{ code }} — {{ label }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.default_country" />
                        </div>
                        <div>
                            <InputLabel value="Default currency" />
                            <select v-model="form.default_currency" class="form-select">
                                <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.default_currency" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Timezone" />
                        <select v-model="form.timezone" class="form-select">
                            <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.timezone" />
                    </div>
                </section>

                <section class="space-y-4 border-t border-slate-200 pt-6 dark:border-slate-700">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Account admin</h2>
                    <p class="text-sm text-slate-500">
                        This user can sign in on the tenant subdomain and configure buyers, suppliers, and campaigns.
                    </p>
                    <div>
                        <InputLabel value="Admin name" />
                        <TextInput v-model="form.admin_name" class="mt-1" required />
                        <InputError class="mt-1" :message="form.errors.admin_name" />
                    </div>
                    <div>
                        <InputLabel value="Admin email" />
                        <TextInput v-model="form.admin_email" type="email" class="mt-1" required />
                        <InputError class="mt-1" :message="form.errors.admin_email" />
                    </div>
                    <div>
                        <InputLabel value="Initial password" />
                        <TextInput v-model="form.admin_password" type="password" class="mt-1" required autocomplete="new-password" />
                        <InputError class="mt-1" :message="form.errors.admin_password" />
                    </div>
                    <label class="flex items-start gap-3">
                        <input v-model="form.send_credentials" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600" />
                        <span class="text-sm text-slate-600 dark:text-slate-300">Email login credentials to the admin</span>
                    </label>
                </section>

                <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-6 dark:border-slate-700">
                    <PrimaryButton :disabled="form.processing">Create platform</PrimaryButton>
                    <AppButton :href="route('accounts.index')" variant="secondary">Cancel</AppButton>
                </div>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
