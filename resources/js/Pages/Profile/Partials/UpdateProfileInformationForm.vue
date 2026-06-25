<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const user = usePage().props.auth.user;
const preview = ref(user.avatar_url);

const form = useForm({
    name: user.name,
    email: user.email,
    avatar: null,
    remove_avatar: false,
});

const initials = computed(() => (user.name ?? '?').split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase());

const onFile = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    form.avatar = file;
    form.remove_avatar = false;
    preview.value = URL.createObjectURL(file);
};

const removeAvatar = () => {
    form.avatar = null;
    form.remove_avatar = true;
    preview.value = null;
};

const submit = () => {
    form.patch(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-slate-900 dark:text-white">Profile Information</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Update your photo, name, and email address.</p>
        </header>

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <div class="flex items-center gap-5">
                <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-full border-2 border-slate-200 bg-gradient-to-br from-violet-500 to-indigo-600 dark:border-slate-700">
                    <img v-if="preview" :src="preview" alt="Avatar" class="h-full w-full object-cover" />
                    <span v-else class="flex h-full w-full items-center justify-center text-xl font-bold text-white">{{ initials }}</span>
                </div>
                <div class="space-y-2">
                    <label class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        Upload photo
                        <input type="file" accept="image/*" class="hidden" @change="onFile" />
                    </label>
                    <button v-if="preview || user.avatar_url" type="button" class="block text-sm text-rose-600 hover:underline" @click="removeAvatar">Remove photo</button>
                    <p class="text-xs text-slate-500">JPG, PNG or WebP. Max 2MB.</p>
                </div>
            </div>

            <div>
                <InputLabel for="name" value="Name" />
                <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
                <InputError class="mt-2" :message="form.errors.avatar" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Your email address is unverified.
                    <Link :href="route('verification.send')" method="post" as="button" class="text-indigo-600 underline">Re-send verification</Link>
                </p>
                <div v-show="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-emerald-600">Verification link sent.</div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                <p v-if="form.recentlySuccessful" class="text-sm text-slate-600">Saved.</p>
            </div>
        </form>
    </section>
</template>
