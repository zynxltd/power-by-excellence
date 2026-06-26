<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Panel from '@/Components/UI/Panel.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    supplier: Object,
    sources: Array,
    iframeEmbedAllowed: Boolean,
    forms: Array,
    trackingParams: Array,
});

const copyText = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        // ignore
    }
};
</script>

<template>
    <Head title="Form embeds" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Form embeds</h1>
            <p class="mt-1 text-sm text-slate-500">
                Embed hosted lead forms on your websites. Your supplier ID and SID are included automatically for attribution.
            </p>
        </div>

        <div
            v-if="!iframeEmbedAllowed"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
        >
            <p class="font-semibold">Iframe embed not enabled for your account</p>
            <p class="mt-1">Your platform operator has not enabled supplier iframe embeds. Contact them to request access, or use direct links below if available.</p>
        </div>

        <Panel v-if="sources?.length" title="Your tracking IDs" class="mb-6">
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="source in sources"
                    :key="source.id"
                    class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <span class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ source.sid }}</span>
                    <span v-if="source.name" class="ml-2 text-slate-500">{{ source.name }}</span>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-500">Append <code class="rounded bg-slate-100 px-1 font-mono dark:bg-slate-800">click_id</code>, UTM params, or <code class="rounded bg-slate-100 px-1 font-mono dark:bg-slate-800">ssid</code> to the embed URL as needed.</p>
        </Panel>

        <div v-if="!forms?.length" class="rounded-xl border border-dashed border-slate-300 px-6 py-10 text-center text-sm text-slate-500 dark:border-slate-600">
            No hosted forms are available for your campaigns yet.
        </div>

        <div v-else class="space-y-6">
            <Panel v-for="form in forms" :key="form.id" :title="form.name">
                <p v-if="form.campaign" class="mb-4 text-sm text-slate-500">
                    Campaign: <span class="font-medium text-slate-700 dark:text-slate-300">{{ form.campaign.name }}</span>
                    <span class="font-mono text-xs">({{ form.campaign.reference }})</span>
                </p>

                <div class="space-y-4">
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <label class="text-xs font-semibold uppercase text-slate-500">Direct link</label>
                            <button type="button" class="text-xs text-indigo-600" @click="copyText(form.embed.directUrl)">Copy</button>
                        </div>
                        <code class="block overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ form.embed.directUrl }}</code>
                    </div>

                    <template v-if="iframeEmbedAllowed">
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label class="text-xs font-semibold uppercase text-slate-500">Iframe URL</label>
                                <button type="button" class="text-xs text-indigo-600" @click="copyText(form.embed.iframeUrl)">Copy</button>
                            </div>
                            <code class="block overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ form.embed.iframeUrl }}</code>
                        </div>
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label class="text-xs font-semibold uppercase text-slate-500">Iframe HTML</label>
                                <button type="button" class="text-xs text-indigo-600" @click="copyText(form.embed.iframeHtml)">Copy</button>
                            </div>
                            <code class="block overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ form.embed.iframeHtml }}</code>
                            <p class="mt-2 text-xs text-slate-500">Paste into any page on your site. Embeds work on any domain when your account has iframe embed enabled.</p>
                        </div>
                    </template>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
