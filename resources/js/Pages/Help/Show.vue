<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import HelpMarkdown from '@/Components/Help/HelpMarkdown.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    article: { type: Object, required: true },
    related: { type: Array, default: () => [] },
});

const page = usePage();
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
            <div class="grid gap-8 lg:grid-cols-3">
                <article class="lg:col-span-2">
                    <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                        {{ article.category }}
                    </p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                        {{ article.title }}
                    </h1>
                    <p v-if="article.summary" class="mt-3 text-lg text-slate-600 dark:text-slate-400">
                        {{ article.summary }}
                    </p>

                    <div class="prose-custom mt-8 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                        <HelpMarkdown :body="article.body" />
                    </div>

                    <Link
                        :href="route('help.index')"
                        class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 transition hover:text-indigo-500 dark:text-indigo-400"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Help Centre
                    </Link>
                </article>

                <aside class="space-y-6">
                    <div
                        v-if="related.length"
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2 class="font-semibold text-slate-900 dark:text-white">Related articles</h2>
                        <ul class="mt-4 space-y-2">
                            <li v-for="item in related" :key="item.slug">
                                <Link
                                    :href="route('help.show', item.slug)"
                                    class="block rounded-lg px-3 py-2 text-sm text-slate-600 transition hover:bg-slate-50 hover:text-indigo-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-indigo-400"
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
