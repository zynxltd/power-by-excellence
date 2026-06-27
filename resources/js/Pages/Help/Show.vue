<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import HelpMarkdown from '@/Components/Help/HelpMarkdown.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    article: { type: Object, required: true },
    related: { type: Array, default: () => [] },
    navigation: { type: Object, default: () => ({}) },
    categoryArticles: { type: Array, default: () => [] },
});

const page = usePage();

const audienceLabel = {
    tenant: 'Platform admin',
    buyer: 'Buyer portal',
    supplier: 'Supplier portal',
    all: 'Everyone',
};

const toc = computed(() => props.article.tableOfContents ?? []);
</script>

<template>
    <Head :title="article.title" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/80 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/80">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <Link href="/">
                    <BrandLogo size="sm" variant="dark" class="dark:hidden" />
                    <BrandLogo size="sm" variant="light" class="hidden dark:block" />
                </Link>
                <div class="flex items-center gap-3">
                    <ThemeToggle />
                    <Link
                        :href="route('help.index')"
                        class="text-sm font-medium text-indigo-600 transition hover:text-indigo-500 dark:text-indigo-400"
                    >
                        &larr; Help Centre
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
            <nav class="text-sm text-slate-500">
                <Link :href="route('help.index')" class="hover:text-indigo-600 dark:hover:text-indigo-400">Help</Link>
                <span class="mx-2">/</span>
                <span>{{ article.category }}</span>
            </nav>

            <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_280px]">
                <article class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                            {{ audienceLabel[article.audience] ?? article.audience }}
                        </span>
                        <span class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ article.category }}</span>
                    </div>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                        {{ article.title }}
                    </h1>
                    <p v-if="article.summary" class="mt-3 text-lg text-slate-600 dark:text-slate-400">
                        {{ article.summary }}
                    </p>

                    <div class="prose-custom mt-8 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                        <HelpMarkdown :body="article.body" />
                    </div>

                    <div class="mt-8 flex flex-col gap-3 border-t border-slate-200 pt-6 dark:border-slate-800 sm:flex-row sm:justify-between">
                        <Link
                            v-if="navigation.prev"
                            :href="route('help.show', navigation.prev.slug)"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 dark:text-indigo-400"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            {{ navigation.prev.title }}
                        </Link>
                        <span v-else />
                        <Link
                            v-if="navigation.next"
                            :href="route('help.show', navigation.next.slug)"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 dark:text-indigo-400 sm:text-right"
                        >
                            {{ navigation.next.title }}
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>
                </article>

                <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                    <div
                        v-if="toc.length"
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2 class="font-semibold text-slate-900 dark:text-white">On this page</h2>
                        <ul class="mt-3 space-y-1 text-sm">
                            <li v-for="item in toc" :key="item.id">
                                <a
                                    :href="`#${item.id}`"
                                    class="block rounded-lg py-1.5 text-slate-600 transition hover:bg-slate-50 hover:text-indigo-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-indigo-400"
                                    :class="item.level === 3 ? 'pl-4' : ''"
                                >
                                    {{ item.text }}
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="categoryArticles.length"
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2 class="font-semibold text-slate-900 dark:text-white">In this section</h2>
                        <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto text-sm">
                            <li v-for="item in categoryArticles" :key="item.slug">
                                <Link
                                    :href="route('help.show', item.slug)"
                                    class="block rounded-lg px-3 py-2 transition"
                                    :class="item.current
                                        ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600 dark:text-slate-400 dark:hover:bg-slate-800'"
                                >
                                    {{ item.title }}
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="related.length"
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2 class="font-semibold text-slate-900 dark:text-white">Related</h2>
                        <ul class="mt-4 space-y-2">
                            <li v-for="item in related" :key="item.slug">
                                <Link
                                    :href="route('help.show', item.slug)"
                                    class="block rounded-lg px-3 py-2 text-sm text-slate-600 transition hover:bg-slate-50 hover:text-indigo-600 dark:text-slate-400 dark:hover:bg-slate-800"
                                >
                                    {{ item.title }}
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="font-semibold text-slate-900 dark:text-white">Still stuck?</h2>
                        <p class="mt-2 text-sm text-slate-500">Our support team can help with setup and troubleshooting.</p>
                        <Link
                            v-if="page.props.auth?.user"
                            :href="route('support.create')"
                            class="mt-4 inline-flex w-full justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500"
                        >
                            Open a ticket
                        </Link>
                        <Link
                            v-else
                            :href="route('login')"
                            class="mt-4 inline-flex w-full justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Sign in for support
                        </Link>
                    </div>
                </aside>
            </div>
        </main>
    </div>
</template>
