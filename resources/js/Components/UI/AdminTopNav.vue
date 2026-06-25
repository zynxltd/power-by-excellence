<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import NotificationBell from '@/Components/UI/NotificationBell.vue';
import TopNavDropdown from '@/Components/UI/TopNavDropdown.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { provideNavDropdown } from '@/Composables/useNavDropdown';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

provideNavDropdown();

const page = usePage();
const user = computed(() => page.props.auth.user);
const account = computed(() => page.props.auth.account);
const branding = computed(() => page.props.tenant ?? page.props.auth.account);
const isSuperAdmin = computed(() => page.props.auth.isSuperAdmin);
const isCentralHost = computed(() => page.props.isCentralHost);
const isBuyer = computed(() => page.props.auth.isBuyerPortal);
const isSupplier = computed(() => page.props.auth.isSupplierPortal);
const allowedModules = computed(() => page.props.auth?.allowedModules ?? []);

const canAccess = (module) => isSuperAdmin.value || allowedModules.value.includes(module);

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
    'shrink-0 rounded-lg px-2.5 py-2 text-sm font-medium transition xl:px-3',
    active ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white',
];

const mobileOpen = ref(false);
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-950">
        <div class="mx-auto flex h-14 max-w-[1600px] items-center gap-2 px-3 sm:gap-3 sm:px-6">
            <!-- Left: logo (fixed width — never competes with right actions) -->
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 lg:hidden" @click="mobileOpen = !mobileOpen">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <Link :href="homeHref" class="min-w-0 max-w-[10rem] shrink-0 sm:max-w-[12rem] xl:max-w-[14rem]">
                    <BrandLogo
                        size="sm"
                        variant="light"
                        :logo-url="branding?.logo_url"
                        :brand-name="branding?.display_name"
                        :show-text="true"
                    />
                </Link>
            </div>

            <!-- Center: primary navigation (scrolls when crowded — does not squeeze the right cluster) -->
            <nav
                v-if="!isBuyer && !isSupplier"
                class="hidden min-w-0 flex-1 items-center gap-0.5 overflow-x-auto overscroll-x-contain lg:flex xl:gap-1 [&::-webkit-scrollbar]:hidden"
                style="-ms-overflow-style: none; scrollbar-width: none;"
            >
                <Link v-if="canAccess('dashboard')" :href="route('dashboard')" :class="navLinkClass(route().current('dashboard'))">Dashboard</Link>
                <Link v-if="isSuperAdmin && isCentralHost" :href="route('command-center.index')" :class="navLinkClass(route().current('command-center.*'))">Command Center</Link>

                <TopNavDropdown v-if="canAccess('tenant')" id="tenant" label="Tenant" :active="isAdminRoute(['accounts.*', 'buyers.*', 'suppliers.*', 'users.*'])">
                    <Link v-if="isSuperAdmin && isCentralHost" :href="route('accounts.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Partner Platforms</Link>
                    <Link :href="route('buyers.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Buyers</Link>
                    <Link :href="route('suppliers.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Suppliers</Link>
                    <Link :href="route('users.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Users</Link>
                </TopNavDropdown>

                <TopNavDropdown v-if="canAccess('campaigns')" id="campaigns" label="Campaigns" :active="isAdminRoute(['campaigns.*', 'forms.*'])">
                    <Link :href="route('campaigns.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">All Campaigns</Link>
                    <Link :href="route('forms.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Form Builder</Link>
                </TopNavDropdown>

                <TopNavDropdown v-if="canAccess('operations')" id="operations" label="Operations" :active="isAdminRoute(['operations.*', 'leads.*', 'quarantine.*'])">
                    <Link :href="route('operations.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Live Operations</Link>
                    <Link :href="route('leads.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Lead Pipeline</Link>
                    <Link :href="route('quarantine.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Quarantine</Link>
                </TopNavDropdown>

                <Link v-if="canAccess('reports')" :href="route('reports.index')" :class="navLinkClass(isAdminRoute(['reports.*']))">Reports</Link>

                <TopNavDropdown v-if="canAccess('routing')" id="routing" label="Routing" :active="isAdminRoute(['deliveries.*', 'distribution.*', 'routing.simulator*', 'automation.*'])">
                    <Link :href="route('deliveries.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Deliveries</Link>
                    <Link :href="route('distribution.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Ping Tree</Link>
                    <Link :href="route('routing.simulator')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Routing Simulator</Link>
                    <Link :href="route('automation.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Automation Hub</Link>
                </TopNavDropdown>

                <TopNavDropdown v-if="canAccess('logs')" id="logs" label="Logs" :active="isAdminRoute(['logs.*', 'command-center.*', 'live-feed.*', 'notifications.admin.*'])">
                    <Link v-if="isSuperAdmin && isCentralHost" :href="route('command-center.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Command Center</Link>
                    <Link v-if="isSuperAdmin && isCentralHost" :href="route('live-feed.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Live Feed</Link>
                    <Link v-if="isSuperAdmin && isCentralHost" :href="route('notifications.admin.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Notifications</Link>
                    <Link :href="route('logs.delivery')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Delivery Logs</Link>
                    <Link :href="route('logs.api')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">API Logs</Link>
                    <Link :href="route('logs.access')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Access Logs</Link>
                    <Link :href="route('logs.changes')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Change Logs</Link>
                    <Link :href="route('logs.security')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Security Logs</Link>
                </TopNavDropdown>

                <TopNavDropdown v-if="canAccess('tools')" id="tools" label="Tools" :active="isAdminRoute(['integrations.*', 'api-keys.*', 'webhooks.*', 'postbacks.*', 'imports.*', 'features.*'])">
                    <Link :href="route('integrations.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Integrations</Link>
                    <Link :href="route('api-keys.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">API Keys</Link>
                    <Link :href="route('webhooks.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Webhooks</Link>
                    <Link :href="route('postbacks.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Postbacks</Link>
                    <Link :href="route('imports.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Import Data</Link>
                    <Link :href="route('features.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Features</Link>
                    <template v-if="isSuperAdmin">
                        <div class="my-1 border-t border-slate-700" />
                        <a href="/horizon" target="_blank" rel="noopener" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Horizon (queues)</a>
                        <a href="/telescope" target="_blank" rel="noopener" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Telescope (debug)</a>
                    </template>
                </TopNavDropdown>

                <TopNavDropdown v-if="canAccess('settings') || canAccess('billing') || canAccess('finance')" id="account" label="Account" :active="isAdminRoute(['settings.*', 'billing.*', 'finance.*', 'profile.*', 'support.*', 'help.*', 'branding.*'])">
                    <Link v-if="canAccess('settings')" :href="route('settings.edit')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Settings</Link>
                    <Link v-if="canAccess('settings')" :href="route('branding.edit')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Branding</Link>
                    <Link v-if="canAccess('finance')" :href="route('finance.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Finance</Link>
                    <Link v-if="canAccess('billing')" :href="route('billing.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Buyer billing</Link>
                    <Link :href="route('support.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Support</Link>
                    <Link :href="route('help.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Help Centre</Link>
                    <Link :href="route('profile.edit')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Profile</Link>
                </TopNavDropdown>
            </nav>

            <!-- Buyer portal -->
            <nav v-else-if="isBuyer" class="hidden min-w-0 flex-1 items-center justify-center gap-1 overflow-x-auto lg:flex">
                <Link :href="route('portal.buyer.dashboard')" :class="navLinkClass(route().current('portal.buyer.dashboard'))">Dashboard</Link>
                <Link :href="route('portal.buyer.leads')" :class="navLinkClass(route().current('portal.buyer.leads'))">My Leads</Link>
                <Link :href="route('portal.buyer.billing')" :class="navLinkClass(route().current('portal.buyer.billing'))">Billing</Link>
            </nav>

            <!-- Supplier portal -->
            <nav v-else-if="isSupplier" class="hidden min-w-0 flex-1 items-center justify-center gap-1 overflow-x-auto lg:flex">
                <Link :href="route('portal.supplier.dashboard')" :class="navLinkClass(route().current('portal.supplier.dashboard'))">Dashboard</Link>
                <Link :href="route('portal.supplier.leads')" :class="navLinkClass(route().current('portal.supplier.leads'))">My Leads</Link>
                <Link :href="route('portal.supplier.billing')" :class="navLinkClass(route().current('portal.supplier.billing'))">Payouts</Link>
            </nav>

            <!-- Right: tenant + utilities + user (fixed — never shrinks) -->
            <div class="flex shrink-0 items-center gap-1 sm:gap-1.5">
                <Dropdown v-if="isSuperAdmin && isCentralHost" align="right" width="56" content-classes="py-1 bg-slate-900 ring-slate-700">
                    <template #trigger>
                        <button
                            type="button"
                            class="flex h-9 max-w-[11rem] items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900 px-2 text-left text-slate-200 transition hover:bg-slate-800 sm:max-w-[13rem] xl:max-w-[15rem]"
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
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Active tenant</p>
                            <p class="mt-0.5 text-sm font-medium text-white">{{ account?.display_name ?? 'All partner platforms' }}</p>
                        </div>
                        <Link :href="route('accounts.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Switch platform</Link>
                        <Link v-if="account" :href="route('dashboard')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Tenant dashboard</Link>
                    </template>
                </Dropdown>

                <span
                    v-else-if="isSuperAdmin && account"
                    class="hidden h-9 max-w-[11rem] items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900 px-2 text-slate-200 md:inline-flex xl:max-w-[15rem]"
                    :title="account.display_name"
                >
                    <svg class="h-4 w-4 shrink-0 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-4 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span class="min-w-0 truncate text-xs font-semibold">{{ account.display_name }}</span>
                </span>

                <div class="flex items-center gap-0.5 sm:gap-1">
                    <NotificationBell />
                    <ThemeToggle variant="dark" />
                </div>

                <div class="ml-0.5 border-l border-slate-800 pl-1 sm:ml-1 sm:pl-1.5">
                    <Dropdown align="right" width="48" content-classes="py-1 bg-slate-900 ring-slate-700">
                        <template #trigger>
                            <button
                                type="button"
                                class="flex h-9 items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-1.5 transition hover:bg-slate-800 sm:px-2"
                                :title="user?.name"
                            >
                                <div class="relative h-7 w-7 shrink-0 overflow-hidden rounded-full bg-gradient-to-br from-violet-500 to-indigo-600">
                                    <img v-if="user?.avatar_url" :src="user.avatar_url" :alt="user?.name" class="h-full w-full object-cover" />
                                    <span v-else class="flex h-full w-full items-center justify-center text-xs font-bold text-white">{{ userInitials }}</span>
                                </div>
                                <span class="hidden max-w-[7rem] truncate text-sm font-medium text-slate-200 xl:inline 2xl:max-w-[10rem]">{{ user?.name }}</span>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </div>

        <nav v-if="mobileOpen && !isBuyer && !isSupplier" class="border-t border-slate-800 bg-slate-950 px-4 py-3 lg:hidden">
            <div class="grid grid-cols-2 gap-1 text-sm">
                <Link :href="route('dashboard')" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-slate-800" @click="mobileOpen = false">Dashboard</Link>
                <Link :href="route('buyers.index')" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-slate-800" @click="mobileOpen = false">Buyers</Link>
                <Link :href="route('suppliers.index')" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-slate-800" @click="mobileOpen = false">Suppliers</Link>
                <Link :href="route('campaigns.index')" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-slate-800" @click="mobileOpen = false">Campaigns</Link>
                <Link :href="route('operations.index')" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-slate-800" @click="mobileOpen = false">Live Ops</Link>
                <Link :href="route('deliveries.index')" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-slate-800" @click="mobileOpen = false">Deliveries</Link>
                <Link v-if="canAccess('reports')" :href="route('reports.index')" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-slate-800" @click="mobileOpen = false">Reports</Link>
                <Link :href="route('settings.edit')" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-slate-800" @click="mobileOpen = false">Settings</Link>
            </div>
        </nav>
    </header>
</template>
