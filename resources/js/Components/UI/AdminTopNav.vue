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

const navDropdown = provideNavDropdown();

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
    'rounded-lg px-3 py-2 text-sm font-medium transition',
    active ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white',
];

const mobileOpen = ref(false);
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-950">
        <div class="mx-auto flex h-14 max-w-[1600px] items-center gap-2 px-4 sm:gap-4 sm:px-6">
            <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 lg:hidden" @click="mobileOpen = !mobileOpen">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <Link :href="homeHref" class="shrink-0">
                <BrandLogo
                    size="sm"
                    variant="light"
                    :logo-url="branding?.logo_url"
                    :brand-name="branding?.display_name"
                    :show-text="true"
                />
            </Link>

            <!-- Admin navigation -->
            <nav v-if="!isBuyer && !isSupplier" class="hidden min-w-0 flex-1 items-center gap-1 lg:flex">
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

                <TopNavDropdown v-if="canAccess('operations')" id="operations" label="Operations" :active="isAdminRoute(['operations.*', 'leads.*', 'quarantine.*', 'reports.*'])">
                    <Link :href="route('operations.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Live Operations</Link>
                    <Link :href="route('leads.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Lead Pipeline</Link>
                    <Link :href="route('quarantine.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Quarantine</Link>
                    <Link :href="route('reports.index')" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Reports</Link>
                </TopNavDropdown>

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
            <nav v-else-if="isBuyer" class="hidden flex-1 items-center gap-1 lg:flex">
                <Link :href="route('portal.buyer.dashboard')" :class="navLinkClass(route().current('portal.buyer.dashboard'))">Dashboard</Link>
                <Link :href="route('portal.buyer.leads')" :class="navLinkClass(route().current('portal.buyer.leads'))">My Leads</Link>
                <Link :href="route('portal.buyer.billing')" :class="navLinkClass(route().current('portal.buyer.billing'))">Billing</Link>
            </nav>

            <!-- Supplier portal -->
            <nav v-else-if="isSupplier" class="hidden flex-1 items-center gap-1 lg:flex">
                <Link :href="route('portal.supplier.dashboard')" :class="navLinkClass(route().current('portal.supplier.dashboard'))">Dashboard</Link>
                <Link :href="route('portal.supplier.leads')" :class="navLinkClass(route().current('portal.supplier.leads'))">My Leads</Link>
                <Link :href="route('portal.supplier.billing')" :class="navLinkClass(route().current('portal.supplier.billing'))">Payouts</Link>
            </nav>

            <div class="ml-auto flex items-center gap-2 sm:gap-3">
                <Link
                    v-if="isSuperAdmin && account"
                    :href="route('accounts.index')"
                    class="hidden max-w-[10rem] truncate rounded-full bg-indigo-600/20 px-3 py-1 text-xs font-semibold text-indigo-200 ring-1 ring-indigo-500/40 sm:inline-block"
                    :title="account.display_name"
                >
                    {{ account.display_name }}
                </Link>
                <Link
                    v-else-if="isSuperAdmin"
                    :href="route('accounts.index')"
                    class="hidden rounded-full bg-amber-500/20 px-3 py-1 text-xs font-semibold text-amber-200 ring-1 ring-amber-500/40 sm:inline-block"
                >
                    All platforms
                </Link>

                <NotificationBell />
                <ThemeToggle />
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button type="button" class="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-900 px-2 py-1.5 text-sm transition hover:bg-slate-800">
                            <div class="relative h-7 w-7 shrink-0 overflow-hidden rounded-full bg-gradient-to-br from-violet-500 to-indigo-600">
                                <img v-if="user?.avatar_url" :src="user.avatar_url" :alt="user?.name" class="h-full w-full object-cover" />
                                <span v-else class="flex h-full w-full items-center justify-center text-xs font-bold text-white">{{ userInitials }}</span>
                            </div>
                            <span class="hidden font-medium text-slate-200 sm:inline">{{ user?.name }}</span>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                    </template>
                </Dropdown>
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
                <Link :href="route('reports.index')" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-slate-800" @click="mobileOpen = false">Reports</Link>
                <Link :href="route('settings.edit')" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-slate-800" @click="mobileOpen = false">Settings</Link>
            </div>
        </nav>
    </header>
</template>
