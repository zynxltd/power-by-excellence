<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    audienceFilters: { type: Array, default: () => [] },
    defaultAudience: { type: String, default: 'all' },
    learningPaths: { type: Array, default: () => [] },
    featured: { type: Array, default: () => [] },
    search: { type: String, default: '' },
});

const page = usePage();
const query = ref(props.search ?? '');
const activeAudience = ref(props.defaultAudience);

const audienceLabel = {
    tenant: 'Platform',
    buyer: 'Buyer',
    supplier: 'Supplier',
    all: 'All',
};

const matchesAudience = (articleAudience) => {
    if (activeAudience.value === 'all') {
        return true;
    }

    return articleAudience === activeAudience.value || articleAudience === 'all';
};

const filteredCategories = computed(() => {
    const q = query.value.trim().toLowerCase();

    return props.categories
        .map((category) => ({
            ...category,
            articles: category.articles.filter((article) => {
                if (!matchesAudience(article.audience ?? category.audience)) {
                    return false;
                }

                if (!q) {
                    return true;
                }

                return (
                    article.title.toLowerCase().includes(q) ||
                    (article.summary ?? '').toLowerCase().includes(q)
                );
            }),
        }))
        .filter((category) => category.articles.length > 0);
});

const visiblePaths = computed(() =>
    props.learningPaths.filter((path) => activeAudience.value === 'all' || path.audience === activeAudience.value),
);

const visibleFeatured = computed(() =>
    props.featured.filter((article) => matchesAudience(article.audience)),
);

const totalArticles = computed(() =>
    filteredCategories.value.reduce((sum, c) => sum + c.articles.length, 0),
);

const articleTitleBySlug = computed(() => {
    const map = {};
    for (const category of props.categories) {
        for (const article of category.articles) {
            map[article.slug] = article.title;
        }
    }
    return map;
});

const pathArticleTitle = (slug) => articleTitleBySlug.value[slug] ?? slug.replace(/-/g, ' ');
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
            <div class="mx-auto max-w-3xl text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Documentation</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                    Help Centre
                </h1>
                <p class="mt-3 text-slate-600 dark:text-slate-400">
                    Step-by-step guides for platform admins, buyer portals, and supplier partners.
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

            <div v-if="audienceFilters.length > 1" class="mt-8 flex flex-wrap justify-center gap-2">
                <button
                    v-for="filter in audienceFilters"
                    :key="filter.id"
                    type="button"
                    class="rounded-full px-4 py-2 text-sm font-medium transition"
                    :class="
                        activeAudience === filter.id
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/25'
                            : 'border border-slate-200 bg-white text-slate-600 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
                    "
                    @click="activeAudience = filter.id"
                >
                    {{ filter.label }}
                    <span class="ml-1 opacity-70">({{ filter.count }})</span>
                </button>
            </div>

            <section v-if="visiblePaths.length && !query" class="mt-12">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Learning paths</h2>
                <p class="mt-1 text-sm text-slate-500">Follow a curated sequence - ideal for onboarding.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div
                        v-for="path in visiblePaths"
                        :key="path.id"
                        class="rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50/80 to-white p-5 dark:border-indigo-500/30 dark:from-indigo-950/40 dark:to-slate-900"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                            {{ audienceLabel[path.audience] ?? path.audience }}
                        </p>
                        <h3 class="mt-1 font-semibold text-slate-900 dark:text-white">{{ path.title }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ path.description }}</p>
                        <ol class="mt-4 space-y-2">
                            <li v-for="(slug, index) in path.slugs" :key="slug" class="flex items-start gap-2 text-sm">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">{{ index + 1 }}</span>
                                <Link :href="route('help.show', slug)" class="font-medium text-indigo-700 hover:underline dark:text-indigo-300">
                                    {{ pathArticleTitle(slug) }}
                                </Link>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

            <section v-if="visibleFeatured.length && !query" class="mt-12">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Popular articles</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="article in visibleFeatured"
                        :key="article.slug"
                        :href="route('help.show', article.slug)"
                        class="rounded-xl border border-slate-200 bg-white p-4 transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-500/40"
                    >
                        <p class="text-xs font-semibold uppercase text-slate-500">{{ audienceLabel[article.audience] ?? article.audience }}</p>
                        <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ article.title }}</p>
                        <p v-if="article.summary" class="mt-1 line-clamp-2 text-sm text-slate-500">{{ article.summary }}</p>
                    </Link>
                </div>
            </section>

            <p v-if="query && totalArticles === 0" class="mt-12 text-center text-sm text-slate-500">
                No articles match "{{ query }}".
            </p>

            <section class="mt-12">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">All articles</h2>
                    <p class="text-sm text-slate-500">{{ totalArticles }} article{{ totalArticles === 1 ? '' : 's' }}</p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="category in filteredCategories"
                        :key="category.name"
                        class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                            <h3 class="font-semibold text-slate-900 dark:text-white">{{ category.name }}</h3>
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
            </section>

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
