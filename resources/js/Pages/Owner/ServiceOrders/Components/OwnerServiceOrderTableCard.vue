<script setup>
import { computed } from 'vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import { formatRupiah } from '../../../../Utils/formatCurrency';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const props = defineProps({
    orders: {
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
            sort_by: 'service_date',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    orderSummary: {
        type: Object,
        default: () => ({
            total: 0,
            open: 0,
            in_progress: 0,
            done: 0,
            cancelled: 0,
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
    canManage: {
        type: Boolean,
        default: true,
    },
    statusProcessingOrderId: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'create',
    'search',
    'sort',
    'per-page',
    'page',
    'detail-order',
    'print-order',
    'estimate-order',
    'start-order',
    'complete-order',
    'cancel-order',
]);

const columns = computed(() => [
    { key: 'code', label: 'Kode Servis', sortable: true, headerClass: 'w-40' },
    { key: 'customer_name', label: 'Pelanggan', headerClass: 'w-64' },
    { key: 'vehicle_name', label: 'Kendaraan', headerClass: 'w-64' },
    { key: 'odometer', label: 'KM', align: 'right', headerClass: 'w-24' },
    { key: 'estimated_days', label: 'Estimasi', headerClass: 'w-28' },
    { key: 'service_date', label: 'Tanggal', sortable: true, headerClass: 'w-36' },
    { key: 'estimate_summary', label: 'Approval Estimasi', headerClass: 'w-64' },
    { key: 'status', label: 'Status', sortable: true, align: 'center', headerClass: 'w-32' },
    { key: 'created_at', label: 'Dibuat', sortable: true, headerClass: 'w-36' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-56' },
]);

const rows = computed(() => (Array.isArray(props.orders?.data) ? props.orders.data : []));

const pagination = computed(() => ({
    mode: String(props.orders?.mode || 'cursor'),
    current_page: Number(props.orders?.current_page) || 1,
    last_page: Number(props.orders?.last_page) || 1,
    per_page: Number(props.orders?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.orders?.total) || 0,
    from: Number(props.orders?.from) || 0,
    to: Number(props.orders?.to) || 0,
    current_cursor: props.orders?.current_cursor ? String(props.orders.current_cursor) : null,
    next_cursor: props.orders?.next_cursor ? String(props.orders.next_cursor) : null,
    prev_cursor: props.orders?.prev_cursor ? String(props.orders.prev_cursor) : null,
    has_more_pages: Boolean(props.orders?.has_more_pages),
}));

const resolveStatusClass = (status) => {
    const normalizedStatus = String(status || '').toLowerCase();
    if (normalizedStatus === 'done') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    if (normalizedStatus === 'in_progress') {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300';
    }

    if (normalizedStatus === 'cancelled') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
};

const resolveStatusLabel = (status) => {
    const normalizedStatus = String(status || '').toLowerCase();
    if (normalizedStatus === 'done') {
        return 'Selesai';
    }
    if (normalizedStatus === 'in_progress') {
        return 'Proses';
    }
    if (normalizedStatus === 'cancelled') {
        return 'Batal';
    }

    return 'Open';
};

const resolveEstimateStatusClass = (status) => {
    const normalizedStatus = String(status || '').toLowerCase();

    if (normalizedStatus === 'approved') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    if (normalizedStatus === 'pending_approval') {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300';
    }

    if (normalizedStatus === 'rejected') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
    }

    if (normalizedStatus === 'expired') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
    }

    return 'bg-slate-100 text-slate-700 dark:bg-slate-700/40 dark:text-slate-200';
};

const hasApprovedEstimate = (row) => String(row?.latest_estimate?.status || '').toLowerCase() === 'approved';
const isPendingApprovalEstimate = (row) => String(row?.latest_estimate?.status || '').toLowerCase() === 'pending_approval';
const isEstimateLocked = (row) => hasApprovedEstimate(row);

const isStatusProcessing = (orderId) => String(props.statusProcessingOrderId || '') === String(orderId || '');

const normalizeWhatsappPhoneNumber = (value) => {
    const numericOnly = String(value || '').replace(/\D/g, '');
    if (numericOnly === '') {
        return '';
    }

    if (numericOnly.startsWith('62')) {
        return numericOnly;
    }

    if (numericOnly.startsWith('0')) {
        const withoutLeadingZero = numericOnly.slice(1);
        return withoutLeadingZero !== '' ? `62${withoutLeadingZero}` : '';
    }

    if (numericOnly.startsWith('8')) {
        return `62${numericOnly}`;
    }

    return numericOnly;
};

const resolveApprovalLink = (row) => {
    const approvalLink = String(row?.latest_estimate?.approval_link || '').trim();

    return approvalLink !== '' ? approvalLink : '';
};

const resolveFollowUpWhatsappLink = (row) => {
    if (!isPendingApprovalEstimate(row)) {
        return '';
    }

    const phone = normalizeWhatsappPhoneNumber(row?.customer_phone);
    const approvalLink = resolveApprovalLink(row);
    if (phone === '' || approvalLink === '') {
        return '';
    }

    const customerName = String(row?.customer_name || '').trim() || 'Pelanggan';
    const serviceCode = String(row?.code || '').trim();
    const estimateCode = String(row?.latest_estimate?.code || '').trim();

    const message = [
        `Halo Bapak/Ibu ${customerName},`,
        '',
        serviceCode !== '' ? `Tindak lanjut estimasi servis ${serviceCode}.` : 'Tindak lanjut estimasi servis Anda.',
        estimateCode !== '' ? `Kode estimasi: ${estimateCode}.` : null,
        'Silakan buka link approval berikut untuk setujui atau tolak estimasi:',
        approvalLink,
        '',
        'Terima kasih.',
    ]
        .filter((line) => line !== null)
        .join('\n');

    return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
};
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Daftar Servis</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Input servis baru dan gunakan histori pelanggan/kendaraan untuk servis berikutnya.</p>
            </div>

            <div class="flex flex-col items-end gap-2">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                        @click="emit('create')"
                    >
                        Tambah Servis
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
                    Total: {{ orderSummary.total }}
                </span>
                <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300">
                    Open: {{ orderSummary.open }}
                </span>
                <span class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-blue-700 dark:border-blue-400/30 dark:bg-blue-500/15 dark:text-blue-300">
                    Proses: {{ orderSummary.in_progress }}
                </span>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300">
                    Selesai: {{ orderSummary.done }}
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
                search-placeholder="Cari kode servis, pelanggan, atau plat..."
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #cell-code="{ row }">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.code }}</span>
                </template>

                <template #cell-customer_name="{ row }">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.customer_name || '-' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.customer_phone || '-' }}</p>
                        <p v-if="row.workshop_name" class="text-[11px] font-medium text-blue-600 dark:text-blue-300">
                            {{ row.workshop_name }}<span v-if="row.workshop_code"> ({{ row.workshop_code }})</span>
                        </p>
                    </div>
                </template>

                <template #cell-vehicle_name="{ row }">
                    <div class="space-y-1">
                        <p class="text-sm text-slate-700 dark:text-slate-200">{{ row.vehicle_name || '-' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.vehicle_plate_number || '-' }}</p>
                    </div>
                </template>

                <template #cell-service_date="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ row.service_date ? formatDateIndonesia(row.service_date) : '-' }}
                    </span>
                </template>

                <template #cell-odometer="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ row.odometer ?? '-' }}
                    </span>
                </template>

                <template #cell-estimated_days="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ row.estimated_days ? `${row.estimated_days} hari` : '-' }}
                    </span>
                </template>

                <template #cell-status="{ row }">
                    <span
                        class="mx-auto inline-flex h-6 items-center rounded-full px-2 text-xs font-semibold leading-none"
                        :class="resolveStatusClass(row.status)"
                    >
                        {{ resolveStatusLabel(row.status) }}
                    </span>
                </template>

                <template #cell-estimate_summary="{ row }">
                    <div
                        v-if="row.latest_estimate"
                        class="space-y-1"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                {{ row.latest_estimate.code || '-' }}
                            </span>
                            <span
                                class="inline-flex h-6 items-center rounded-full px-2 text-[11px] font-semibold"
                                :class="resolveEstimateStatusClass(row.latest_estimate.status)"
                            >
                                {{ row.latest_estimate.status_label || '-' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300">
                            Total: {{ formatRupiah(row.latest_estimate.total_amount || 0) }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Exp: {{ row.latest_estimate.valid_until ? formatDateIndonesia(row.latest_estimate.valid_until) : '-' }}
                        </p>
                    </div>
                    <p v-else class="text-xs text-slate-500 dark:text-slate-400">
                        Belum ada estimasi.
                    </p>
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ row.created_at ? formatDateIndonesia(row.created_at) : '-' }}
                    </span>
                </template>

                <template #cell-actions="{ row }">
                    <div v-if="canManage" class="flex flex-wrap items-center justify-end gap-1.5">
                        <button
                            v-if="String(row.status || '') === 'done'"
                            type="button"
                            title="Cetak nota"
                            aria-label="Cetak nota"
                            class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-cyan-200 bg-cyan-50 text-cyan-700 transition hover:bg-cyan-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500/40 dark:border-cyan-400/30 dark:bg-cyan-500/15 dark:text-cyan-300 dark:hover:bg-cyan-500/25"
                            @click="emit('print-order', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M6.75 9V5.25A2.25 2.25 0 0 1 9 3h6a2.25 2.25 0 0 1 2.25 2.25V9M6.75 15H5.25A2.25 2.25 0 0 1 3 12.75v-3A2.25 2.25 0 0 1 5.25 7.5h13.5A2.25 2.25 0 0 1 21 9.75v3A2.25 2.25 0 0 1 18.75 15h-1.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M8.25 15h7.5v5.25h-7.5V15Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="17.25" cy="10.5" r=".75" fill="currentColor" />
                            </svg>
                        </button>
                        <button
                            v-if="String(row.status || '') === 'done'"
                            type="button"
                            title="Detail servis"
                            aria-label="Detail servis"
                            class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                            @click="emit('detail-order', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M1.5 12s3.75-7.5 10.5-7.5S22.5 12 22.5 12s-3.75 7.5-10.5 7.5S1.5 12 1.5 12Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" />
                            </svg>
                        </button>
                        <button
                            v-if="String(row.status || '') === 'open' || String(row.status || '') === 'in_progress'"
                            type="button"
                            aria-label="Buka form estimasi"
                            class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-sky-200 bg-sky-50 text-sky-700 transition hover:bg-sky-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/40 disabled:cursor-not-allowed disabled:opacity-60 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-300 dark:hover:bg-sky-500/20"
                            :disabled="isStatusProcessing(row.id) || isEstimateLocked(row)"
                            :title="isEstimateLocked(row) ? 'Estimasi sudah disetujui pelanggan dan tidak bisa diubah lagi.' : 'Buka form estimasi'"
                            @click="emit('estimate-order', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M8.25 3.75h7.5A2.25 2.25 0 0 1 18 6v14.25l-6-3-6 3V6a2.25 2.25 0 0 1 2.25-2.25Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M9.75 8.25h4.5M9.75 11.25h4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                            </svg>
                        </button>

                        <a
                            v-if="isPendingApprovalEstimate(row) && resolveFollowUpWhatsappLink(row)"
                            :href="resolveFollowUpWhatsappLink(row)"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Follow up approval via WhatsApp"
                            class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                            title="Follow up approval estimasi via WhatsApp"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M8.25 18.75 3.75 20.25 5.25 15.75a8.25 8.25 0 1 1 3 3Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M9.75 10.125h.008v.008H9.75v-.008Zm4.5 0h.008v.008h-.008v-.008Zm-4.5 3.75c.75.75 1.8 1.125 2.25 1.125s1.5-.375 2.25-1.125" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>

                        <button
                            v-else-if="isPendingApprovalEstimate(row)"
                            type="button"
                            disabled
                            aria-label="Follow up tidak tersedia"
                            class="inline-flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-500 opacity-80 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-300"
                            title="Nomor WhatsApp pelanggan atau link approval belum tersedia."
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M8.25 18.75 3.75 20.25 5.25 15.75a8.25 8.25 0 1 1 3 3Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                            </svg>
                        </button>

                        <button
                            v-if="String(row.status || '') === 'in_progress'"
                            type="button"
                            title="Selesaikan servis"
                            aria-label="Selesaikan servis"
                            class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                            :disabled="isStatusProcessing(row.id)"
                            @click="emit('complete-order', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="m5.25 12 4.5 4.5 9-9" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <button
                            v-if="String(row.status || '') === 'open'"
                            type="button"
                            aria-label="Mulai servis"
                            class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700 transition hover:bg-blue-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40 disabled:cursor-not-allowed disabled:opacity-60 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20"
                            :disabled="isStatusProcessing(row.id) || !hasApprovedEstimate(row)"
                            :title="hasApprovedEstimate(row) ? 'Mulai servis' : 'Servis hanya bisa dimulai setelah estimasi disetujui pelanggan.'"
                            @click="emit('start-order', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M8.25 6.75v10.5l8.25-5.25-8.25-5.25Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <button
                            v-if="String(row.status || '') === 'open' || String(row.status || '') === 'in_progress'"
                            type="button"
                            title="Batalkan servis"
                            aria-label="Batalkan servis"
                            class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/40 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                            :disabled="isStatusProcessing(row.id)"
                            @click="emit('cancel-order', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="m8.25 8.25 7.5 7.5m0-7.5-7.5 7.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                            </svg>
                        </button>

                        <span
                            v-if="isStatusProcessing(row.id)"
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                        >
                            Proses...
                        </span>
                    </div>
                    <span v-else class="text-sm text-slate-400 dark:text-slate-500">-</span>
                </template>
            </DataTable>
        </div>
    </article>
</template>

