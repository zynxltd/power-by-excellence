<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AppButton from '@/Components/UI/AppButton.vue';
import { ref } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    hasExistingSecret: { type: Boolean, default: false },
});

const generating = ref(false);
const revealedSecret = ref('');

const xsrfToken = () => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const generateSecret = async () => {
    generating.value = true;
    try {
        const res = await fetch(route('webhooks.generate-signing-secret'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = await res.json();
            props.form.secret = data.secret;
            revealedSecret.value = data.secret;
        }
    } finally {
        generating.value = false;
    }
};
</script>

<template>
    <div class="md:col-span-2 space-y-4 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/40">
        <div>
            <InputLabel value="Payload signing (HMAC-SHA256)" />
            <label class="mt-2 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input v-model="form.sign_payloads" type="checkbox" class="rounded" />
                Sign outbound JSON with <code class="rounded bg-slate-100 px-1 text-xs dark:bg-slate-800">X-Signature</code>
            </label>
            <p class="mt-1 text-xs text-slate-500">
                Receivers verify the raw request body against the header using your shared secret.
            </p>
        </div>

        <div v-if="form.sign_payloads" class="space-y-3">
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-0 flex-1">
                    <InputLabel value="Signing secret" />
                    <TextInput
                        v-model="form.secret"
                        type="password"
                        class="mt-1 block w-full font-mono text-sm"
                        :placeholder="hasExistingSecret && !form.secret ? '•••••••• (unchanged)' : 'Generate or paste a secret'"
                        autocomplete="off"
                    />
                </div>
                <AppButton type="button" variant="secondary" :disabled="generating" @click="generateSecret">
                    {{ generating ? 'Generating…' : 'Generate secret' }}
                </AppButton>
            </div>
            <p v-if="revealedSecret" class="text-xs text-amber-700 dark:text-amber-300">
                Copy this secret now — it will not be shown again after save.
            </p>
            <p v-else-if="hasExistingSecret && !form.secret" class="text-xs text-slate-500">
                A signing secret is already stored. Generate a new one to rotate it.
            </p>

            <details class="text-sm text-slate-600 dark:text-slate-400">
                <summary class="cursor-pointer font-medium text-slate-800 dark:text-slate-200">Verification guide for your endpoint</summary>
                <div class="mt-2 space-y-2 rounded-lg border border-slate-200 bg-white p-3 font-mono text-xs dark:border-slate-700 dark:bg-slate-950">
                    <p>Header: <span class="text-indigo-600 dark:text-indigo-400">X-Signature: sha256=&lt;hmac&gt;</span></p>
                    <p>HMAC-SHA256 of the <strong>raw JSON body</strong> using your signing secret (hex digest, prefixed with <code>sha256=</code>).</p>
                    <pre class="overflow-x-auto whitespace-pre-wrap text-[11px] text-slate-500">$signature = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
if (!hash_equals($signature, $_SERVER['HTTP_X_SIGNATURE'] ?? '')) {
    http_response_code(401);
    exit('Invalid signature');
}</pre>
                </div>
            </details>
        </div>
    </div>
</template>
