<script setup>
import { computed } from 'vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const props = defineProps({
    categories: {
        type: Object,
        default: () => ({
            mode: 'cursor',
            data: [],
            per_page: 10,
            total: 0,
            from: 0,
            to: 0,
            current_cursor: null,
            next_cursor: null,
            prev_cursor: null,
            has_more_pages: false,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    summary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
        }),
    },
    flashStatus: {
        type: String,
        default: '',
    },
    errorMessage: {
        type: String,
        default: '',
    },
    tableLoading: {
        type: Boolean,
        default: false,
    },
    formProcessing: {
        type: Boolean,
        default: false,
    },
    deletingCategoryId: {
        type: String,
        default: null,
    },
    canManage: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['create', 'edit', 'delete', 'search', 'sort', 'per-page', 'page']);

const columns = computed(() => [
    { key: 'name', label: 'Kategori', sortable: true, headerClass: 'w-64' },
    { key: 'description', label: 'Deskripsi', sortable: false, headerClass: 'w-[36rem]' },
    { key: 'is_active', label: 'Status', sortable: true, align: 'center', headerClass: 'w-28' },
    { key: 'created_at', label: 'Dibuat', sortable: true, headerClass: 'w-40' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-44' },
]);

const rows = computed(() => Array.isArray(props.categories?.data) ? props.categories.data : []);

const pagination = computed(() => ({
    mode: String(props.categories?.mode || 'cursor'),
    current_page: Number(props.categories?.current_page) || 1,
    last_page: Number(props.categories?.last_page) || 1,
    per_page: Number(props.categories?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.categories?.total) || 0,
    from: Number(props.categories?.from) || 0,
    to: Number(props.categories?.to) || 0,
    current_cursor: props.categories?.current_cursor ? String(props.categories.current_cursor) : null,
    next_cursor: props.categories?.next_cursor ? String(props.categories.next_cursor) : null,
    prev_cursor: props.categories?.prev_cursor ? String(props.categories.prev_cursor) : null,
    has_more_pages: Boolean(props.categories?.has_more_pages),
}));
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Daftar Kategori Sparepart</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kelola daftar kategori sparepart agar input data lebih rapi.</p>
            </div>

            <div class="flex flex-col items-end gap-2">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                        @click="emit('create')"
                    >
                        Tambah Kategori
                    </button>

                    <span
                        v-if="flashStatus"
                        class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                    >
                        {{ flashStatus }}
                    </span>
                </div>
            </div>

            <div class="flex w-full flex-wrap items-center justify-end gap-2 text-xs font-semibold">
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    Total: {{ summary.total }}
                </span>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300">
                    Aktif: {{ summary.active }}
                </span>
                <span class="rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    Nonaktif: {{ summary.inactive }}
                </span>
            </div>
        </header>

        <div
            v-if="errorMessage"
            class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm font-medium text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300"
        >
            {{ errorMessage }}
        </div>

        <div class="p-4">
            <DataTable
                :columns="columns"
                :rows="rows"
                :pagination="pagination"
                :filters="filters"
                :loading="tableLoading"
                :fixed-layout="true"
                search-placeholder="Cari kategori..."
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #cell-name="{ row }">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        {{ row.name }}
                    </span>
                </template>

                <template #cell-description="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ row.description || '-' }}
                    </span>
                </template>

                <template #cell-is_active="{ row }">
                    <span
                        class="mx-auto inline-flex h-6 items-center rounded-full px-2 text-xs font-semibold leading-none"
                        :class="row.is_active
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                            : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                    >
                        {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ row.created_at ? formatDateIndonesia(row.created_at) : '-' }}
                    </span>
                </template>

                <template #cell-actions="{ row }">
                    <div v-if="canManage" class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                            :disabled="formProcessing"
                            @click="emit('edit', row)"
                        >
                            Edit
                        </button>

                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                            :disabled="formProcessing || deletingCategoryId === String(row.id)"
                            @click="emit('delete', row)"
                        >
                            {{ deletingCategoryId === String(row.id) ? 'Menghapus...' : 'Hapus' }}
                        </button>
                    </div>
                    <span v-else class="text-sm text-slate-400 dark:text-slate-500">-</span>
                </template>
            </DataTable>
        </div>
    </article>
</template>

