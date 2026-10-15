<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import NotificationBell from '@/Components/ORI/NotificationBell.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const page = usePage();

const user = computed(() => page.props.auth.user as any);
const isDark = computed(() => (page.props.appearance as any)?.theme === 'dark');
const isAdmin = computed(() => user.value?.role === 'admin');
const flashSuccess = computed(() => (page.props.flash as any)?.success);
const flashError = computed(() => (page.props.flash as any)?.error);
const currentTime = ref(new Date());
const currentDateLabel = computed(() => new Intl.DateTimeFormat(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
}).format(currentTime.value));
const currentTimeLabel = computed(() => new Intl.DateTimeFormat(undefined, {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
}).format(currentTime.value));
let clockTimer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    clockTimer = setInterval(() => {
        currentTime.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    if (clockTimer) clearInterval(clockTimer);
});

type NavigationItem = { label: string; route: string; match: string };

const adminNavigationGroups: { label: string; items: NavigationItem[] }[] = [
    {
        label: 'People & units',
        items: [
            { label: 'Users', route: 'admin.users.index', match: 'admin.users.*' },
            { label: 'Research Profiles', route: 'research-profiles.index', match: 'research-profiles.*' },
            { label: 'Colleges & Departments', route: 'admin.organizations.index', match: 'admin.organizations.*' },
        ],
    },
    {
        label: 'Research setup',
        items: [
            { label: 'Approvals', route: 'admin.approvals.index', match: 'admin.approvals.*' },
            { label: 'Point Schedule', route: 'admin.research-type-points.index', match: 'admin.research-type-points.*' },
            { label: 'Research Types', route: 'admin.research-types.index', match: 'admin.research-types.*' },
            { label: 'Achievement Legend', route: 'admin.achievement-tiers.index', match: 'admin.achievement-tiers.*' },
            { label: 'News & Documents', route: 'admin.news.index', match: 'admin.news.*' },
            { label: 'SDG & Agendas', route: 'admin.research-mapping.index', match: 'admin.research-mapping.*' },
        ],
    },
    {
        label: 'Reports',
        items: [
            { label: 'FPES Reports', route: 'reports.fpes', match: 'reports.fpes' },
            { label: 'Report Setup', route: 'admin.reports.settings', match: 'admin.reports.*' },
        ],
    },
];

function isActive(item: NavigationItem): boolean {
    return Boolean(route().current(item.match));
}

function isGroupActive(group: { items: NavigationItem[] }): boolean {
    return group.items.some(isActive);
}

function toggleTheme() {
    router.patch(route('profile.preferences.update'), {
        theme_preference: isDark.value ? 'light' : 'dark',
    }, { preserveScroll: true });
}

const navItems = computed(() => {
    const role = user.value?.role;
    const items: NavigationItem[] = [{ label: 'Dashboard', route: 'dashboard', match: 'dashboard' }];

    if (role !== 'admin') {
        items.push({ label: 'News', route: 'news.index', match: 'news.*' });
    }

    if (role === 'faculty') {
        items.push({ label: 'Research Profile', route: 'research-profiles.index', match: 'research-profiles.*' });
        items.push({
            label: 'My Submissions',
            route: 'faculty.submissions.index',
            match: 'faculty.submissions.*',
        });
        items.push({ label: 'My FPES', route: 'reports.fpes', match: 'reports.fpes' });
    }

    if (role === 'coordinator') {
        items.push({ label: 'Faculty Profiles', route: 'research-profiles.index', match: 'research-profiles.*' });
        items.push({
            label: 'Evaluations',
            route: 'coordinator.evaluations.index',
            match: 'coordinator.evaluations.*',
        });
        items.push({ label: 'FPES', route: 'reports.fpes', match: 'reports.fpes' });
    }

    if (role === 'admin') {
        items.push(...adminNavigationGroups.flatMap((group) => group.items));
    }

    return items;
});
</script>

