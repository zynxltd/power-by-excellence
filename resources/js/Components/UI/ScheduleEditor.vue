<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';

const model = defineModel({ type: Object, required: true });

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

const ensureWindows = () => {
    if (!model.value.windows?.length) {
        model.value.windows = [{ day: 'all', start: '09:00', end: '17:00' }];
    }
};

const addWindow = () => {
    ensureWindows();
    model.value.windows.push({ day: 'monday', start: '09:00', end: '17:00' });
};

const removeWindow = (index) => {
    if (model.value.windows.length > 1) {
        model.value.windows.splice(index, 1);
    }
};

ensureWindows();
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Restrict when this delivery can fire. Outside these windows, attempts are skipped with reason "Outside schedule".
        </p>

        <div>
            <InputLabel value="Timezone" />
            <TextInput v-model="model.timezone" class="mt-1 block w-full" placeholder="Europe/London" />
        </div>

        <div class="space-y-3">
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
