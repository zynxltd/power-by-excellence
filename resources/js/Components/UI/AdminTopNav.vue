<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import NotificationBell from '@/Components/UI/NotificationBell.vue';
import AdminHubMenu from '@/Components/UI/AdminHubMenu.vue';
import TopNavDropdown from '@/Components/UI/TopNavDropdown.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { provideNavDropdown } from '@/Composables/useNavDropdown';
import { pushToast } from '@/Composables/useToast';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

provideNavDropdown();

const page = usePage();
const user = computed(() => page.props.auth.user);
const account = computed(() => page.props.auth.account);
const branding = computed(() => {
    if (isSuperAdmin.value && isCentralHost.value) {
        return null;
    }

    return page.props.tenant ?? page.props.auth.account;
});
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);
const isCentralHost = computed(() => page.props.isCentralHost);
const isBuyer = computed(() => page.props.auth.isBuyerPortal);
const isSupplier = computed(() => page.props.auth.isSupplierPortal);
const allowedModules = computed(() => page.props.auth?.allowedModules ?? []);

const canAccess = (module) => isSuperAdmin.value || allowedModules.value.includes(module);

const hasTenantContext = computed(() => Boolean(account.value));

const needsTenantOnCentral = computed(() => isSuperAdmin.value && isCentralHost.value && !hasTenantContext.value);

/** Tenant switcher lives on Partner platforms / page banners - not in central admin header. */
const showHeaderTenantSwitcher = computed(() => isSuperAdmin.value && Boolean(account.value) && !isCentralHost.value);

const guardTenantRoute = (event) => {
    if (!needsTenantOnCentral.value) {
        return true;
    }

    event.preventDefault();
    pushToast('Select a partner platform first - click Switch on a platform row.', 'error');

    if (!route().current('accounts.index')) {
        router.visit(route('accounts.index'));
    }

    return false;
};

const userInitials = computed(() => {
    const name = user.value?.name ?? '?';
    return name.split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase();
});

const homeHref = computed(() => {
    if (isBuyer.value) return route('portal.buyer.dashboard');
    if (isSupplier.value) return route('portal.supplier.dashboard');
    return route('dashboard');
});

const isAdminRoute = (patterns) => patterns.some((p) => route().current(p));

const navLinkClass = (active) => [
    'shrink-0 whitespace-nowrap rounded-lg px-2.5 py-2 text-sm font-medium transition',
    active ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white',
];

const dropdownLinkClass = 'block px-4 py-2 text-sm text-slate-100 hover:bg-slate-800';

const mobileOpen = ref(false);
const mobileSection = ref(null);

const toggleMobileSection = (id) => {
    mobileSection.value = mobileSection.value === id ? null : id;
};

const closeMobile = () => {
    mobileOpen.value = false;
    mobileSection.value = null;
};

const clearTenantContext = () => router.post(route('accounts.clear'));

const adminHub = computed(() => {
    if (! isSuperAdmin.value || ! isCentralHost.value) {
        return null;
    }

    return page.props.tenantHub ?? page.props.platformHub;
});

const brandTitle = computed(() => branding.value?.display_name ?? 'PowerByExcellence');

