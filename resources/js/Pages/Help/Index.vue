<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    search: { type: String, default: '' },
});

const page = usePage();
const query = ref(props.search ?? '');

const filteredCategories = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.categories;

    return props.categories
        .map((category) => ({
            ...category,
            articles: category.articles.filter(
                (article) =>
                    article.title.toLowerCase().includes(q) ||
                    (article.summary ?? '').toLowerCase().includes(q),
            ),
        }))
        .filter((category) => category.articles.length > 0);
});

const totalArticles = computed(() =>
    filteredCategories.value.reduce((sum, c) => sum + c.articles.length, 0),
);
</script>

<template>
    <Head title="Help Centre" />

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
                        href="/"
                        class="hidden text-sm font-medium text-slate-600 transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-white sm:inline"
                    >
                        Home
                    </Link>
                    <Link
                        v-if="page.props.auth?.user"
                        :href="route('dashboard')"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="route('login')"
                        class="rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-500/20 transition hover:opacity-95"
                    >
                        Sign In
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
            <div class="mx-auto max-w-2xl text-center">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                    Help Centre
                </h1>
                <p class="mt-3 text-slate-600 dark:text-slate-400">
                    Guides and documentation for PowerByExcellence.
                </p>
                <div class="relative mt-8">
                    <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Search articles..."
                        class="form-input w-full pl-12"
                    />
                </div>
            </div>

            <p v-if="query && totalArticles === 0" class="mt-12 text-center text-sm text-slate-500">
                No articles match "{{ query }}".
            </p>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="category in filteredCategories"
                    :key="category.name"
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <h2 class="font-semibold text-slate-900 dark:text-white">{{ category.name }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ category.articles.length }} article{{ category.articles.length === 1 ? '' : 's' }}</p>
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="article in category.articles" :key="article.slug">
                            <Link
                                :href="route('help.show', article.slug)"
                                class="block px-5 py-3.5 transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <p class="font-medium text-slate-900 dark:text-white">{{ article.title }}</p>
                                <p v-if="article.summary" class="mt-0.5 line-clamp-2 text-sm text-slate-500">{{ article.summary }}</p>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 rounded-2xl border border-indigo-200 bg-indigo-50 p-6 text-center dark:border-indigo-900 dark:bg-indigo-950/40">
                <p class="font-semibold text-slate-900 dark:text-white">Need more help?</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    Signed-in users can open a support ticket from the platform.
                </p>
                <Link
                    v-if="page.props.auth?.user"
                    :href="route('support.create')"
                    class="mt-4 inline-flex rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500"
                >
                    Contact Support
                </Link>
                <Link
                    v-else
                    :href="route('login')"
                    class="mt-4 inline-flex rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500"
                >
                    Sign in to contact support
                </Link>
            </div>
        </main>

        <footer class="border-t border-slate-200 py-8 dark:border-slate-800">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 sm:flex-row sm:px-6">
                <p class="text-sm text-slate-500">&copy; {{ new Date().getFullYear() }} PowerByExcellence</p>
                <div class="flex gap-6 text-sm text-slate-500">
                    <Link href="/" class="transition hover:text-slate-700 dark:hover:text-slate-300">Home</Link>
                    <Link :href="route('help.index')" class="font-medium text-indigo-600 dark:text-indigo-400">Help Centre</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
