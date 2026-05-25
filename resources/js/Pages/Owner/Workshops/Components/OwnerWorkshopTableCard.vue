<script setup>
import { computed } from 'vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const props = defineProps({
    workshops: {
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
    workshopSummary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
            limit: null,
            remaining: null,
        }),
    },
    flashStatus: {
        type: String,
        default: '',
    },
    activeWorkshopId: {
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
    deletingWorkshopId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['create', 'edit', 'delete', 'search', 'sort', 'per-page', 'page']);

const columns = computed(() => [
    { key: 'name', label: 'Bengkel', sortable: true, headerClass: 'w-72' },
    { key: 'code', label: 'Kode', sortable: true, headerClass: 'w-40' },
    { key: 'contact', label: 'Kontak', headerClass: 'w-64' },
    { key: 'is_primary', label: 'Tipe', headerClass: 'w-32' },
    { key: 'is_active', label: 'Status', sortable: true, align: 'center', headerClass: 'w-32' },
    { key: 'created_at', label: 'Dibuat', sortable: true, headerClass: 'w-40' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-48' },
]);

const rows = computed(() => Array.isArray(props.workshops?.data) ? props.workshops.data : []);

const pagination = computed(() => ({
    mode: String(props.workshops?.mode || 'cursor'),
    current_page: Number(props.workshops?.current_page) || 1,
    last_page: Number(props.workshops?.last_page) || 1,
    per_page: Number(props.workshops?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.workshops?.total) || 0,
    from: Number(props.workshops?.from) || 0,
    to: Number(props.workshops?.to) || 0,
    current_cursor: props.workshops?.current_cursor ? String(props.workshops.current_cursor) : null,
    next_cursor: props.workshops?.next_cursor ? String(props.workshops.next_cursor) : null,
    prev_cursor: props.workshops?.prev_cursor ? String(props.workshops.prev_cursor) : null,
    has_more_pages: Boolean(props.workshops?.has_more_pages),
}));

const limitLabel = computed(() => {
    const limit = Number(props.workshopSummary?.limit);
    if (!Number.isFinite(limit) || limit <= 0) {
        return 'Tanpa batas paket';
    }

    const remaining = Number(props.workshopSummary?.remaining);
    if (!Number.isFinite(remaining)) {
        return `Limit paket: ${limit} bengkel`;
    }

    return `Sisa kuota: ${remaining} dari ${limit}`;
});

const isWorkshopLimitReached = computed(() => {
    const limit = Number(props.workshopSummary?.limit);
    if (!Number.isFinite(limit) || limit <= 0) {
        return false;
    }

    const remaining = Number(props.workshopSummary?.remaining);
    if (Number.isFinite(remaining)) {
        return remaining <= 0;
    }

    const total = Number(props.workshopSummary?.total) || 0;
    return total >= limit;
});

const isUsedWorkshop = (row) => {
    const rowId = String(row?.id || '').trim();
    const activeId = String(props.activeWorkshopId || '').trim();

    return rowId !== '' && activeId !== '' && rowId === activeId;
};
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Daftar Bengkel Tenant</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kelola semua bengkel yang dimiliki tenant dalam satu akun owner.</p>
            </div>

            <div class="flex flex-col items-end gap-2">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-1.5 text-sm font-semibold transition active:scale-95"
                        :class="isWorkshopLimitReached
                            ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300 dark:hover:bg-amber-500/25'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25'"
                        @click="emit('create')"
                    >
                        Tambah Bengkel
                    </button>

                    <span
                        v-if="flashStatus"
                        class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                    >
                        {{ flashStatus }}
                    </span>
                </div>

                <p
                    v-if="isWorkshopLimitReached"
                    class="text-xs font-semibold text-amber-700 dark:text-amber-300"
                >
                    Kuota bengkel paket habis. Klik tambah untuk upgrade plan lewat pembayaran.
                </p>
            </div>

            <div class="flex w-full flex-wrap items-center justify-end gap-2 text-xs font-semibold">
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    Total: {{ workshopSummary.total }}
                </span>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300">
                    Aktif: {{ workshopSummary.active }}
                </span>
                <span class="rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    Nonaktif: {{ workshopSummary.inactive }}
                </span>
                <span class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-blue-700 dark:border-blue-400/30 dark:bg-blue-500/15 dark:text-blue-300">
                    {{ limitLabel }}
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
                search-placeholder="Cari bengkel, kode, no. HP, atau alamat..."
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #cell-name="{ row }">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">ID: {{ row.id }}</p>
                    </div>
                </template>

                <template #cell-code="{ row }">
                    <span class="inline-flex h-6 items-center rounded-lg bg-slate-100 px-2 text-xs font-semibold leading-none text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ row.code }}
                    </span>
                </template>

                <template #cell-contact="{ row }">
                    <div class="space-y-1">
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-300">
                            No. HP: {{ row.phone || '-' }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ row.address || '-' }}
                        </p>
                    </div>
                </template>

                <template #cell-is_primary="{ row }">
                    <div class="flex items-center gap-1.5">
                        <span
                            class="inline-flex h-6 items-center rounded-full px-2 text-xs font-semibold leading-none"
                            :class="row.is_primary
                                ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300'
                                : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                        >
                            {{ row.is_primary ? 'Utama' : 'Cabang' }}
                        </span>
                        <span
                            v-if="isUsedWorkshop(row)"
                            class="inline-flex h-6 items-center rounded-full bg-emerald-100 px-2 text-xs font-semibold leading-none text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"
                        >
                            Digunakan
                        </span>
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
                    <div class="flex items-center justify-end gap-2">
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
                            :disabled="formProcessing || row.is_primary || deletingWorkshopId === String(row.id)"
                            @click="emit('delete', row)"
                        >
                            {{ deletingWorkshopId === String(row.id) ? 'Menghapus...' : 'Hapus' }}
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </article>
</template>
