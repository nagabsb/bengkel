<script setup>
import { computed, nextTick, onMounted, ref } from "vue";
import InputField from "../../../../Components/UI/InputField.vue";
import CurrencyInput from "../../../../Components/UI/CurrencyInput.vue";

const props = defineProps({
    isEditMode: {
        type: Boolean,
        default: false,
    },
    form: {
        type: Object,
        required: true,
    },
    menuOptions: {
        type: Array,
        default: () => [],
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(["close", "submit"]);

const menuSearch = ref("");
const firstInputId = "plan-name";

const normalizeMenuId = (menuId) => Number(menuId);

const ensureMenuIdsArray = () => {
    if (!Array.isArray(props.form.menu_ids)) {
        props.form.menu_ids = [];
    }
};

const selectedMenuIds = computed(() => {
    if (!Array.isArray(props.form.menu_ids)) {
        return [];
    }

    return props.form.menu_ids
        .map((menuId) => normalizeMenuId(menuId))
        .filter((menuId, index, source) => menuId > 0 && source.indexOf(menuId) === index);
});

const selectedMenuIdSet = computed(() => new Set(selectedMenuIds.value));

const normalizedMenuOptions = computed(() => {
    if (!Array.isArray(props.menuOptions)) {
        return [];
    }

    return props.menuOptions
        .map((menu, orderIndex) => ({
            id: normalizeMenuId(menu?.id),
            label: String(menu?.label || "-"),
            route: menu?.route ? String(menu.route) : "",
            parentId:
                menu?.parent_id === null || menu?.parent_id === undefined
                    ? null
                    : normalizeMenuId(menu.parent_id),
            sortOrder: Number(menu?.sort_order) || 0,
            orderIndex,
        }))
        .filter((menu) => menu.id > 0);
});

const rootMenuGroups = computed(() => {
    const menus = normalizedMenuOptions.value;
    if (menus.length === 0) {
        return [];
    }

    const menuById = new Map(menus.map((menu) => [menu.id, menu]));
    const childrenByParent = new Map();

    const addChild = (parentKey, menu) => {
        if (!childrenByParent.has(parentKey)) {
            childrenByParent.set(parentKey, []);
        }

        childrenByParent.get(parentKey).push(menu);
    };

    menus.forEach((menu) => {
        const parentKey = menu.parentId && menuById.has(menu.parentId)
            ? menu.parentId
            : 0;

        addChild(parentKey, menu);
    });

    const sortByOrder = (firstMenu, secondMenu) => {
        if (firstMenu.sortOrder !== secondMenu.sortOrder) {
            return firstMenu.sortOrder - secondMenu.sortOrder;
        }

        return firstMenu.orderIndex - secondMenu.orderIndex;
    };

    childrenByParent.forEach((childMenus, parentKey) => {
        childrenByParent.set(parentKey, [...childMenus].sort(sortByOrder));
    });

    const buildBranch = (menu, depth = 0) => {
        const childMenus = childrenByParent.get(menu.id) || [];

        return [
            { ...menu, depth },
            ...childMenus.flatMap((childMenu) => buildBranch(childMenu, depth + 1)),
        ];
    };

    const rootMenus = childrenByParent.get(0) || [];

    return rootMenus.map((rootMenu) => ({
        id: rootMenu.id,
        label: rootMenu.label,
        items: buildBranch(rootMenu),
    }));
});

const filteredMenuGroups = computed(() => {
    const keyword = menuSearch.value.trim().toLowerCase();
    if (keyword === "") {
        return rootMenuGroups.value;
    }

    return rootMenuGroups.value
        .map((menuGroup) => {
            const filteredItems = menuGroup.items.filter((menu) => {
                return (
                    menu.label.toLowerCase().includes(keyword) ||
                    menu.route.toLowerCase().includes(keyword)
                );
            });

            return {
                ...menuGroup,
                items: filteredItems,
            };
        })
        .filter((menuGroup) => menuGroup.items.length > 0);
});

const visibleMenuIds = computed(() => {
    return filteredMenuGroups.value.flatMap((menuGroup) => {
        return menuGroup.items.map((menu) => menu.id);
    });
});

const selectedMenus = computed(() => {
    const selectedSet = selectedMenuIdSet.value;

    return normalizedMenuOptions.value.filter((menu) => selectedSet.has(menu.id));
});

const selectedMenuCount = computed(() => selectedMenuIds.value.length);

const isMenuSelected = (menuId) => {
    return selectedMenuIdSet.value.has(normalizeMenuId(menuId));
};

const updateMenuIds = (nextIds) => {
    props.form.menu_ids = nextIds;
};

const toggleMenu = (menuId) => {
    const normalizedMenuId = normalizeMenuId(menuId);
    ensureMenuIdsArray();

    if (isMenuSelected(normalizedMenuId)) {
        updateMenuIds(selectedMenuIds.value.filter((id) => id !== normalizedMenuId));
        return;
    }

    updateMenuIds([...selectedMenuIds.value, normalizedMenuId]);
};

const toggleAllMenus = () => {
    const allMenuIds = normalizedMenuOptions.value.map((menu) => menu.id);

    if (allMenuIds.length === 0) {
        updateMenuIds([]);
        return;
    }

    const selectedSet = selectedMenuIdSet.value;
    const isAllSelected = allMenuIds.every((menuId) => selectedSet.has(menuId));

    updateMenuIds(isAllSelected ? [] : allMenuIds);
};

const toggleVisibleMenus = () => {
    const currentVisibleIds = visibleMenuIds.value;
    if (currentVisibleIds.length === 0) {
        return;
    }

    const selectedSet = selectedMenuIdSet.value;
    const allVisibleSelected = currentVisibleIds.every((menuId) => selectedSet.has(menuId));

    if (allVisibleSelected) {
        const visibleSet = new Set(currentVisibleIds);
        updateMenuIds(selectedMenuIds.value.filter((menuId) => !visibleSet.has(menuId)));
        return;
    }

    updateMenuIds([...new Set([...selectedMenuIds.value, ...currentVisibleIds])]);
};

const clearSelectedMenus = () => {
    updateMenuIds([]);
};

const countSelectedMenusInGroup = (groupItems) => {
    const selectedSet = selectedMenuIdSet.value;

    return groupItems.reduce((count, menu) => {
        return selectedSet.has(menu.id) ? count + 1 : count;
    }, 0);
};

const menuIndentClass = (depth) => {
    if (depth >= 2) {
        return "ml-8";
    }

    if (depth === 1) {
        return "ml-4";
    }

    return "";
};

const handleEscKey = (event) => {
    if (event.key !== "Escape") {
        return;
    }

    event.preventDefault();
    emit("close");
};

const handleEnterKey = (event) => {
    if (event.key !== "Enter" || event.isComposing) {
        return;
    }

    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return;
    }

    if (target.closest('[data-enter-ignore="true"]')) {
        return;
    }

    const tagName = target.tagName.toLowerCase();
    if (tagName === "textarea" || tagName === "button") {
        return;
    }

    if (tagName === "input") {
        const inputType = String(target.getAttribute("type") || "text").toLowerCase();
        if (["checkbox", "radio", "file", "submit", "button"].includes(inputType)) {
            return;
        }
    }

    event.preventDefault();
    emit("submit");
};

const focusFirstInput = () => {
    nextTick(() => {
        const firstInput = document.getElementById(firstInputId);
        if (!(firstInput instanceof HTMLInputElement)) {
            return;
        }

        firstInput.focus();
        firstInput.select();
    });
};

onMounted(() => {
    if (!props.isEditMode) {
        focusFirstInput();
    }
});
</script>

<template>
    <article
        class="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm sm:max-h-[calc(100dvh-3rem)] dark:border-emerald-500/20 dark:bg-slate-900"
    >
        <div
            class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-white px-5 py-3 dark:border-slate-800 dark:bg-slate-900"
        >
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                {{ isEditMode ? "Edit Plan" : "Tambah Plan" }}
            </h3>
            <div class="flex items-center">
                <button
                    type="button"
                    class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                    aria-label="Tutup modal"
                    @click="emit('close')"
                >
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                        <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="modal-scroll-green min-h-0 overflow-y-auto px-5 pb-5 pt-4">
            <form class="space-y-4" @submit.prevent="emit('submit')" @keydown.esc="handleEscKey" @keydown.enter="handleEnterKey">
                <InputField
                    id="plan-name"
                    v-model="form.name"
                    label="Nama Plan"
                    placeholder="Contoh: Growth"
                    :error="form.errors.name"
                />

                <InputField
                    id="plan-slug"
                    v-model="form.slug"
                    label="Slug"
                    placeholder="growth"
                    :error="form.errors.slug"
                />

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label
                            for="max-workshops"
                            class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                        >
                            Max Bengkel
                        </label>
                        <input
                            id="max-workshops"
                            v-model.number="form.max_workshops"
                            type="number"
                            min="1"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400/40"
                        >
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Jumlah bengkel yang bisa dibuat tenant.
                        </p>
                        <p
                            v-if="form.errors.max_workshops"
                            class="text-sm font-medium text-rose-600 dark:text-rose-300"
                        >
                            {{ form.errors.max_workshops }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="max-users"
                            class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                        >
                            Max User
                        </label>
                        <input
                            id="max-users"
                            v-model.number="form.max_users_per_ws"
                            type="number"
                            min="1"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400/40"
                        >
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Batas user untuk setiap bengkel tenant.
                        </p>
                        <p
                            v-if="form.errors.max_users_per_ws"
                            class="text-sm font-medium text-rose-600 dark:text-rose-300"
                        >
                            {{ form.errors.max_users_per_ws }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300"
                    >
                        <input
                            v-model="form.has_ai_feature"
                            type="checkbox"
                            class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                        >
                        AI Feature
                    </label>
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300"
                    >
                        <input
                            v-model="form.has_notification"
                            type="checkbox"
                            class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                        >
                        Notifikasi
                    </label>
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300"
                    >
                        <input
                            v-model="form.has_loyalty"
                            type="checkbox"
                            class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                        >
                        Loyalty
                    </label>
                </div>

                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200"
                    >
                        <input
                            v-model="form.has_trial"
                            type="checkbox"
                            class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                        >
                        Aktifkan Trial
                    </label>

                    <div class="mt-3 space-y-1.5">
                        <label
                            for="trial-duration"
                            class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                        >
                            Durasi Trial (hari)
                        </label>
                        <input
                            id="trial-duration"
                            v-model.number="form.trial_duration_days"
                            type="number"
                            min="1"
                            :disabled="!form.has_trial"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400/40"
                        >
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Gunakan nilai 7, 14, atau 30 hari sesuai strategi onboarding.
                        </p>
                        <p
                            v-if="form.errors.trial_duration_days"
                            class="text-sm font-medium text-rose-600 dark:text-rose-300"
                        >
                            {{ form.errors.trial_duration_days }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <label
                            for="duration-months"
                            class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                        >
                            Durasi (bulan)
                        </label>
                        <input
                            id="duration-months"
                            v-model.number="form.duration_months"
                            type="number"
                            min="1"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400/40"
                        >
                        <p
                            v-if="form.errors.duration_months"
                            class="text-sm font-medium text-rose-600 dark:text-rose-300"
                        >
                            {{ form.errors.duration_months }}
                        </p>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label
                            for="plan-price"
                            class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                        >
                            Harga Plan
                        </label>
                        <CurrencyInput id="plan-price" v-model="form.price" />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Masukkan harga paket dalam Rupiah.
                        </p>
                        <p
                            v-if="form.errors.price"
                            class="text-sm font-medium text-rose-600 dark:text-rose-300"
                        >
                            {{ form.errors.price }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="discount-pct"
                        class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                    >
                        Diskon (%)
                    </label>
                    <input
                        id="discount-pct"
                        v-model.number="form.discount_pct"
                        type="number"
                        min="0"
                        max="100"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-emerald-400/40"
                    >
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Isi 0 jika tidak ada diskon.
                    </p>
                    <p
                        v-if="form.errors.discount_pct"
                        class="text-sm font-medium text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.discount_pct }}
                    </p>
                </div>

                <div class="space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Sinkron Menu Plan
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Pilih menu yang akan tersedia di plan ini.
                            </p>
                        </div>

                        <div class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900">
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center rounded-md px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700 dark:text-slate-300 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300"
                                @click="toggleAllMenus"
                            >
                                {{ selectedMenuCount === normalizedMenuOptions.length ? "Kosongkan Semua" : "Pilih Semua" }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center rounded-md px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700 dark:text-slate-300 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300"
                                :disabled="visibleMenuIds.length === 0"
                                @click="toggleVisibleMenus"
                            >
                                {{ visibleMenuIds.every((menuId) => selectedMenuIdSet.has(menuId)) ? "Kosongkan Terlihat" : "Pilih Terlihat" }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center rounded-md px-2.5 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                :disabled="selectedMenuCount === 0"
                                @click="clearSelectedMenus"
                            >
                                Reset
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <div class="relative mb-3">
                                <input
                                    v-model.trim="menuSearch" data-enter-ignore="true"
                                    type="text"
                                    placeholder="Cari menu atau route..."
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 pr-9 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
                                >
                                <span
                                    class="pointer-events-none absolute inset-y-0 right-3 inline-flex items-center text-slate-400"
                                    aria-hidden="true"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4">
                                        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="1.8" />
                                    </svg>
                                </span>
                            </div>

                            <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
                                <p
                                    v-if="normalizedMenuOptions.length === 0"
                                    class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500 dark:border-slate-600 dark:text-slate-400"
                                >
                                    Belum ada menu system aktif.
                                </p>

                                <p
                                    v-else-if="filteredMenuGroups.length === 0"
                                    class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500 dark:border-slate-600 dark:text-slate-400"
                                >
                                    Menu tidak ditemukan.
                                </p>

                                <details
                                    v-for="menuGroup in filteredMenuGroups"
                                    v-else
                                    :key="`plan-menu-group-${menuGroup.id}`"
                                    open
                                    class="rounded-lg border border-slate-100 bg-slate-50/70 p-2 dark:border-slate-800 dark:bg-slate-800/30"
                                >
                                    <summary class="cursor-pointer list-none rounded-md px-1 py-1 text-sm font-semibold text-slate-700 transition hover:text-emerald-700 dark:text-slate-200 dark:hover:text-emerald-300">
                                        <span class="inline-flex items-center gap-2">
                                            <span>{{ menuGroup.label }}</span>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                                {{ countSelectedMenusInGroup(menuGroup.items) }}/{{ menuGroup.items.length }}
                                            </span>
                                        </span>
                                    </summary>

                                    <div class="mt-2 space-y-1.5">
                                        <label
                                            v-for="menu in menuGroup.items"
                                            :key="`plan-menu-${menu.id}`"
                                            class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-100 px-2.5 py-2 transition hover:border-emerald-200 hover:bg-emerald-50/60 dark:border-slate-800 dark:hover:border-emerald-400/30 dark:hover:bg-emerald-500/10"
                                            :class="menuIndentClass(menu.depth)"
                                        >
                                            <input
                                                type="checkbox"
                                                class="mt-0.5 h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                                                :checked="isMenuSelected(menu.id)"
                                                @change="toggleMenu(menu.id)"
                                            >
                                            <span>
                                                <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                                                    {{ menu.label }}
                                                </span>
                                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                                    {{ menu.route || "-" }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </details>
                            </div>
                        </div>

                        <aside class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    Menu Terpilih
                                </p>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                    {{ selectedMenuCount }}
                                </span>
                            </div>

                            <div class="max-h-56 space-y-1.5 overflow-y-auto pr-1">
                                <p
                                    v-if="selectedMenus.length === 0"
                                    class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-xs text-slate-500 dark:border-slate-600 dark:text-slate-400"
                                >
                                    Belum ada menu yang dipilih.
                                </p>

                                <button
                                    v-for="selectedMenu in selectedMenus"
                                    :key="`selected-menu-${selectedMenu.id}`"
                                    type="button"
                                    class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-left transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-400/40"
                                    @click="toggleMenu(selectedMenu.id)"
                                >
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-slate-700 dark:text-slate-200">
                                            {{ selectedMenu.label }}
                                        </span>
                                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ selectedMenu.route || "-" }}
                                        </span>
                                    </span>
                                    <span class="text-xs font-semibold text-rose-500">Hapus</span>
                                </button>
                            </div>
                        </aside>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Menu yang dipilih akan menjadi scope fitur plan ini.
                    </p>
                    <p
                        v-if="form.errors.menu_ids"
                        class="text-sm font-medium text-rose-600 dark:text-rose-300"
                    >
                        {{ form.errors.menu_ids }}
                    </p>
                </div>

                <label
                    class="inline-flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-600 dark:text-slate-300"
                >
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                    >
                    Plan aktif
                </label>

                <p
                    v-if="errors?.create_plan && !form.errors.create_plan"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.create_plan }}
                </p>
                <p
                    v-if="errors?.update_plan && !form.errors.update_plan"
                    class="text-sm font-medium text-rose-600 dark:text-rose-300"
                >
                    {{ errors.update_plan }}
                </p>

                <button
                    type="submit"
                    class="inline-flex w-full cursor-pointer items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    :disabled="form.processing"
                >
                    {{ form.processing ? "Menyimpan..." : (isEditMode ? "Simpan Perubahan" : "Tambah Plan") }}
                </button>
            </form>
        </div>
    </article>
</template>



