<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Panel from '@/Components/UI/Panel.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ account: Object });

const logoPreview = ref(props.account.logo_url);
const faviconPreview = ref(props.account.favicon_url);

const form = useForm({
    name: props.account.name ?? '',
    brand_name: props.account.brand_name ?? '',
    logo: null,
    favicon: null,
    remove_logo: false,
    remove_favicon: false,
});

const onLogoFile = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    form.logo = file;
    form.remove_logo = false;
    logoPreview.value = URL.createObjectURL(file);
};

const onFaviconFile = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    form.favicon = file;
    form.remove_favicon = false;
    faviconPreview.value = URL.createObjectURL(file);
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
};

const removeFavicon = () => {
    form.favicon = null;
    form.remove_favicon = true;
    faviconPreview.value = null;
};

const submit = () => {
    form.post(route('branding.update'), {
        forceFormData: true,
        onSuccess: () => form.reset('logo', 'favicon'),
    });
};
</script>

<template>
    <Head title="Branding" />
    <AuthenticatedLayout>
        <PageHeader
            title="Platform Branding"
            description="Customise your platform name, logo, and favicon shown to admins, buyers, and suppliers."
        />

        <Panel class="max-w-2xl">
            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <InputLabel value="Internal platform name" />
                    <TextInput v-model="form.name" class="mt-1 block w-full" required placeholder="Excellence Leads UK" />
                    <p class="mt-1 text-xs text-slate-500">Used in admin areas and system references.</p>
                </div>

                <div>
                    <InputLabel value="Public display name" />
                    <TextInput v-model="form.brand_name" class="mt-1 block w-full" placeholder="e.g. Excellence Leads UK" />
                    <p class="mt-1 text-xs text-slate-500">Shown in the nav bar and login page. Leave blank to use the internal name.</p>
                </div>

                <div>
                    <InputLabel value="Logo" />
                    <div v-if="logoPreview" class="mt-3 flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                        <img :src="logoPreview" alt="Logo preview" class="h-12 max-w-[200px] object-contain" />
                        <button type="button" class="text-sm text-rose-600 hover:text-rose-500" @click="removeLogo">Remove logo</button>
                    </div>
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/svg+xml,image/webp"
                        class="mt-3 block w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 dark:file:bg-indigo-500/15 dark:file:text-indigo-400"
                        @change="onLogoFile"
                    />
                    <p class="mt-1 text-xs text-slate-500">PNG, JPG, SVG or WebP. Max 2MB. Recommended: transparent PNG, 200×48px.</p>
                </div>

                <div>
                    <InputLabel value="Favicon" />
                    <div v-if="faviconPreview" class="mt-3 flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                        <img :src="faviconPreview" alt="Favicon preview" class="h-10 w-10 rounded object-contain" />
                        <button type="button" class="text-sm text-rose-600 hover:text-rose-500" @click="removeFavicon">Remove favicon</button>
                    </div>
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/svg+xml,image/webp,image/x-icon,.ico"
                        class="mt-3 block w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 dark:file:bg-indigo-500/15 dark:file:text-indigo-400"
                        @change="onFaviconFile"
                    />
                    <p class="mt-1 text-xs text-slate-500">PNG, ICO, SVG or WebP. Max 512KB. Shown in browser tabs for your subdomain.</p>
                </div>

                <PrimaryButton :disabled="form.processing">Save branding</PrimaryButton>
            </form>
        </Panel>
    </AuthenticatedLayout>
</template>
