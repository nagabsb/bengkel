<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DashboardSidebarMenuItem from './DashboardSidebarMenuItem.vue';

const DEFAULT_LOGO_BACKGROUND_COLOR = '#10B981';

const props = defineProps({
    homeHref: {
        type: String,
        default: '/dashboard',
    },
    roleLabel: {
        type: String,
        default: 'Admin',
    },
    menuItems: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const appName = computed(() => String(page.props?.appName || 'AutoServ'));
const appLogoUrl = computed(() => String(page.props?.appLogoUrl || ''));
const logoBackgroundEnabled = computed(() => Boolean(page.props?.logoBackgroundEnabled ?? true));
const logoBackgroundColor = computed(() => {
    const normalized = String(page.props?.logoBackgroundColor || DEFAULT_LOGO_BACKGROUND_COLOR).trim().toUpperCase();

    return /^#[A-F0-9]{6}$/.test(normalized) ? normalized : DEFAULT_LOGO_BACKGROUND_COLOR;
});
const appInitials = computed(() => {
    const words = appName.value
        .split(' ')
        .map((word) => word.trim())
        .filter((word) => word !== '');

    if (words.length === 0) {
        return 'AS';
    }

    return words
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
});

const avatarTextColor = computed(() => {
    const normalized = logoBackgroundColor.value.replace('#', '');
    const red = Number.parseInt(normalized.slice(0, 2), 16);
    const green = Number.parseInt(normalized.slice(2, 4), 16);
    const blue = Number.parseInt(normalized.slice(4, 6), 16);
    const luma = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

    return luma >= 148 ? '#0F172A' : '#FFFFFF';
});

const logoAvatarStyle = computed(() => {
    if (!logoBackgroundEnabled.value) {
        return {};
    }

    return {
        backgroundColor: logoBackgroundColor.value,
        color: avatarTextColor.value,
    };
});

const SIDEBAR_STORAGE_KEY = 'dashboard_sidebar_collapsed';
const isSidebarCollapsed = ref(false);
const expandedMenuKeys = ref({});
const collapsedSubmenuKey = ref(null);
const sidebarRef = ref(null);

const hasChildren = (item) => Array.isArray(item?.children) && item.children.length > 0;
const hasActiveChild = (item) => hasChildren(item) && item.children.some((child) => Boolean(child?.active));
const isParentActive = (item) => Boolean(item?.active) || hasActiveChild(item);

const isSubmenuOpen = (item) => {
    if (!hasChildren(item)) {
        return false;
    }

    if (hasActiveChild(item)) {
        return true;
    }

    return Boolean(expandedMenuKeys.value[item.key]);
};

const toggleSubmenu = (itemKey) => {
    expandedMenuKeys.value = {
        ...expandedMenuKeys.value,
        [itemKey]: !expandedMenuKeys.value[itemKey],
    };
};

const closeCollapsedSubmenu = () => {
    collapsedSubmenuKey.value = null;
};

const handleParentMenuClick = (item) => {
    if (isSidebarCollapsed.value) {
        if (!hasChildren(item)) {
            return;
        }

        collapsedSubmenuKey.value = collapsedSubmenuKey.value === item.key ? null : item.key;
        return;
    }

    closeCollapsedSubmenu();
    toggleSubmenu(item.key);
};

const initializeExpandedMenu = (items) => {
    if (!Array.isArray(items)) {
        expandedMenuKeys.value = {};
        return;
    }

    const nextState = {};
    items.forEach((item) => {
        if (hasChildren(item) && item.key) {
            nextState[item.key] = Boolean(item.expanded) || hasActiveChild(item);
        }
    });

    expandedMenuKeys.value = nextState;
};

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(SIDEBAR_STORAGE_KEY, isSidebarCollapsed.value ? '1' : '0');
    }

    if (!isSidebarCollapsed.value) {
        closeCollapsedSubmenu();
    }
};

const handleDocumentClick = (event) => {
    if (!collapsedSubmenuKey.value || !event.target || !(event.target instanceof Node)) {
        return;
    }

    if (!(event.target instanceof Element)) {
        closeCollapsedSubmenu();
        return;
    }

    if (event.target.closest('[data-collapsed-submenu-root]')) {
        return;
    }

    if (sidebarRef.value && sidebarRef.value.contains(event.target)) {
        closeCollapsedSubmenu();
        return;
    }

    closeCollapsedSubmenu();
};

