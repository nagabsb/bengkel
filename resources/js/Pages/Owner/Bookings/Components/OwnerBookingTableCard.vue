<script setup>
import { computed } from 'vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const props = defineProps({
    bookings: {
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
            status: 'active',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
            workshop_id: '',
        }),
    },
    bookingSummary: {
        type: Object,
        default: () => ({
            total: 0,
            queued: 0,
            in_service: 0,
            completed: 0,
            cancelled: 0,
        }),
    },
    workshopOptions: {
        type: Array,
        default: () => [],
    },
    isGlobalWorkshopFilter: {
        type: Boolean,
        default: false,
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
    statusProcessingBookingId: {
        type: String,
        default: '',
    },
    canManage: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    'create',
    'search',
    'status',
    'workshop',
    'sort',
    'per-page',
    'page',
    'start-service',
]);

const columns = computed(() => [
    { key: 'code', label: 'Kode', sortable: true, headerClass: 'w-36' },
    { key: 'booking_date', label: 'Jadwal', sortable: true, headerClass: 'w-44' },
    { key: 'customer_name', label: 'Pelanggan', sortable: true, headerClass: 'w-56' },
    { key: 'complaint', label: 'Keluhan Awal', headerClass: 'w-80' },
    { key: 'workshop_name', label: 'Cabang', headerClass: 'w-52' },
    { key: 'status', label: 'Status', sortable: true, headerClass: 'w-40' },
    { key: 'created_at', label: 'Dicatat', sortable: true, headerClass: 'w-40' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-44' },
]);

const rows = computed(() => Array.isArray(props.bookings?.data) ? props.bookings.data : []);

const pagination = computed(() => ({
    mode: String(props.bookings?.mode || 'cursor'),
    current_page: Number(props.bookings?.current_page) || 1,
    last_page: Number(props.bookings?.last_page) || 1,
    per_page: Number(props.bookings?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.bookings?.total) || 0,
    from: Number(props.bookings?.from) || 0,
    to: Number(props.bookings?.to) || 0,
    current_cursor: props.bookings?.current_cursor ? String(props.bookings.current_cursor) : null,
    next_cursor: props.bookings?.next_cursor ? String(props.bookings.next_cursor) : null,
    prev_cursor: props.bookings?.prev_cursor ? String(props.bookings.prev_cursor) : null,
    has_more_pages: Boolean(props.bookings?.has_more_pages),
}));

const statusOptions = [
    { value: '', label: 'Semua status' },
    { value: 'active', label: 'Aktif (Antrian + Dikerjakan)' },
    { value: 'queued', label: 'Dalam Antrian' },
    { value: 'in_service', label: 'Sedang Dikerjakan' },
    { value: 'completed', label: 'Selesai' },
    { value: 'cancelled', label: 'Dibatalkan' },
];

const workshopFilterOptions = computed(() => {
    const sourceOptions = Array.isArray(props.workshopOptions) ? props.workshopOptions : [];
    const deduplicatedOptions = [];
    const seen = new Set();

    sourceOptions.forEach((workshopOption) => {
        const value = String(workshopOption?.value || '').trim();
        const label = String(workshopOption?.label || '').trim();
        const subtitle = String(workshopOption?.subtitle || '').trim();
        if (value === '' || label === '') {
            return;
        }

        const key = value.toLowerCase();
        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        deduplicatedOptions.push({
            value,
            label,
            subtitle,
        });
    });

    return deduplicatedOptions;
});

const workshopAsyncOptions = computed(() => ([
    { value: '', label: 'Semua cabang' },
    ...workshopFilterOptions.value.map((workshopOption) => ({
        value: workshopOption.value,
        label: workshopOption.subtitle
            ? `${workshopOption.label} (${workshopOption.subtitle})`
            : workshopOption.label,
    })),
]));

const canUseWorkshopFilter = computed(() => Boolean(props.isGlobalWorkshopFilter) && workshopFilterOptions.value.length > 0);
const selectedWorkshopFilter = computed(() => String(props.filters?.workshop_id || '').trim());
const selectedStatusFilter = computed(() => String(props.filters?.status ?? 'active').trim());

const activeWorkshopLabel = computed(() => {
    const name = String(props.activeWorkshop?.name || '').trim();
    const code = String(props.activeWorkshop?.code || '').trim();
    if (name === '' && code === '') {
        return '-';
    }

    return [name, code !== '' ? `(${code})` : ''].filter((segment) => segment !== '').join(' ');
});

const statusPillClass = (status) => {
    const normalizedStatus = String(status || '').trim();

    if (normalizedStatus === 'in_service') {
        return 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300';
    }

    if (normalizedStatus === 'completed') {
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    if (normalizedStatus === 'cancelled') {
        return 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300';
    }

    return 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300';
};

const statusLabel = (status, fallback = '') => {
    const normalizedStatus = String(status || '').trim();

    if (normalizedStatus === 'in_service') {
        return 'Sedang Dikerjakan';
    }

    if (normalizedStatus === 'completed') {
        return 'Selesai';
    }

    if (normalizedStatus === 'cancelled') {
        return 'Dibatalkan';
    }

    return fallback || 'Dalam Antrian';
};
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="space-y-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Booking & Antrian Servis</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Kelola jadwal booking, nomor antrian, dan status progres kendaraan.
                    </p>
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
                            Tambah Booking
                        </button>

                        <span
                            v-if="flashStatus"
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                        >
                            {{ flashStatus }}
                        </span>
                    </div>
                </div>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-800/40">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Booking</p>
                    <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ Number(bookingSummary?.total || 0) }}</p>
                </article>
                <article class="rounded-xl border border-amber-200 bg-amber-50/70 px-3.5 py-3 shadow-sm dark:border-amber-400/30 dark:bg-amber-500/10">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Dalam Antrian</p>
                    <p class="mt-1 text-xl font-bold text-amber-700 dark:text-amber-300">{{ Number(bookingSummary?.queued || 0) }}</p>
                </article>
                <article class="rounded-xl border border-blue-200 bg-blue-50/70 px-3.5 py-3 shadow-sm dark:border-blue-400/30 dark:bg-blue-500/10">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Sedang Dikerjakan</p>
                    <p class="mt-1 text-xl font-bold text-blue-700 dark:text-blue-300">{{ Number(bookingSummary?.in_service || 0) }}</p>
                </article>
                <article class="rounded-xl border border-emerald-200 bg-emerald-50/70 px-3.5 py-3 shadow-sm dark:border-emerald-400/30 dark:bg-emerald-500/10">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Selesai</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-300">{{ Number(bookingSummary?.completed || 0) }}</p>
                </article>
            </section>
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
                search-placeholder="Cari kode, pelanggan, telepon, keluhan..."
                search-container-class="w-full lg:w-64 xl:w-72"
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #toolbar-filters>
                    <div class="w-44 shrink-0">
                        <AsyncSelect
                            :model-value="selectedStatusFilter"
                            :options="statusOptions"
                            placeholder="Semua status"
                            search-placeholder="Cari status..."
                            empty-text="Status tidak ditemukan."
                            :clearable="false"
                            :trigger-class="'h-10 border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-white focus-visible:ring-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-slate-900 dark:focus-visible:ring-emerald-400/30'"
                            @update:model-value="emit('status', String($event || '').trim())"
                        />
                    </div>

                    <div
                        v-if="canUseWorkshopFilter"
                        class="w-44 shrink-0 xl:w-52"
                    >
                        <AsyncSelect
                            :model-value="selectedWorkshopFilter"
                            :options="workshopAsyncOptions"
                            placeholder="Semua cabang"
                            search-placeholder="Cari cabang..."
                            empty-text="Cabang tidak ditemukan."
                            :clearable="false"
                            :trigger-class="'h-10 border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-white focus-visible:ring-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-slate-900 dark:focus-visible:ring-emerald-400/30'"
                            @update:model-value="emit('workshop', String($event || '').trim())"
                        />
                    </div>
                </template>

                <template #cell-code="{ row }">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            {{ row.code || '-' }}
                        </p>
                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            Antrian #{{ Number(row.queue_number || 0) }}
                        </span>
                    </div>
                </template>

                <template #cell-booking_date="{ row }">
                    <div class="space-y-1">
                        <p class="text-sm text-slate-700 dark:text-slate-200">
                            {{ row.booking_date ? formatDateIndonesia(row.booking_date) : '-' }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ row.booking_time ? `${row.booking_time} WIB` : 'Jam fleksibel' }}
                        </p>
                    </div>
                </template>

                <template #cell-customer_name="{ row }">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                            {{ row.customer_name || '-' }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ row.customer_phone || '-' }}
                        </p>
                        <p v-if="row.customer_vehicle_name" class="text-xs text-slate-500 dark:text-slate-400">
                            {{ row.customer_vehicle_name }}<span v-if="row.customer_vehicle_plate_number"> ({{ row.customer_vehicle_plate_number }})</span>
                        </p>
                    </div>
                </template>

                <template #cell-complaint="{ row }">
                    <div class="space-y-1">
                        <p class="line-clamp-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ row.complaint || '-' }}
                        </p>
                        <p v-if="row.notes" class="line-clamp-1 text-xs text-slate-500 dark:text-slate-400">
                            Catatan: {{ row.notes }}
                        </p>
                    </div>
                </template>

                <template #cell-workshop_name="{ row }">
                    <div class="space-y-0.5">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ row.workshop_name || '-' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.workshop_code || '-' }}</p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="statusPillClass(row.status)"
                    >
                        {{ statusLabel(row.status, row.status_label) }}
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
                            v-if="row.status === 'queued'"
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-700 transition hover:bg-blue-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20"
                            :disabled="formProcessing || statusProcessingBookingId === String(row.id)"
                            @click="emit('start-service', row)"
                        >
                            Mulai
                        </button>

                        <span
                            v-if="row.status !== 'queued'"
                            class="text-xs text-slate-400 dark:text-slate-500"
                        >
                            -
                        </span>
                    </div>
                    <span v-else class="text-sm text-slate-400 dark:text-slate-500">-</span>
                </template>
            </DataTable>
        </div>
    </article>
</template>
