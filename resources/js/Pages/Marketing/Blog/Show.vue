<script setup>
import SeoHead from '@/Components/SeoHead.vue';
import MarketingNav from '@/Components/Marketing/MarketingNav.vue';
import MarketingFooter from '@/Components/Marketing/MarketingFooter.vue';
import { useMarketingTheme } from '@/Composables/useMarketingTheme';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ article: Object, seo: Object });

const { marketingTheme } = useMarketingTheme();

const html = computed(() => {
    const body = props.article?.body ?? '';
    return body
        .split('\n\n')
        .map((block) => {
            if (block.startsWith('## ')) return `<h2 class="article-h2">${block.slice(3)}</h2>`;
            if (block.startsWith('### ')) return `<h3 class="article-h3">${block.slice(4)}</h3>`;
            if (block.startsWith('- ')) {
                const items = block.split('\n').map((l) => `<li>${l.slice(2)}</li>`).join('');
                return `<ul class="article-ul">${items}</ul>`;
            }
            return `<p class="article-p">${block.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/`([^`]+)`/g, '<code class="article-code">$1</code>')}</p>`;
        })
        .join('');
});
</script>

<template>
    <SeoHead :title="seo?.title" :description="seo?.description" />
    <div class="min-h-screen">
        <MarketingNav active="blog" />

        <div :class="['marketing-content', marketingTheme === 'dark' && 'marketing-dark']">
            <article class="mx-auto max-w-3xl px-6 pb-16 pt-28">
                <Link :href="route('blog.index')" class="text-sm text-indigo-600 hover:underline marketing-dark:text-indigo-400">← Blog</Link>
                <p class="mt-6 text-sm font-semibold uppercase tracking-wider text-indigo-600 marketing-dark:text-indigo-400">{{ article.category }}</p>
                <h1 class="mt-2 text-4xl font-bold text-slate-900 marketing-dark:text-white">{{ article.title }}</h1>
                <p class="mt-2 flex flex-wrap gap-3 text-sm text-slate-500">
                    <span>{{ article.published_at }}</span>
                    <span v-if="article.reading_time">{{ article.reading_time }} min read</span>
                    <span v-if="article.word_count">{{ article.word_count }} words</span>
                </p>
                <div class="article-body mt-10 max-w-none" v-html="html" />
            </article>
        </div>

        <MarketingFooter />
    </div>
</template>