const handleDocumentKeydown = (event) => {
    if (event.key === 'Escape') {
        closeCollapsedSubmenu();
    }
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        isSidebarCollapsed.value = window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', handleDocumentKeydown);
    }
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleDocumentKeydown);
});

watch(
    () => props.menuItems,
    (items) => {
        initializeExpandedMenu(items);
    },
    {
        immediate: true,
        deep: true,
    },
);

watch(isSidebarCollapsed, (collapsed) => {
    if (!collapsed) {
        closeCollapsedSubmenu();
    }
});
</script>

<template>
    <aside
        ref="sidebarRef"
        class="relative hidden h-dvh shrink-0 flex-col border-r border-slate-200 bg-white transition-[width] duration-200 ease-out dark:border-slate-700 dark:bg-slate-800 lg:flex"
        :class="isSidebarCollapsed ? 'w-20' : 'w-64'"
    >
        <button
            type="button"
            class="absolute -right-3 top-8 z-20 grid h-6 w-6 cursor-pointer place-items-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-emerald-300 hover:text-emerald-600 active:scale-95 dark:border-slate-600 dark:bg-slate-700/90 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
            :aria-label="isSidebarCollapsed ? 'Perlebar sidebar' : 'Perkecil sidebar'"
            @click="toggleSidebar"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                class="h-3.5 w-3.5 transition-transform duration-200 ease-out"
                :class="isSidebarCollapsed ? 'rotate-180' : ''"
                aria-hidden="true"
            >
                <path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        <Link
            :href="homeHref"
            class="group flex h-20 shrink-0 items-center border-b border-slate-200 transition-colors hover:bg-transparent dark:border-slate-700 dark:hover:bg-transparent"
            :class="isSidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-5'"
        >
            <div
                class="grid h-10 w-10 place-items-center overflow-hidden rounded-xl text-sm font-bold transition-transform duration-150 ease-out group-hover:scale-105"
                :class="logoBackgroundEnabled ? 'border border-transparent shadow-sm' : 'border border-slate-300 text-slate-700 dark:border-slate-600 dark:text-slate-200'"
                :style="logoAvatarStyle"
            >
                <img
                    v-if="appLogoUrl"
                    :src="appLogoUrl"
                    :alt="`Logo ${appName}`"
                    class="h-full w-full object-contain p-1"
                >
                <span v-else>{{ appInitials }}</span>
            </div>
            <div
                class="overflow-hidden transition-all duration-150 ease-out"
                :class="isSidebarCollapsed ? 'w-0 -translate-x-1 opacity-0' : 'w-40 translate-x-0 opacity-100'"
            >
                <p class="text-lg font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ appName }}</p>
                <p class="text-xs font-medium uppercase tracking-wide text-emerald-600">{{ roleLabel }}</p>
            </div>
        </Link>

        <div
            class="flex min-h-0 flex-1 flex-col"
            :class="isSidebarCollapsed ? 'px-2 pb-4 pt-5' : 'px-4 pb-4 pt-5'"
        >
            <p
                class="overflow-hidden text-xs font-semibold uppercase tracking-wider text-slate-400 transition-all duration-150 ease-out dark:text-slate-400"
                :class="isSidebarCollapsed ? 'mb-0 max-h-0 -translate-y-1 opacity-0' : 'mb-2 max-h-8 translate-y-0 opacity-100'"
            >
                Menu Utama
            </p>
            <nav class="sidebar-scroll -mr-2 flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto pr-3">
                <DashboardSidebarMenuItem
                    v-for="item in menuItems"
                    :key="item.key"
                    :item="item"
                    :is-sidebar-collapsed="isSidebarCollapsed"
                    :is-submenu-open="isSubmenuOpen(item)"
                    :is-parent-active="isParentActive(item)"
                    :show-collapsed-submenu="collapsedSubmenuKey === item.key"
                    @parent-click="handleParentMenuClick"
                    @close-collapsed-submenu="closeCollapsedSubmenu"
                />
            </nav>
        </div>
    </aside>
</template>