const mobileSections = computed(() => {
    if (isBuyer.value) {
        return [
            { id: 'buyer-dashboard', label: 'Dashboard', href: route('portal.buyer.dashboard'), links: [] },
            { id: 'buyer-leads', label: 'My Leads', href: route('portal.buyer.leads'), links: [] },
            { id: 'buyer-billing', label: 'Billing', href: route('portal.buyer.billing'), links: [] },
            { id: 'buyer-integrations', label: 'Integrations', href: route('portal.buyer.integrations'), links: [] },
            { id: 'buyer-profile', label: 'Profile', href: route('profile.edit'), links: [] },
        ];
    }

    if (isSupplier.value) {
        return [
            { id: 'supplier-dashboard', label: 'Dashboard', href: route('portal.supplier.dashboard'), links: [] },
            { id: 'supplier-leads', label: 'My Leads', href: route('portal.supplier.leads'), links: [] },
            { id: 'supplier-embeds', label: 'Form embeds', href: route('portal.supplier.embeds'), links: [] },
            { id: 'supplier-integrations', label: 'Integrations', href: route('portal.supplier.integrations'), links: [] },
            { id: 'supplier-billing', label: 'Payouts', href: route('portal.supplier.billing'), links: [] },
            { id: 'supplier-profile', label: 'Profile', href: route('profile.edit'), links: [] },
        ];
    }

    const sections = [];

    if (canAccess('dashboard')) {
        sections.push({ id: 'dashboard', label: 'Dashboard', href: route('dashboard'), links: [] });
    }

    if (isSuperAdmin.value && isCentralHost.value) {
        sections.push({
            id: 'command',
            label: 'Command',
            links: [
                { label: 'Command Center', href: route('command-center.index') },
                { label: 'Platform Events', href: route('platform-events.index') },
            ],
        });
    }

    if (canAccess('tenant')) {
        const tenantLinks = [
            ...(isSuperAdmin.value && isCentralHost.value ? [{ label: 'Partner Platforms', href: route('accounts.index') }] : []),
            ...(isSuperAdmin.value && isCentralHost.value ? [{ label: 'Tenant Billing', href: route('accounts.billing.index') }] : []),
            ...(account.value || !isSuperAdmin.value || !isCentralHost.value ? [
                { label: 'Buyers', href: route('buyers.index') },
                { label: 'Suppliers', href: route('suppliers.index') },
            ] : []),
            { label: 'Users', href: route('users.index') },
        ];

        sections.push({
            id: 'tenant',
            label: 'Tenant',
            links: tenantLinks,
        });
    }

    if (canAccess('campaigns')) {
        sections.push({
            id: 'campaigns',
            label: 'Campaigns',
            links: [
                { label: 'All Campaigns', href: route('campaigns.index') },
                { label: 'Form Builder', href: route('forms.index') },
            ],
        });
    }

    if (canAccess('operations')) {
        sections.push({
            id: 'operations',
            label: 'Operations',
            links: [
                { label: 'Live Operations', href: route('operations.index') },
                { label: 'Lead Pipeline', href: route('leads.index') },
                { label: 'Quarantine', href: route('quarantine.index') },
            ],
        });
    }

    if (canAccess('reports')) {
        sections.push({ id: 'reports', label: 'Reports', href: route('reports.index'), links: [] });
    }

    if (canAccess('routing')) {
        sections.push({
            id: 'routing',
            label: 'Routing',
            links: [
                { label: 'Deliveries', href: route('deliveries.index') },
                { label: 'Ping Tree', href: route('distribution.index') },
                { label: 'Routing Simulator', href: route('routing.simulator') },
                { label: 'Automation Hub', href: route('automation.index') },
            ],
        });
    }

    if (canAccess('logs')) {
        sections.push({
            id: 'logs',
            label: 'Logs',
            links: [
                ...(isSuperAdmin.value && isCentralHost.value ? [
                    { label: 'Live Feed', href: route('live-feed.index') },
                    { label: 'Platform Events', href: route('platform-events.index') },
                    { label: 'Notifications', href: route('notifications.admin.index') },
                ] : []),
                { label: 'Delivery Logs', href: route('logs.delivery') },
                { label: 'API Logs', href: route('logs.api') },
                { label: 'Access Logs', href: route('logs.access') },
                { label: 'Change Logs', href: route('logs.changes') },
                { label: 'Security Logs', href: route('logs.security') },
            ],
        });
    }

    if (canAccess('tools')) {
        sections.push({
            id: 'tools',
            label: 'Tools',
            links: [
                { label: 'API Documentation', href: route('api-docs.index') },
                { label: 'Integrations', href: route('integrations.index') },
                { label: 'API Keys', href: route('api-keys.index') },
                { label: 'Webhooks', href: route('webhooks.index') },
                { label: 'Postbacks', href: route('postbacks.index') },
                { label: 'Import Data', href: route('imports.index') },
                { label: 'Features', href: route('features.index') },
            ],
        });
    }

    if (canAccess('settings') || canAccess('billing') || canAccess('finance')) {
        sections.push({
            id: 'account',
            label: 'Account',
            links: [
                ...(canAccess('settings') ? [
                    { label: 'Settings', href: route('settings.edit'), requiresTenant: true },
                    { label: 'Branding', href: route('branding.edit'), requiresTenant: true },
                ] : []),
                ...(canAccess('finance') ? [{ label: 'Finance', href: route('finance.index'), requiresTenant: true }] : []),
                ...(canAccess('billing') ? [{ label: 'Buyer Billing', href: route('billing.index'), requiresTenant: true }] : []),
                { label: 'Support', href: isSuperAdmin.value ? route('support.admin.index') : route('support.index') },
                { label: 'Notifications', href: route('notifications.index') },
                { label: 'Help Centre', href: route('help.index') },
                { label: 'Profile', href: route('profile.edit') },
            ],
        });
    }

    return sections;
});
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-950">
        <div class="relative mx-auto flex h-14 max-w-[1600px] items-center justify-between gap-2 px-3 sm:px-6 md:grid md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:items-center">
            <!-- Left: mobile menu + brand -->
            <div class="flex min-w-0 flex-1 items-center gap-1.5 justify-self-start md:gap-2">
                <button type="button" class="shrink-0 rounded-lg p-2 text-slate-400 hover:bg-slate-800 md:hidden" @click="mobileOpen = !mobileOpen">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <template v-if="isSuperAdmin && isCentralHost && adminHub">
                    <div class="hidden md:block">
                    <Dropdown align="left" width="72" teleport content-classes="py-0 bg-slate-900 text-slate-100">
                        <template #trigger>
                            <button
                                type="button"
                                class="rounded-lg p-0.5 transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                title="Platform shortcuts"
                                aria-label="Open platform shortcuts"
                            >
                                <BrandLogo
                                    size="sm"
                                    variant="light"
                                    :logo-url="branding?.logo_url"
                                    :brand-name="branding?.display_name"
                                    :show-text="false"
                                />
                            </button>
                        </template>
                        <template #content>
                            <AdminHubMenu :hub="adminHub" />
                        </template>
                    </Dropdown>
                    </div>
                    <Link
                        :href="homeHref"
                        class="hidden min-w-0 truncate text-lg font-bold tracking-tight text-white hover:text-indigo-200 md:inline"
                        :title="brandTitle"
                    >
                        <span class="bg-gradient-to-r from-violet-400 via-indigo-400 to-cyan-400 bg-clip-text text-transparent">Power</span><span>ByExcellence</span>
                    </Link>
                    <Link
                        :href="homeHref"
                        class="min-w-0 max-w-[9.5rem] shrink md:hidden"
                        :title="brandTitle"
                    >
                        <BrandLogo
                            size="sm"
                            variant="light"
                            :logo-url="branding?.logo_url"
                            :brand-name="branding?.display_name"
                            :show-text="true"
                        />
                    </Link>
                </template>
                <Link
                    v-else
                    :href="homeHref"
                    class="min-w-0 max-w-[10rem] shrink-0 sm:max-w-[12rem] xl:max-w-[14rem]"
                    :title="brandTitle"
                >
                    <BrandLogo
                        size="sm"
                        variant="light"
                        :logo-url="branding?.logo_url"
                        :brand-name="branding?.display_name"
                        :show-text="true"
                    />
                </Link>
            </div>

            <!-- Center: primary navigation (desktop) -->
            <nav
                v-if="!isBuyer && !isSupplier"
                class="hidden min-w-0 max-w-[calc(100vw-20rem)] items-center justify-center gap-0.5 overflow-x-auto md:flex lg:max-w-none [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                <Link v-if="canAccess('dashboard')" :href="route('dashboard')" :class="navLinkClass(route().current('dashboard'))">Dashboard</Link>

                <TopNavDropdown
                    v-if="isSuperAdmin && isCentralHost"
                    id="command"
                    label="Command"
                    :active="isAdminRoute(['command-center.*', 'platform-events.*'])"
                >
                    <Link :href="route('command-center.index')" :class="dropdownLinkClass">Command Center</Link>
                    <Link :href="route('platform-events.index')" :class="dropdownLinkClass">Platform Events</Link>
                </TopNavDropdown>

                <TopNavDropdown v-if="canAccess('tenant')" id="tenant" label="Tenant" :active="isAdminRoute(['accounts.*', 'buyers.*', 'suppliers.*', 'users.*'])">
                    <Link v-if="isSuperAdmin && isCentralHost" :href="route('accounts.index')" :class="dropdownLinkClass">Partner Platforms</Link>
                    <Link v-if="isSuperAdmin && isCentralHost" :href="route('accounts.billing.index')" :class="dropdownLinkClass">Tenant Billing</Link>
                    <template v-if="account || !isSuperAdmin || !isCentralHost">
                        <Link :href="route('buyers.index')" :class="dropdownLinkClass">Buyers</Link>
                        <Link :href="route('suppliers.index')" :class="dropdownLinkClass">Suppliers</Link>
                    </template>
                    <Link :href="route('users.index')" :class="dropdownLinkClass">Users</Link>
                </TopNavDropdown>

                <TopNavDropdown v-if="canAccess('campaigns')" id="campaigns" label="Campaigns" :active="isAdminRoute(['campaigns.*', 'forms.*'])">
                    <Link :href="route('campaigns.index')" :class="dropdownLinkClass">All Campaigns</Link>
                    <Link :href="route('forms.index')" :class="dropdownLinkClass">Form Builder</Link>
                </TopNavDropdown>

                <TopNavDropdown v-if="canAccess('operations')" id="operations" label="Operations" :active="isAdminRoute(['operations.*', 'leads.*', 'quarantine.*'])">
                    <Link :href="route('operations.index')" :class="dropdownLinkClass">Live Operations</Link>
                    <Link :href="route('leads.index')" :class="dropdownLinkClass">Lead Pipeline</Link>
                    <Link :href="route('quarantine.index')" :class="dropdownLinkClass">Quarantine</Link>
                </TopNavDropdown>

                <Link v-if="canAccess('reports')" :href="route('reports.index')" :class="navLinkClass(isAdminRoute(['reports.*']))">Reports</Link>

                <TopNavDropdown v-if="canAccess('routing')" id="routing" label="Routing" :active="isAdminRoute(['deliveries.*', 'distribution.*', 'routing.simulator*', 'automation.*'])">
                    <Link :href="route('deliveries.index')" :class="dropdownLinkClass">Deliveries</Link>
                    <Link :href="route('distribution.index')" :class="dropdownLinkClass">Ping Tree</Link>
                    <Link :href="route('routing.simulator')" :class="dropdownLinkClass">Routing Simulator</Link>
                    <Link :href="route('automation.index')" :class="dropdownLinkClass">Automation Hub</Link>
                </TopNavDropdown>

                <TopNavDropdown v-if="canAccess('logs')" id="logs" label="Logs" :active="isAdminRoute(['logs.*', 'live-feed.*', 'platform-events.*', 'notifications.admin.*'])">
                    <Link v-if="isSuperAdmin && isCentralHost" :href="route('live-feed.index')" :class="dropdownLinkClass">Live Feed</Link>
                    <Link v-if="isSuperAdmin && isCentralHost" :href="route('platform-events.index')" :class="dropdownLinkClass">Platform Events</Link>
                    <Link v-if="isSuperAdmin && isCentralHost" :href="route('notifications.admin.index')" :class="dropdownLinkClass">Notifications</Link>
                    <Link :href="route('logs.delivery')" :class="dropdownLinkClass">Delivery Logs</Link>
                    <Link :href="route('logs.api')" :class="dropdownLinkClass">API Logs</Link>
                    <Link :href="route('logs.access')" :class="dropdownLinkClass">Access Logs</Link>
                    <Link :href="route('logs.changes')" :class="dropdownLinkClass">Change Logs</Link>
                    <Link :href="route('logs.security')" :class="dropdownLinkClass">Security Logs</Link>
                </TopNavDropdown>

                <TopNavDropdown v-if="canAccess('tools')" id="tools" label="Tools" :active="isAdminRoute(['api-docs.*', 'integrations.*', 'api-keys.*', 'webhooks.*', 'postbacks.*', 'imports.*', 'features.*'])">
                    <Link :href="route('api-docs.index')" :class="dropdownLinkClass">API Documentation</Link>
                    <Link :href="route('integrations.index')" :class="dropdownLinkClass">Integrations</Link>
                    <Link :href="route('api-keys.index')" :class="dropdownLinkClass">API Keys</Link>
                    <Link :href="route('webhooks.index')" :class="dropdownLinkClass">Webhooks</Link>
                    <Link :href="route('postbacks.index')" :class="dropdownLinkClass">Postbacks</Link>
                    <Link :href="route('imports.index')" :class="dropdownLinkClass">Import Data</Link>
                    <Link :href="route('features.index')" :class="dropdownLinkClass">Features</Link>
                    <template v-if="isSuperAdmin && isCentralHost">
                        <div class="my-1 border-t border-slate-700" />
                        <a href="/horizon" target="_blank" rel="noopener" :class="dropdownLinkClass">Horizon (queues)</a>
                        <a href="/telescope" target="_blank" rel="noopener" :class="dropdownLinkClass">Telescope (debug)</a>
                    </template>
                </TopNavDropdown>

                <TopNavDropdown
                    v-if="canAccess('settings') || canAccess('billing') || canAccess('finance')"
                    id="account"
                    label="Account"
                    :active="isAdminRoute(['settings.*', 'billing.*', 'finance.*', 'profile.*', 'support.*', 'help.*', 'branding.*', 'notifications.index'])"
                >
                    <p
                        v-if="needsTenantOnCentral"
                        class="border-b border-slate-800 px-4 py-2 text-xs leading-relaxed text-slate-400"
                    >
                        Select a platform via <strong class="text-slate-300">Switch</strong> to edit tenant settings.
                    </p>
                    <Link
                        v-if="canAccess('settings')"
                        :href="route('settings.edit')"
                        :class="[dropdownLinkClass, needsTenantOnCentral && 'opacity-80']"
                        @click="guardTenantRoute"
                    >
                        Settings
                    </Link>
                    <Link
                        v-if="canAccess('settings')"
                        :href="route('branding.edit')"
                        :class="[dropdownLinkClass, needsTenantOnCentral && 'opacity-80']"
                        @click="guardTenantRoute"
                    >
                        Branding
                    </Link>
                    <Link
                        v-if="canAccess('finance')"
                        :href="route('finance.index')"
                        :class="[dropdownLinkClass, needsTenantOnCentral && 'opacity-80']"
                        @click="guardTenantRoute"
                    >
                        Finance
                    </Link>
                    <Link
                        v-if="canAccess('billing')"
                        :href="route('billing.index')"
                        :class="[dropdownLinkClass, needsTenantOnCentral && 'opacity-80']"
                        @click="guardTenantRoute"
                    >
                        Buyer Billing
                    </Link>
                    <Link :href="isSuperAdmin ? route('support.admin.index') : route('support.index')" :class="dropdownLinkClass">
                        {{ isSuperAdmin ? 'Support Queue' : 'Support' }}
                    </Link>
                    <Link :href="route('notifications.index')" :class="dropdownLinkClass">Notifications</Link>
                    <Link :href="route('help.index')" :class="dropdownLinkClass">Help Centre</Link>
                    <Link :href="route('profile.edit')" :class="dropdownLinkClass">Profile</Link>
                </TopNavDropdown>
            </nav>

            <nav v-else-if="isBuyer" class="hidden items-center justify-center gap-1 md:flex">
                <Link :href="route('portal.buyer.dashboard')" :class="navLinkClass(route().current('portal.buyer.dashboard'))">Dashboard</Link>
                <Link :href="route('portal.buyer.leads')" :class="navLinkClass(route().current('portal.buyer.leads'))">My Leads</Link>
                <Link :href="route('portal.buyer.billing')" :class="navLinkClass(route().current('portal.buyer.billing'))">Billing</Link>
                <Link :href="route('portal.buyer.integrations')" :class="navLinkClass(route().current('portal.buyer.integrations'))">Integrations</Link>
            </nav>
            <nav v-else-if="isSupplier" class="hidden items-center justify-center gap-1 md:flex">
                <Link :href="route('portal.supplier.dashboard')" :class="navLinkClass(route().current('portal.supplier.dashboard'))">Dashboard</Link>
                <Link :href="route('portal.supplier.leads')" :class="navLinkClass(route().current('portal.supplier.leads'))">My Leads</Link>
                <Link :href="route('portal.supplier.embeds')" :class="navLinkClass(route().current('portal.supplier.embeds'))">Form embeds</Link>
                <Link :href="route('portal.supplier.integrations')" :class="navLinkClass(route().current('portal.supplier.integrations'))">Integrations</Link>
                <Link :href="route('portal.supplier.billing')" :class="navLinkClass(route().current('portal.supplier.billing'))">Payouts</Link>
            </nav>

            <!-- Right: tenant switcher + utilities -->
            <div class="flex shrink-0 items-center justify-end gap-1 justify-self-end sm:gap-1.5">
                <Dropdown v-if="showHeaderTenantSwitcher" align="right" width="56" teleport content-classes="py-1 bg-slate-900 text-slate-100">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-700 bg-slate-900 text-slate-200 transition hover:bg-slate-800 sm:h-9 sm:w-auto sm:px-2"
                            :title="account?.display_name ?? 'All partner platforms'"
                        >
                            <svg class="h-4 w-4 shrink-0 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span class="hidden min-w-0 truncate text-xs font-semibold md:inline">
                                {{ account?.display_name ?? 'All platforms' }}
                            </span>
                            <svg class="hidden h-3.5 w-3.5 shrink-0 opacity-60 md:block" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </template>
                    <template #content>
                        <div class="border-b border-slate-800 px-4 py-2.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Tenant context</p>
                            <p class="mt-0.5 text-sm font-medium text-white">{{ account?.display_name ?? 'All partner platforms' }}</p>
                        </div>
                        <button
                            v-if="account"
                            type="button"
                            class="block w-full px-4 py-2 text-left text-sm text-slate-100 hover:bg-slate-800"
                            @click="clearTenantContext"
                        >
                            All platforms (central admin)
                        </button>
                        <Link v-if="isCentralHost" :href="route('accounts.index')" :class="dropdownLinkClass">Partner platforms list</Link>
                        <Link v-if="isCentralHost" :href="route('accounts.billing.index')" :class="dropdownLinkClass">Tenant billing</Link>
                        <Link v-if="account" :href="route('accounts.billing.edit', account.id)" :class="dropdownLinkClass">Billing for this platform</Link>
                        <Link v-if="account" :href="route('dashboard')" :class="dropdownLinkClass">Tenant dashboard</Link>
                    </template>
                </Dropdown>

                <NotificationBell />
                <ThemeToggle variant="dark" />

                <Dropdown align="right" width="48" teleport content-classes="py-1 bg-slate-900 text-slate-100">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-700 bg-slate-900 transition hover:bg-slate-800 sm:h-9 sm:w-auto sm:gap-2 sm:px-2"
                            :title="user?.name"
                        >
                            <div class="relative h-7 w-7 shrink-0 overflow-hidden rounded-full bg-gradient-to-br from-violet-500 to-indigo-600">
                                <img v-if="user?.avatar_url" :src="user.avatar_url" :alt="user?.name" class="h-full w-full object-cover" />
                                <span v-else class="flex h-full w-full items-center justify-center text-xs font-bold text-white">{{ userInitials }}</span>
                            </div>
                            <span class="hidden max-w-[7rem] truncate text-sm font-medium text-slate-200 lg:inline xl:max-w-[10rem]">{{ user?.name }}</span>
                        </button>
                    </template>
                    <template #content>
                        <Link :href="route('profile.edit')" :class="dropdownLinkClass">Profile</Link>
                        <Link :href="route('logout')" method="post" as="button" class="block w-full cursor-pointer border-0 bg-transparent px-4 py-2 text-left text-sm text-slate-100 hover:bg-slate-800">Log Out</Link>
                    </template>
                </Dropdown>
            </div>
        </div>

        <!-- Mobile navigation -->
        <nav v-if="mobileOpen" class="max-h-[70vh] overflow-y-auto border-t border-slate-800 bg-slate-950 px-3 py-3 md:hidden">
            <div class="space-y-1">
                <template v-for="section in mobileSections" :key="section.id">
                    <Link
                        v-if="section.href"
                        :href="section.href"
                        :class="[...navLinkClass(route().current(section.id === 'dashboard' ? 'dashboard' : `${section.id}.*`)), 'block w-full']"
                        @click="closeMobile"
                    >
                        {{ section.label }}
                    </Link>
                    <div v-else class="rounded-lg border border-slate-800">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm font-medium text-slate-200"
                            @click="toggleMobileSection(section.id)"
                        >
                            {{ section.label }}
                            <svg class="h-4 w-4 transition" :class="mobileSection === section.id ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div v-if="mobileSection === section.id" class="border-t border-slate-800 pb-1">
                            <Link
                                v-for="link in section.links"
                                :key="link.href + link.label"
                                :href="link.href"
                                class="block px-4 py-2 text-sm text-slate-300 hover:bg-slate-800 hover:text-white"
                                @click="(e) => { if (link.requiresTenant && !guardTenantRoute(e)) return; closeMobile(); }"
                            >
                                {{ link.label }}
                            </Link>
                        </div>
                    </div>
                </template>
            </div>
        </nav>
    </header>
</template>
