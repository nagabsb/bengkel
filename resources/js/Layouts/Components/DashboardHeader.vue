<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AsyncSelect from '../../Components/UI/AsyncSelect.vue';
import ThemeToggle from '../../Components/UI/ThemeToggle.vue';
import { useThemeMode } from '../../Composables/useThemeMode';

const props = defineProps({
    title: {
        type: String,
        default: 'Dasbor',
    },
    subtitle: {
        type: String,
        default: 'Ringkasan dan statistik utama',
    },
    searchPlaceholder: {
        type: String,
        default: 'Cari sesuatu...',
    },
    roleLabel: {
        type: String,
        default: 'Admin',
    },
    user: {
        type: Object,
        default: () => ({}),
    },
    menuItems: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['logout']);
const { isDark, toggleTheme } = useThemeMode();
const page = usePage();
const isUserMenuOpen = ref(false);
const userMenuRef = ref(null);
const selectedWorkshopId = ref('');
const isWorkshopSwitching = ref(false);

const initials = computed(() => {
    const name = String(props.user?.name || props.roleLabel || 'A').trim();
    if (name === '') {
        return 'A';
    }

    const segments = name.split(/\s+/).slice(0, 2);
    return segments.map((segment) => segment.charAt(0).toUpperCase()).join('');
});

const hasChildren = (item) => Array.isArray(item?.children) && item.children.length > 0;

const mobileMenuItems = computed(() => props.menuItems.flatMap((item) => {
    if (!hasChildren(item)) {
        return [item];
    }

    return item.children.map((child) => ({
        ...child,
        key: `${item.key}-${child.key}`,
        label: `${item.label}: ${child.label}`,
    }));
}));

const workshopSwitcher = computed(() => {
    const payload = page.props?.ownerWorkshopSwitcher;

    return payload && typeof payload === 'object' ? payload : null;
});

const activeWorkshopId = computed(() => String(workshopSwitcher.value?.active_workshop_id || '').trim());
const switchWorkshopRoute = computed(() => String(workshopSwitcher.value?.switch_route || '').trim());

const workshopSwitcherOptions = computed(() => (
    Array.isArray(workshopSwitcher.value?.workshops)
        ? workshopSwitcher.value.workshops
            .map((workshop) => ({
                value: String(workshop?.id || ''),
                label: String(workshop?.name || '').trim(),
                subtitle: String(workshop?.code || '').trim(),
                is_primary: Boolean(workshop?.is_primary),
                is_all: Boolean(workshop?.is_all),
                is_used: String(workshop?.id || '').trim() !== '' && String(workshop?.id || '').trim() === activeWorkshopId.value,
            }))
            .filter((workshop) => workshop.value !== '' && workshop.label !== '')
        : []
));

const canSwitchWorkshop = computed(() => (
    Boolean(workshopSwitcher.value?.can_switch)
    && workshopSwitcherOptions.value.length > 1
    && switchWorkshopRoute.value !== ''
));

const activeWorkshopDisplay = computed(() => {
    const activeName = String(workshopSwitcher.value?.active_workshop_name || '').trim();
    const activeCode = String(workshopSwitcher.value?.active_workshop_code || '').trim();

    if (activeName === '' && activeCode === '') {
        return '';
    }

    return [activeName, activeCode !== '' ? `(${activeCode})` : '']
        .filter((segment) => segment !== '')
        .join(' ');
});

watch(
    workshopSwitcher,
    (nextSwitcher) => {
        selectedWorkshopId.value = String(nextSwitcher?.active_workshop_id || '').trim();
    },
    { immediate: true, deep: true },
);

const toggleUserMenu = () => {
    isUserMenuOpen.value = !isUserMenuOpen.value;
};

const closeUserMenu = () => {
    isUserMenuOpen.value = false;
};

const handleWorkshopChange = (nextWorkshopId) => {
    const normalizedWorkshopId = String(nextWorkshopId || '').trim();
    if (!canSwitchWorkshop.value || normalizedWorkshopId === '' || switchWorkshopRoute.value === '') {
        return;
    }

    if (normalizedWorkshopId === selectedWorkshopId.value) {
        return;
    }

    selectedWorkshopId.value = normalizedWorkshopId;
    closeUserMenu();

    router.post(
        switchWorkshopRoute.value,
        {
            workshop_id: normalizedWorkshopId,
        },
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
            onStart: () => {
                isWorkshopSwitching.value = true;
            },
            onSuccess: () => {
                router.reload({
                    preserveScroll: true,
                    preserveState: false,
                });
            },
            onFinish: () => {
                isWorkshopSwitching.value = false;
            },
            onError: () => {
                selectedWorkshopId.value = String(workshopSwitcher.value?.active_workshop_id || '').trim();
            },
        },
    );
};

