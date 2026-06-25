<script setup>
import SeoHead from '@/Components/SeoHead.vue';
import MarketingNav from '@/Components/Marketing/MarketingNav.vue';
import MarketingFooter from '@/Components/Marketing/MarketingFooter.vue';
import { useMarketingTheme } from '@/Composables/useMarketingTheme';
import { Link } from '@inertiajs/vue3';

defineProps({ articles: Array, seo: Object });

const { marketingTheme } = useMarketingTheme();
</script>

<template>
    <SeoHead :title="seo?.title" :description="seo?.description" />
    <div class="min-h-screen">
        <MarketingNav active="blog" />

        <div :class="['marketing-content', marketingTheme === 'dark' && 'marketing-dark']">
            <main class="mx-auto max-w-5xl px-6 pb-16 pt-28">
                <h1 class="text-4xl font-bold text-slate-900 marketing-dark:text-white">Lead distribution <span class="brand-gradient-text">insights</span></h1>
                <p class="mt-3 text-slate-600 marketing-dark:text-slate-400">Guides on ping-tree routing, bidding, multi-vertical capture, and integrations.</p>
                <div class="mt-12 grid gap-6 md:grid-cols-2">
                    <Link
                        v-for="a in articles"
                        :key="a.slug"
                        :href="route('blog.show', a.slug)"
                        class="group brand-card-sm"
                    >
                        <p class="brand-kicker text-xs">{{ a.category }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900 group-hover:text-indigo-700 marketing-dark:text-white marketing-dark:group-hover:text-indigo-300">{{ a.title }}</h2>
                        <p class="mt-2 text-sm text-slate-600 marketing-dark:text-slate-400">{{ a.excerpt }}</p>
                        <p class="mt-4 flex items-center gap-3 text-xs text-slate-500">
                            <span>{{ a.published_at }}</span>
                            <span v-if="a.reading_time">· {{ a.reading_time }} min read</span>
                            <span v-if="a.word_count">· {{ a.word_count }} words</span>
                        </p>
                    </Link>
                </div>
            </main>
        </div>

        <MarketingFooter />
    </div>
</template>
