<script setup>
import { resolveMenuIcon } from '../../../Utils/menuIcons';

defineProps({
    flatMenus: {
        type: Array,
        default: () => [],
    },
    flashStatus: {
        type: String,
        default: '',
    },
    dragOverMenuId: {
        type: Number,
        default: null,
    },
    draggedMenuId: {
        type: Number,
        default: null,
    },
    reorderProcessing: {
        type: Boolean,
        default: false,
    },
    deleteProcessing: {
        type: Boolean,
        default: false,
    },
    toggleProcessing: {
        type: Boolean,
        default: false,
    },
    togglingMenuId: {
        type: Number,
        default: null,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    depthPaddingClass: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    'row-drag-start',
    'row-drag-over',
    'row-drop',
    'row-drag-end',
    'edit',
    'delete',
    'toggle-status',
]);
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900 xl:order-2 xl:col-span-3">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Struktur Menu System</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Menu pada tabel ini ditampilkan di sidebar owner/tenant, bukan sidebar superadmin.
                </p>
                <p class="mt-1 text-sm font-medium text-emerald-600 dark:text-emerald-300">
                    Gunakan ikon drag untuk menukar posisi/sort menu se-level (parent yang sama).
                </p>
            </div>
            <span v-if="flashStatus" class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300">
                {{ flashStatus }}
            </span>
        </header>

        <div class="dashboard-scroll overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="w-80 px-5 py-3 text-left text-base font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Menu</th>
                        <th class="min-w-40 px-4 py-3 text-left text-base font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Route</th>
                        <th class="px-4 py-3 text-left text-base font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tipe</th>
                        <th class="px-4 py-3 text-left text-base font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                        <th class="px-4 py-3 text-left text-base font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sort</th>
                        <th class="px-4 py-3 text-right text-base font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="menu in flatMenus"
                        :key="`menu-${menu.id}`"
                        class="border-t border-slate-200 transition-colors dark:border-slate-700"
                        :class="[
                            dragOverMenuId === menu.id ? 'bg-emerald-50/60 dark:bg-emerald-500/10' : '',
                            draggedMenuId === menu.id ? 'opacity-70' : '',
                        ]"
                        @dragover="emit('row-drag-over', menu, $event)"
                        @drop="emit('row-drop', menu, $event)"
                    >
                        <td class="px-5 py-3.5">
                            <div :class="['flex items-center gap-2.5', depthPaddingClass(menu.depth)]">
                                <button
                                    type="button"
                                    class="grid h-6 w-6 shrink-0 cursor-grab place-items-center rounded-md border border-slate-200 bg-white text-slate-400 transition hover:border-emerald-300 hover:text-emerald-600 active:cursor-grabbing dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                                    :disabled="reorderProcessing"
                                    draggable="true"
                                    title="Drag untuk ubah urutan menu"
                                    @dragstart="emit('row-drag-start', menu, $event)"
                                    @dragend="emit('row-drag-end')"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" class="h-3.5 w-3.5" aria-hidden="true">
                                        <path d="M10 6H10.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                        <path d="M14 6H14.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                        <path d="M10 12H10.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                        <path d="M14 12H14.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                        <path d="M10 18H10.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                        <path d="M14 18H14.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                                    </svg>
                                </button>
                                <span
                                    class="grid h-6 w-6 shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    :title="menu.icon"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" class="h-3.5 w-3.5" aria-hidden="true">
                                        <path
                                            v-for="(pathValue, pathIndex) in resolveMenuIcon(menu.icon || 'dashboard')"
                                            :key="`table-menu-icon-${menu.id}-${pathIndex}`"
                                            :d="pathValue"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                                <p class="truncate text-base font-semibold text-slate-700 dark:text-slate-200">{{ menu.label }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-base text-slate-600 dark:text-slate-300">{{ menu.route || '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {{ menu.menu_type }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="menu.is_active
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                        : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                                >
                                    {{ menu.is_active ? 'aktif' : 'nonaktif' }}
                                </span>
                                <label class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    <input
                                        type="checkbox"
                                        class="h-3.5 w-3.5 cursor-pointer rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                                        :checked="menu.is_active"
                                        :disabled="toggleProcessing"
                                        @change="emit('toggle-status', menu)"
                                    >
                                    <span>{{ toggleProcessing && togglingMenuId === menu.id ? '...' : (menu.is_active ? 'ON' : 'OFF') }}</span>
                                </label>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-base leading-6 text-slate-600 dark:text-slate-300">{{ menu.sort_order }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                                    @click="emit('edit', menu)"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                                    :disabled="deleteProcessing"
                                    @click="emit('delete', menu)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="flatMenus.length === 0">
                        <td :colspan="6" class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada menu system pada level platform.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="errors?.delete_menu" class="border-t border-rose-100 px-5 py-3 text-sm font-medium text-rose-600 dark:border-rose-500/20 dark:text-rose-300">
            {{ errors.delete_menu }}
        </p>
        <p v-else-if="errors?.source_id || errors?.target_id || errors?.reorder_menu" class="border-t border-rose-100 px-5 py-3 text-sm font-medium text-rose-600 dark:border-rose-500/20 dark:text-rose-300">
            {{ errors.reorder_menu || errors.source_id || errors.target_id }}
        </p>
        <p v-else-if="errors?.status_menu || errors?.is_active" class="border-t border-rose-100 px-5 py-3 text-sm font-medium text-rose-600 dark:border-rose-500/20 dark:text-rose-300">
            {{ errors.status_menu || errors.is_active }}
        </p>
    </article>
</template>

