<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    defaults: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    address_line1: props.defaults.address_line1,
    address_line2: props.defaults.address_line2,
    city: props.defaults.city,
    region: props.defaults.region,
    postcode: props.defaults.postcode,
    country: props.defaults.country,
    confirm_address: false,
});

const submit = () => {
    form.post(route('verification.address.store'));
};

const inputClass =
    'block w-full rounded-xl border border-slate-200 bg-slate-50 py-3 px-4 text-base text-slate-900 placeholder-slate-400 transition focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20';
</script>

<template>
    <GuestLayout>
        <Head title="Verify Address - PowerByExcellence" />

        <template #header>
            <h2>Confirm your address</h2>
            <p>Enter your business or billing address. You must confirm it is accurate before accessing the platform.</p>
        </template>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="address_line1" class="mb-1.5 block text-sm font-semibold text-slate-700">Address line 1</label>
                <input id="address_line1" v-model="form.address_line1" type="text" required autocomplete="address-line1" :class="inputClass" />
                <InputError class="mt-2" :message="form.errors.address_line1" />
            </div>

            <div>
                <label for="address_line2" class="mb-1.5 block text-sm font-semibold text-slate-700">Address line 2</label>
                <input id="address_line2" v-model="form.address_line2" type="text" autocomplete="address-line2" :class="inputClass" />
                <InputError class="mt-2" :message="form.errors.address_line2" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="city" class="mb-1.5 block text-sm font-semibold text-slate-700">City</label>
                    <input id="city" v-model="form.city" type="text" required autocomplete="address-level2" :class="inputClass" />
                    <InputError class="mt-2" :message="form.errors.city" />
                </div>
                <div>
                    <label for="region" class="mb-1.5 block text-sm font-semibold text-slate-700">County / state</label>
                    <input id="region" v-model="form.region" type="text" autocomplete="address-level1" :class="inputClass" />
                    <InputError class="mt-2" :message="form.errors.region" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="postcode" class="mb-1.5 block text-sm font-semibold text-slate-700">Postcode</label>
                    <input id="postcode" v-model="form.postcode" type="text" required autocomplete="postal-code" :class="inputClass" />
                    <InputError class="mt-2" :message="form.errors.postcode" />
                </div>
                <div>
                    <label for="country" class="mb-1.5 block text-sm font-semibold text-slate-700">Country</label>
                    <select id="country" v-model="form.country" required :class="inputClass">
                        <option value="GB">United Kingdom</option>
                        <option value="US">United States</option>
                        <option value="IE">Ireland</option>
                        <option value="CA">Canada</option>
                        <option value="AU">Australia</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.country" />
                </div>
            </div>

            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <input v-model="form.confirm_address" type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                <span class="text-sm text-slate-700">I confirm this address is correct and I am authorised to use it for this account.</span>
            </label>
            <InputError class="-mt-3" :message="form.errors.confirm_address" />

            <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-center text-sm font-medium text-slate-600 underline hover:text-slate-900 sm:text-left"
                >
                    Log out
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-indigo-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition disabled:opacity-60 sm:w-auto"
                >
                    {{ form.processing ? 'Saving…' : 'Confirm address' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
