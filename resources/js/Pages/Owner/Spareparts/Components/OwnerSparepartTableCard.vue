<script setup>
import { computed } from 'vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';
import { formatNumber, formatRupiah } from '../../../../Utils/formatCurrency';

const props = defineProps({
    spareparts: {
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
            supplier_id: null,
            warehouse_id: null,
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    supplierOptions: {
        type: Array,
        default: () => [],
    },
    warehouseOptions: {
        type: Array,
        default: () => [],
    },
    sparePartSummary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
            low_stock: 0,
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
    deletingSparePartId: {
        type: String,
        default: null,
    },
    canManage: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    'create',
    'edit',
    'delete',
    'search',
    'sort',
    'per-page',
    'page',
    'supplier-filter',
    'warehouse-filter',
]);

const columns = computed(() => [
    { key: 'name', label: 'Sparepart', sortable: true, headerClass: 'w-72' },
    { key: 'supplier_name', label: 'Supplier', sortable: false, headerClass: 'w-56' },
    { key: 'selling_price', label: 'Harga Jual', sortable: true, headerClass: 'w-36' },
    { key: 'stock', label: 'Stok', sortable: true, headerClass: 'w-32' },
    { key: 'is_active', label: 'Status', sortable: true, align: 'center', headerClass: 'w-28' },
    { key: 'created_at', label: 'Dibuat', sortable: true, headerClass: 'w-40' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-44' },
]);

const rows = computed(() => Array.isArray(props.spareparts?.data) ? props.spareparts.data : []);

const pagination = computed(() => ({
    mode: String(props.spareparts?.mode || 'cursor'),
    current_page: Number(props.spareparts?.current_page) || 1,
    last_page: Number(props.spareparts?.last_page) || 1,
    per_page: Number(props.spareparts?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.spareparts?.total) || 0,
    from: Number(props.spareparts?.from) || 0,
    to: Number(props.spareparts?.to) || 0,
    current_cursor: props.spareparts?.current_cursor ? String(props.spareparts.current_cursor) : null,
    next_cursor: props.spareparts?.next_cursor ? String(props.spareparts.next_cursor) : null,
    prev_cursor: props.spareparts?.prev_cursor ? String(props.spareparts.prev_cursor) : null,
    has_more_pages: Boolean(props.spareparts?.has_more_pages),
}));

const selectedSupplierId = computed(() => String(props.filters?.supplier_id || ''));
const selectedWarehouseId = computed(() => String(props.filters?.warehouse_id || ''));
const supplierFilterOptions = computed(() => (
    Array.isArray(props.supplierOptions)
        ? props.supplierOptions.map((supplier) => ({
            value: String(supplier?.id || ''),
            label: String(supplier?.name || ''),
        })).filter((supplier) => supplier.value !== '' && supplier.label !== '')
        : []
));
const warehouseFilterOptions = computed(() => (
    Array.isArray(props.warehouseOptions)
        ? props.warehouseOptions.map((warehouse) => ({
            value: String(warehouse?.id || ''),
            label: [
                [String(warehouse?.name || ''), String(warehouse?.code || '').trim() !== '' ? `(${String(warehouse?.code || '').trim()})` : '']
                    .filter((segment) => segment !== '')
                    .join(' '),
                String(warehouse?.workshop_name || '').trim() !== ''
                    ? `- ${String(warehouse?.workshop_name || '').trim()}${String(warehouse?.workshop_code || '').trim() !== '' ? ` (${String(warehouse?.workshop_code || '').trim()})` : ''}`
                    : '',
            ]
                .filter((segment) => segment.trim() !== '')
                .join(' '),
        })).filter((warehouse) => warehouse.value !== '' && warehouse.label !== '')
        : []
));
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Daftar Sparepart</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kelola data sparepart. Supplier boleh dikosongkan.</p>
            </div>

            <div class="flex flex-col items-end gap-2">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                        @click="emit('create')"
                    >
                        Tambah Sparepart
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
                    Total: {{ sparePartSummary.total }}
                </span>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300">
                    Aktif: {{ sparePartSummary.active }}
                </span>
                <span class="rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    Nonaktif: {{ sparePartSummary.inactive }}
                </span>
                <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300">
                    Stok menipis: {{ sparePartSummary.low_stock }}
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
                search-placeholder="Cari sparepart..."
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #toolbar-filters>
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <div class="w-full sm:w-auto">
                            <AsyncSelect
                                :model-value="selectedSupplierId"
                                :options="supplierFilterOptions"
                                placeholder="Semua supplier"
                                search-placeholder="Cari supplier..."
                                empty-text="Supplier tidak ditemukan."
                                trigger-class="h-10 w-full sm:w-[clamp(14rem,30vw,32rem)]"
                                @update:model-value="emit('supplier-filter', String($event || '').trim())"
                            />
                        </div>
                        <div class="w-full sm:w-auto">
                            <AsyncSelect
                                :model-value="selectedWarehouseId"
                                :options="warehouseFilterOptions"
                                placeholder="Semua gudang"
                                search-placeholder="Cari gudang..."
                                empty-text="Gudang tidak ditemukan."
                                trigger-class="h-10 w-full sm:w-[clamp(12rem,24vw,24rem)]"
                                @update:model-value="emit('warehouse-filter', String($event || '').trim())"
                            />
                        </div>
                    </div>
                </template>

                <template #cell-name="{ row }">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ row.sku || '-' }} <span v-if="row.category">- {{ row.category }}</span>
                        </p>
                    </div>
                </template>

                <template #cell-supplier_name="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ row.supplier_name || '-' }}
                    </span>
                </template>

                <template #cell-selling_price="{ row }">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                            {{ formatRupiah(row.selling_price) }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Beli: {{ formatRupiah(row.purchase_price) }}
                        </p>
                    </div>
                </template>

                <template #cell-stock="{ row }">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                            {{ formatNumber(row.stock) }} {{ row.unit || 'pcs' }}
                        </p>
                        <p
                            class="text-xs"
                            :class="Number(row.minimum_stock || 0) > 0 && Number(row.stock || 0) <= Number(row.minimum_stock || 0)
                                ? 'font-semibold text-amber-700 dark:text-amber-300'
                                : 'text-slate-500 dark:text-slate-400'"
                        >
                            Min: {{ formatNumber(row.minimum_stock) }}
                        </p>
                    </div>
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
                            :disabled="formProcessing || deletingSparePartId === String(row.id)"
                            @click="emit('delete', row)"
                        >
                            {{ deletingSparePartId === String(row.id) ? 'Menghapus...' : 'Hapus' }}
                        </button>
                    </div>
                    <span v-else class="text-sm text-slate-400 dark:text-slate-500">-</span>
                </template>
            </DataTable>
        </div>
    </article>
</template>
