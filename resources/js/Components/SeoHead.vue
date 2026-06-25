<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    title: { type: String, default: 'PowerByExcellence — Lead Distribution Platform' },
    description: { type: String, default: 'Real-time lead distribution, ping-tree routing, multi-vertical capture, and buyer management for agencies and lead sellers.' },
    image: { type: String, default: null },
    canonical: { type: String, default: null },
    type: { type: String, default: 'website' },
});
</script>

<template>
    <Head>
        <title>{{ title }}</title>
        <meta name="description" :content="description" />
        <meta name="robots" content="index, follow" />
        <meta property="og:title" :content="title" />
        <meta property="og:description" :content="description" />
        <meta property="og:type" :content="type" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="title" />
        <meta name="twitter:description" :content="description" />
        <link v-if="canonical" rel="canonical" :href="canonical" />
    </Head>
</template>