<template>
    <div class="app-shell min-h-screen bg-[radial-gradient(circle_at_top,_#fff9e6,_transparent_28%),linear-gradient(180deg,#ffffff_0%,#eaf1fb_100%)]" :class="{ 'theme-dark': isDark }">
        <nav class="relative z-50 border-b border-ori-blue-100 bg-white/90 backdrop-blur">
            <div class="border-b-2 border-b-ori-blue-50 border-t-2 border-t-yellow-400 bg-white/90">
                <div class="mx-auto flex max-w-7xl items-center justify-end gap-3 px-4 py-1.5 text-xs text-ori-blue-600 sm:px-6 lg:px-8">
                    <time :datetime="currentTime.toISOString()" class="tabular-nums" aria-label="Current local date and time">
                        <span>{{ currentDateLabel }}</span>
                        <span class="mx-1.5 text-ori-blue-300" aria-hidden="true">|</span>
                        <span class="font-semibold text-ori-blue-800">{{ currentTimeLabel }}</span>
                    </time>
                </div>
            </div>
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex">
                        <div class="flex shrink-0 items-center gap-3">
                            <Link :href="route('dashboard')" class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-ori-blue-700 text-ori-yellow-400 shadow-soft">
                                    <img
                                        src="/images/ori.ico"
                                        alt="Leyte Normal University Office of Research and Innovation"
                                        class="h-10 w-10 rounded-xl bg-navy p-1 object-contain"
                                    />
                                </div>
                                <div class="hidden leading-tight sm:block">
                                    <div class="font-display text-sm font-semibold text-ori-blue-900">
                                        LNU ORI System
                                    </div>
                                    <div class="text-[11px] uppercase tracking-[0.16em] text-ori-blue-500">
                                        Research & Innovation
                                    </div>
                                </div>
                            </Link>
                        </div>

                        <div :class="isAdmin ? 'hidden xl:-my-px xl:ms-6 xl:flex xl:shrink-0 xl:items-center xl:gap-1' : 'hidden space-x-6 sm:-my-px sm:ms-10 sm:flex'">
                            <template v-if="isAdmin">
                                <NavLink :href="route('dashboard')" :active="isActive(navItems[0])">
                                    Dashboard
                                </NavLink>
                                <Dropdown v-for="group in adminNavigationGroups" :key="group.label" align="left" width="64">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="inline-flex min-h-10 items-center gap-1 border-b-2 px-2 pt-1 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-ori-blue-300 focus:ring-offset-2"
                                            :class="isGroupActive(group) ? 'border-ori-yellow-400 font-semibold text-ori-blue-900' : 'border-transparent text-ori-blue-500 hover:border-ori-blue-200 hover:text-ori-blue-800'"
                                            aria-haspopup="true"
                                        >
                                            {{ group.label }}
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink v-for="item in group.items" :key="item.route" :href="route(item.route)">
                                            <span class="flex items-center justify-between gap-3" :class="isActive(item) ? 'font-semibold text-ori-blue-900' : ''">
                                                {{ item.label }}
                                                <span v-if="isActive(item)" class="h-1.5 w-1.5 shrink-0 rounded-full bg-ori-yellow-500" aria-hidden="true"></span>
                                            </span>
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </template>
                            <template v-else>
                            <NavLink
                                v-for="item in navItems"
                                :key="item.route"
                                :href="route(item.route)"
                                :active="isActive(item)"
                            >
                                {{ item.label }}
                            </NavLink>
                            </template>
                        </div>
                    </div>

                    <div :class="isAdmin ? 'hidden xl:ms-4 xl:flex xl:shrink-0 xl:items-center' : 'hidden sm:ms-6 sm:flex sm:items-center'">
                        <NotificationBell class="me-3" />
                        <button type="button" class="me-3 inline-flex h-10 w-10 items-center justify-center rounded-full border border-ori-blue-100 bg-white text-sm text-ori-blue-700 hover:bg-ori-blue-50" :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'" :title="isDark ? 'Light theme' : 'Dark theme'" @click="toggleTheme">
                            <span aria-hidden="true">{{ isDark ? '☼' : '◐' }}</span>
                        </button>
                        <div class="me-2 hidden rounded-full bg-ori-yellow-100 px-3 py-1 text-xs font-semibold text-ori-blue-800 2xl:block">
                            {{ user?.role_label }}
                        </div>
                        <div class="relative ms-3">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <span class="inline-flex rounded-md">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-xl border border-ori-blue-100 bg-white px-3 py-2 text-sm font-medium text-ori-blue-700 transition hover:bg-ori-blue-50"
                                        >
                                            <img v-if="user?.profile_photo_url" :src="user.profile_photo_url" alt="" class="me-2 h-7 w-7 rounded-full object-cover" />
                                            <span v-else class="me-2 flex h-7 w-7 items-center justify-center rounded-full bg-ori-blue-100 text-xs font-bold text-ori-blue-800">{{ user?.name?.slice(0, 1) }}</span>
                                            <span class="max-w-[9rem] truncate">{{ user?.name }}</span>
                                            <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">
                                        Log Out
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div :class="isAdmin ? '-me-2 flex items-center xl:hidden' : '-me-2 flex items-center sm:hidden'">
                        <NotificationBell class="me-2" />
                        <button type="button" class="me-2 inline-flex h-10 w-10 items-center justify-center rounded-full border border-ori-blue-100 bg-white text-ori-blue-700" :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'" @click="toggleTheme">{{ isDark ? '☼' : '◐' }}</button>
                        <button
                            type="button"
                            @click="showingNavigationDropdown = !showingNavigationDropdown"
                            :aria-expanded="showingNavigationDropdown"
                            :aria-label="showingNavigationDropdown ? 'Close navigation menu' : 'Open navigation menu'"
                            class="inline-flex items-center justify-center rounded-md p-2 text-ori-blue-500 transition hover:bg-ori-blue-50"
                        >
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div :class="[{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }, isAdmin ? 'xl:hidden' : 'sm:hidden']">
                <div class="space-y-1 pb-3 pt-2">
                    <ResponsiveNavLink
                        v-for="item in navItems"
                        :key="item.route"
                        :href="route(item.route)"
                        :active="isActive(item)"
                    >
                        {{ item.label }}
                    </ResponsiveNavLink>
                </div>
                <div class="border-t border-ori-blue-100 pb-1 pt-4">
                    <div class="px-4">
                        <div class="text-base font-medium text-ori-blue-900">{{ user?.name }}</div>
                        <div class="text-sm text-ori-blue-500">{{ user?.email }}</div>
                    </div>
                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                            Log Out
                        </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </nav>

        <div v-if="flashSuccess || flashError" class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <div
                v-if="flashSuccess"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
            >
                {{ flashError }}
            </div>
        </div>

        <header v-if="$slots.header" class="bg-transparent">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