const handleDocumentClick = (event) => {
    if (!event.target || !(event.target instanceof Node)) {
        return;
    }

    if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
        closeUserMenu();
    }
};

const handleDocumentKeydown = (event) => {
    if (event.key === 'Escape') {
        closeUserMenu();
    }
};

onMounted(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleDocumentKeydown);
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleDocumentKeydown);
});
</script>

<template>
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur transition-colors dark:border-slate-700 dark:bg-slate-800/95">
        <div class="flex min-h-20 flex-wrap items-center justify-between gap-3 px-4 py-3 xl:px-6">
            <div class="order-1 min-w-0 flex-1 xl:order-none xl:max-w-xs xl:justify-self-start">
                <h1 class="truncate text-xl font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ title }}</h1>
                <p class="hidden truncate text-sm text-slate-500 dark:text-slate-400 sm:block">{{ subtitle }}</p>
            </div>

            <div class="order-2 ml-auto flex shrink-0 items-center gap-2 xl:order-none xl:ml-0">
                <div v-if="canSwitchWorkshop" class="hidden w-64 md:block">
                    <AsyncSelect
                        :model-value="selectedWorkshopId"
                        :options="workshopSwitcherOptions"
                        placeholder="Filter bengkel"
                        search-placeholder="Cari bengkel..."
                        empty-text="Bengkel tidak ditemukan."
                        :clearable="false"
                        :disabled="isWorkshopSwitching"
                        trigger-class="h-10 border-emerald-200/70 bg-emerald-50 text-emerald-800 hover:border-emerald-300 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-200"
                        @update:model-value="handleWorkshopChange"
                    >
                        <template #selected="{ option, placeholder }">
                            <span class="block truncate text-sm font-semibold">
                                {{ option ? [option.label, option.subtitle].filter((segment) => segment).join(' - ') : placeholder }}
                            </span>
                        </template>
                        <template #option="{ option, selected }">
                            <div class="flex min-w-0 flex-1 items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">
                                        {{ [option?.label, option?.subtitle].filter((segment) => segment).join(' - ') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span
                                        v-if="option?.is_all"
                                        class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:border-indigo-400/30 dark:bg-indigo-500/15 dark:text-indigo-300"
                                    >
                                        Global
                                    </span>
                                    <span
                                        v-if="option?.is_primary"
                                        class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/15 dark:text-sky-300"
                                    >
                                        Utama
                                    </span>
                                    <span
                                        v-if="option?.is_used || selected"
                                        class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                                    >
                                        Digunakan
                                    </span>
                                </div>
                            </div>
                        </template>
                    </AsyncSelect>
                </div>
                <span
                    v-else-if="activeWorkshopDisplay !== ''"
                    class="hidden items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 md:inline-flex"
                >
                    {{ activeWorkshopDisplay }}
                </span>
                <button type="button" class="hidden h-10 w-10 cursor-pointer place-items-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-emerald-300 hover:text-slate-700 active:scale-95 dark:border-slate-600 dark:bg-slate-700/90 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:text-slate-100 sm:grid">
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                        <path d="M20 12A8 8 0 1 1 17.7 6.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        <path d="M20 4V10H14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <ThemeToggle :is-dark="isDark" @toggle="toggleTheme" />
                <button type="button" class="hidden h-10 w-10 cursor-pointer place-items-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-emerald-300 hover:text-slate-700 active:scale-95 dark:border-slate-600 dark:bg-slate-700/90 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:text-slate-100 sm:grid">
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                        <path d="M15 17H20L19 15V10A7 7 0 1 0 5 10V15L4 17H9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M10 19C10.4 20.2 11.1 20.8 12 20.8C12.9 20.8 13.6 20.2 14 19" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                    </svg>
                </button>
                <div ref="userMenuRef" class="relative">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white p-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 active:scale-95 dark:border-slate-600 dark:bg-slate-700/90 dark:text-slate-100 dark:hover:bg-slate-600 sm:pl-2 sm:pr-3 sm:py-1.5"
                        aria-haspopup="menu"
                        :aria-expanded="isUserMenuOpen ? 'true' : 'false'"
                        @click="toggleUserMenu"
                    >
                        <span class="grid h-7 w-7 place-items-center rounded-full bg-emerald-500 text-[11px] font-bold text-white">{{ initials }}</span>
                        <span class="hidden sm:inline">{{ roleLabel }}</span>
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            class="hidden h-4 w-4 text-slate-400 transition-transform duration-150 ease-out dark:text-slate-500 sm:block"
                            :class="isUserMenuOpen ? 'rotate-180' : ''"
                            aria-hidden="true"
                        >
                            <path d="M7 10L12 15L17 10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <Transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="translate-y-1 scale-95 opacity-0"
                        enter-to-class="translate-y-0 scale-100 opacity-100"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="translate-y-0 scale-100 opacity-100"
                        leave-to-class="translate-y-1 scale-95 opacity-0"
                    >
                        <div
                            v-if="isUserMenuOpen"
                            class="absolute right-0 top-[calc(100%+0.5rem)] z-30 w-44 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-slate-200/80 dark:border-slate-600 dark:bg-slate-800 dark:shadow-black/35"
                            role="menu"
                        >
                            <Link
                                href="/profile"
                                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 active:scale-95 dark:text-slate-200 dark:hover:bg-slate-800"
                                role="menuitem"
                                @click="closeUserMenu"
                            >
                                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4 text-slate-400 dark:text-slate-500" aria-hidden="true">
                                    <path d="M12 12A4 4 0 1 0 12 4A4 4 0 1 0 12 12Z" stroke="currentColor" stroke-width="1.7" />
                                    <path d="M5 20A7 7 0 0 1 19 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                </svg>
                                <span>Profil</span>
                            </Link>

                            <button
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-rose-600 transition hover:bg-rose-50 active:scale-95 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                role="menuitem"
                                @click="closeUserMenu(); emit('logout')"
                            >
                                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                    <path d="M15 16L19 12L15 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M19 12H10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                    <path d="M10 5H7A2 2 0 0 0 5 7V17A2 2 0 0 0 7 19H10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                </svg>
                                <span>Logout</span>
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-100 px-4 py-2 dark:border-slate-700 lg:hidden">
            <div class="sm:hidden">
                <div class="mb-1 flex items-center justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Menu Cepat</p>
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-400 dark:text-slate-500">
                        Geser
                        <svg viewBox="0 0 24 24" fill="none" class="h-3.5 w-3.5" aria-hidden="true">
                            <path d="M10 7L15 12L10 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>

                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-6 bg-gradient-to-l from-white/95 to-transparent dark:from-slate-800/95" />

                    <div class="no-scrollbar flex snap-x snap-mandatory gap-2 overflow-x-auto pb-1 pl-1 pr-6">
                        <component
                            v-for="item in mobileMenuItems"
                            :key="`mobile-${item.key}`"
                            :is="item.href ? Link : 'button'"
                            :href="item.href || undefined"
                            :type="item.href ? undefined : 'button'"
                            class="shrink-0 snap-start cursor-pointer rounded-full border px-3 py-1.5 text-xs font-semibold transition active:scale-95"
                            :class="item.active
                                ? 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/20 dark:text-emerald-300'
                                : 'border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-700/90 dark:text-slate-200'"
                        >
                            {{ item.label }}
                        </component>
                    </div>
                </div>
            </div>

            <div class="hidden sm:block">
                <div class="no-scrollbar flex gap-2 overflow-x-auto pb-1">
                    <component
                        v-for="item in mobileMenuItems"
                        :key="`mobile-simple-${item.key}`"
                        :is="item.href ? Link : 'button'"
                        :href="item.href || undefined"
                        :type="item.href ? undefined : 'button'"
                        class="shrink-0 cursor-pointer rounded-full border px-3 py-1.5 text-xs font-semibold transition active:scale-95"
                        :class="item.active
                            ? 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/20 dark:text-emerald-300'
                            : 'border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-700/90 dark:text-slate-200'"
                    >
                        {{ item.label }}
                    </component>
                </div>
            </div>
        </div>
    </header>
</template>


