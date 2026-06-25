<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';

const props = defineProps({
    preferences: Object,
    accentOptions: Array,
});

const { setTheme, setAccent } = useTheme();

const form = useForm({
    theme: props.preferences?.theme ?? 'light',
    accent_color: props.preferences?.accent_color ?? 'indigo',
});

const accentSwatches = {
    violet: 'bg-violet-600',
    indigo: 'bg-indigo-600',
    emerald: 'bg-emerald-600',
    rose: 'bg-rose-600',
    amber: 'bg-amber-500',
    cyan: 'bg-cyan-500',
};

const selectTheme = (value) => {
    form.theme = value;
    setTheme(value);
};

const selectAccent = (value) => {
    form.accent_color = value;
    setAccent(value);
};

const submit = () => {
    form.patch(route('profile.preferences'), { preserveScroll: true });
};
</script>

<template>
    <div>
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Appearance</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose your theme and accent colour. Saved to your account.</p>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Theme</p>
                <div class="mt-3 flex gap-3">
                    <button
                        type="button"
                        :class="[
                            'flex flex-1 items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium transition',
                            form.theme === 'light'
                                ? 'border-[var(--accent-ring)] bg-[var(--accent-from)]/10 text-slate-900 dark:text-white'
                                : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400',
                        ]"
                        @click="selectTheme('light')"
                    >
                        Light
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex flex-1 items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium transition',
                            form.theme === 'dark'
                                ? 'border-[var(--accent-ring)] bg-[var(--accent-from)]/10 text-slate-900 dark:text-white'
                                : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400',
                        ]"
                        @click="selectTheme('dark')"
                    >
                        Dark
                    </button>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Accent colour</p>
                <div class="mt-3 flex flex-wrap gap-3">
                    <button
                        v-for="opt in accentOptions"
                        :key="opt.value"
                        type="button"
                        :title="opt.label"
                        :class="[
                            'h-10 w-10 rounded-full ring-2 ring-offset-2 ring-offset-white transition dark:ring-offset-slate-900',
                            accentSwatches[opt.value],
                            form.accent_color === opt.value ? 'ring-[var(--accent-ring)]' : 'ring-transparent hover:ring-slate-300',
                        ]"
                        @click="selectAccent(opt.value)"
                    />
                </div>
                <InputError class="mt-1" :message="form.errors.accent_color" />
            </div>

            <PrimaryButton :disabled="form.processing">Save Appearance</PrimaryButton>
        </form>
    </div>
</template>
