<script setup>
import { computed } from 'vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const props = defineProps({
    warehouses: {
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
    warehouseSummary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
        }),
    },
    activeWorkshop: {
        type: Object,
        default: () => ({
            id: '',
            name: '',
            code: '',
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
    deletingWarehouseId: {
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
    { key: 'name', label: 'Gudang', sortable: true, headerClass: 'w-80' },
    { key: 'code', label: 'Kode', sortable: true, headerClass: 'w-40' },
    { key: 'is_active', label: 'Status', sortable: true, align: 'center', headerClass: 'w-28' },
    { key: 'created_at', label: 'Dibuat', sortable: true, headerClass: 'w-40' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-44' },
]);

const rows = computed(() => Array.isArray(props.warehouses?.data) ? props.warehouses.data : []);

const pagination = computed(() => ({
    mode: String(props.warehouses?.mode || 'cursor'),
    current_page: Number(props.warehouses?.current_page) || 1,
    last_page: Number(props.warehouses?.last_page) || 1,
    per_page: Number(props.warehouses?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.warehouses?.total) || 0,
    from: Number(props.warehouses?.from) || 0,
    to: Number(props.warehouses?.to) || 0,
    current_cursor: props.warehouses?.current_cursor ? String(props.warehouses.current_cursor) : null,
    next_cursor: props.warehouses?.next_cursor ? String(props.warehouses.next_cursor) : null,
    prev_cursor: props.warehouses?.prev_cursor ? String(props.warehouses.prev_cursor) : null,
    has_more_pages: Boolean(props.warehouses?.has_more_pages),
}));

const activeWorkshopLabel = computed(() => {
    const name = String(props.activeWorkshop?.name || '').trim();
    const code = String(props.activeWorkshop?.code || '').trim();
    if (name === '' && code === '') {
        return '-';
    }

    return [name, code !== '' ? `(${code})` : ''].filter((segment) => segment !== '').join(' ');
});
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Daftar Gudang</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kelola gudang berdasarkan cabang aktif untuk pencatatan stok sparepart.</p>
            </div>

            <div class="flex flex-col items-end gap-2">
                <span class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:border-blue-400/30 dark:bg-blue-500/15 dark:text-blue-300">
                    Filter bengkel: {{ activeWorkshopLabel }}
                </span>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                        @click="emit('create')"
                    >
                        Tambah Gudang
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
                    Total: {{ warehouseSummary.total }}
                </span>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300">
                    Aktif: {{ warehouseSummary.active }}
                </span>
                <span class="rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    Nonaktif: {{ warehouseSummary.inactive }}
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
                search-placeholder="Cari gudang..."
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #cell-name="{ row }">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ row.address || '-' }}
                        </p>
                        <p v-if="row.workshop_name" class="text-[11px] font-medium text-blue-600 dark:text-blue-300">
                            {{ row.workshop_name }}<span v-if="row.workshop_code"> ({{ row.workshop_code }})</span>
                        </p>
                    </div>
                </template>

                <template #cell-code="{ row }">
                    <span class="inline-flex h-6 items-center rounded-lg bg-slate-100 px-2 text-xs font-semibold leading-none text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ row.code || '-' }}
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
                            :disabled="formProcessing || deletingWarehouseId === String(row.id)"
                            @click="emit('delete', row)"
                        >
                            {{ deletingWarehouseId === String(row.id) ? 'Menghapus...' : 'Hapus' }}
                        </button>
                    </div>
                    <span v-else class="text-sm text-slate-400 dark:text-slate-500">-</span>
                </template>
            </DataTable>
        </div>
    </article>
</template>
