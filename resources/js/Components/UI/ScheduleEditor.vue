<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';

const model = defineModel({ type: Object, required: true });

if (!model.value) {
    model.value = { timezone: 'Europe/London', windows: [] };
}

if (!model.value.timezone) {
    model.value.timezone = 'Europe/London';
}

if (!Array.isArray(model.value.windows)) {
    model.value.windows = [];
}

const days = [
    { value: 'all', label: 'Every day' },
    { value: 'monday', label: 'Monday' },
    { value: 'tuesday', label: 'Tuesday' },
    { value: 'wednesday', label: 'Wednesday' },
    { value: 'thursday', label: 'Thursday' },
    { value: 'friday', label: 'Friday' },
    { value: 'saturday', label: 'Saturday' },
    { value: 'sunday', label: 'Sunday' },
];

const addWindow = () => {
    if (!model.value.timezone) {
        model.value.timezone = 'Europe/London';
    }
    model.value.windows.push({ day: 'all', start: '09:00', end: '17:00' });
};

const removeWindow = (index) => {
    model.value.windows.splice(index, 1);
};
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Optional. With no windows, this delivery runs <strong>24/7</strong>. Add windows to restrict hours — outside them, attempts are skipped with reason "Outside schedule".
        </p>

        <div v-if="model.windows.length">
            <InputLabel value="Timezone" />
            <TextInput v-model="model.timezone" class="mt-1 block w-full" placeholder="Europe/London" />
        </div>

        <p
            v-if="!model.windows.length"
            class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
            No schedule windows — delivery is always eligible.
        </p>

        <div v-else class="space-y-3">
            <div
                v-for="(window, index) in model.windows"
                :key="index"
                class="grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-4"
            >
                <div>
                    <InputLabel value="Day" />
                    <select v-model="window.day" class="form-select mt-1 w-full">
                        <option v-for="d in days" :key="d.value" :value="d.value">{{ d.label }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Start" />
                    <TextInput v-model="window.start" type="time" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="End" />
                    <TextInput v-model="window.end" type="time" class="mt-1" />
                </div>
                <div class="flex items-end">
                    <AppButton type="button" variant="secondary" @click="removeWindow(index)">Remove</AppButton>
                </div>
            </div>
        </div>

        <AppButton type="button" variant="secondary" @click="addWindow">Add window</AppButton>
    </div>
</template>
