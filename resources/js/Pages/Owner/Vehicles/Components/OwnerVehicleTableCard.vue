<script setup>
import { computed } from 'vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const props = defineProps({
    vehicles: {
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
            vehicle_type: '',
            vehicle_brand: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    vehicleSummary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
            motor: 0,
            mobil: 0,
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
    deletingVehicleId: {
        type: String,
        default: null,
    },
    syncingMaster: {
        type: Boolean,
        default: false,
    },
    canManage: {
        type: Boolean,
        default: true,
    },
    vehicleBrandOptions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'create',
    'edit',
    'delete',
    'sync',
    'search',
    'filter-type',
    'filter-brand',
    'sort',
    'per-page',
    'page',
]);

const columns = computed(() => [
    { key: 'vehicle_type', label: 'Jenis', sortable: true, align: 'center', headerClass: 'w-28' },
    { key: 'brand', label: 'Merek', sortable: true, headerClass: 'w-52' },
    { key: 'model', label: 'Model', sortable: true, headerClass: 'w-52' },
    { key: 'source', label: 'Sumber', sortable: true, headerClass: 'w-36' },
    { key: 'is_active', label: 'Status', sortable: true, align: 'center', headerClass: 'w-28' },
    { key: 'created_at', label: 'Dibuat', sortable: true, headerClass: 'w-40' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-44' },
]);

const rows = computed(() => Array.isArray(props.vehicles?.data) ? props.vehicles.data : []);

const pagination = computed(() => ({
    mode: String(props.vehicles?.mode || 'cursor'),
    current_page: Number(props.vehicles?.current_page) || 1,
    last_page: Number(props.vehicles?.last_page) || 1,
    per_page: Number(props.vehicles?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.vehicles?.total) || 0,
    from: Number(props.vehicles?.from) || 0,
    to: Number(props.vehicles?.to) || 0,
    current_cursor: props.vehicles?.current_cursor ? String(props.vehicles.current_cursor) : null,
    next_cursor: props.vehicles?.next_cursor ? String(props.vehicles.next_cursor) : null,
    prev_cursor: props.vehicles?.prev_cursor ? String(props.vehicles.prev_cursor) : null,
    has_more_pages: Boolean(props.vehicles?.has_more_pages),
}));

const selectedVehicleType = computed(() => String(props.filters?.vehicle_type || '').trim());
const selectedVehicleBrand = computed(() => String(props.filters?.vehicle_brand || '').trim());

const vehicleTypeFilterOptions = computed(() => [
    { value: '', label: 'Semua jenis' },
    { value: 'motor', label: 'Motor' },
    { value: 'mobil', label: 'Mobil' },
]);

const normalizedBrandOptions = computed(() => (
    Array.isArray(props.vehicleBrandOptions)
        ? props.vehicleBrandOptions
            .map((brand) => ({
                value: String(brand || '').trim(),
                label: String(brand || '').trim(),
            }))
            .filter((brand) => brand.value !== '')
        : []
));

const vehicleBrandFilterOptions = computed(() => (
    [
        { value: '', label: 'Semua merek' },
        ...normalizedBrandOptions.value,
    ]
));

const handleVehicleTypeChange = (value) => {
    emit('filter-type', String(value || '').trim());
};

const handleVehicleBrandChange = (value) => {
    emit('filter-brand', String(value || '').trim());
};
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Master Kendaraan Tenant</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kelola merek dan model kendaraan milik tenant ini.</p>
            </div>

            <div class="flex flex-col items-end gap-2">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-emerald-500/15"
                        :disabled="syncingMaster"
                        @click="emit('sync')"
                    >
                        {{ syncingMaster ? 'Sinkron...' : 'Sinkron dari Platform' }}
                    </button>

                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                        @click="emit('create')"
                    >
                        Tambah Kendaraan
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
                    Total: {{ vehicleSummary.total }}
                </span>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300">
                    Aktif: {{ vehicleSummary.active }}
                </span>
                <span class="rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    Nonaktif: {{ vehicleSummary.inactive }}
                </span>
                <span class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/15 dark:text-sky-300">
                    Motor: {{ vehicleSummary.motor }}
                </span>
                <span class="rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-violet-700 dark:border-violet-400/30 dark:bg-violet-500/15 dark:text-violet-300">
                    Mobil: {{ vehicleSummary.mobil }}
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
                search-placeholder="Cari merek/model kendaraan..."
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #toolbar-filters>
                    <div class="flex flex-wrap items-center gap-2">
                        <AsyncSelect
                            :model-value="selectedVehicleType"
                            :options="vehicleTypeFilterOptions"
                            placeholder="Semua jenis"
                            search-placeholder="Cari jenis..."
                            :clearable="false"
                            trigger-class="h-10 min-w-36"
                            menu-width-class="min-w-44"
                            @update:model-value="handleVehicleTypeChange"
                        />

                        <AsyncSelect
                            :model-value="selectedVehicleBrand"
                            :options="vehicleBrandFilterOptions"
                            placeholder="Semua merek"
                            search-placeholder="Cari merek..."
                            :clearable="false"
                            trigger-class="h-10 min-w-44"
                            menu-width-class="min-w-56"
                            @update:model-value="handleVehicleBrandChange"
                        />
                    </div>
                </template>

                <template #cell-vehicle_type="{ row }">
                    <span
                        class="mx-auto inline-flex h-6 items-center rounded-full px-2 text-xs font-semibold leading-none"
                        :class="String(row.vehicle_type || '').toLowerCase() === 'mobil'
                            ? 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300'
                            : 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'"
                    >
                        {{ String(row.vehicle_type || '').toLowerCase() === 'mobil' ? 'Mobil' : 'Motor' }}
                    </span>
                </template>

                <template #cell-brand="{ row }">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.brand || '-' }}</span>
                </template>

                <template #cell-model="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ row.model || '-' }}</span>
                </template>

                <template #cell-source="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ row.source || '-' }}</span>
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
                            :disabled="formProcessing || deletingVehicleId === String(row.id)"
                            @click="emit('delete', row)"
                        >
                            {{ deletingVehicleId === String(row.id) ? 'Menghapus...' : 'Hapus' }}
                        </button>
                    </div>
                    <span v-else class="text-sm text-slate-400 dark:text-slate-500">-</span>
                </template>
            </DataTable>
        </div>
    </article>
</template>
