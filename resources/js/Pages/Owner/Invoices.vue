<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import DataTable from '../../Components/UI/DataTable.vue';
import CurrencyInput from '../../Components/UI/CurrencyInput.vue';
import DatePicker from '../../Components/UI/DatePicker.vue';
import { formatRupiah } from '../../Utils/formatCurrency';
import { formatDateIndonesia, formatDateTimeIndonesia } from '../../Utils/indonesiaDate';
import {
    fetchOwnerFinanceRecords,
    markOwnerInvoiceReminder,
    storeOwnerInvoicePayment,
    updateOwnerInvoiceDueDate,
} from './Services/ownerInvoiceService';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    tenantId: {
        type: String,
        default: '',
    },
    package: {
        type: Object,
        default: null,
    },
    menuItems: {
        type: Array,
        default: () => [],
    },
    activeTab: {
        type: String,
        default: 'invoices',
    },
    records: {
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
            status: '',
            method: '',
            state: '',
            sort_by: 'invoice_date',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    summary: {
        type: Object,
        default: () => ({
            invoice_total: 0,
            invoice_paid: 0,
            invoice_unpaid: 0,
            invoice_partial: 0,
            overdue_count: 0,
            due_soon_count: 0,
            total_amount: 0,
            paid_amount: 0,
            remaining_amount: 0,
            payments_total: 0,
            payments_count: 0,
        }),
    },
});

const page = usePage();
const logoutForm = useForm({});
const tableLoading = ref(false);
const reminderProcessingId = ref('');

const paymentForm = useForm({
    paid_at: new Date(),
    amount: null,
    method: 'cash',
    reference_number: '',
    notes: '',
});

const dueDateForm = useForm({
    due_date: new Date(),
});

const selectedInvoice = ref(null);
const isPaymentModalOpen = ref(false);
const isDueDateModalOpen = ref(false);

const tableFilters = ref({
    search: '',
    status: '',
    method: '',
    state: '',
    sort_by: 'invoice_date',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});

watch(
    () => props.filters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            status: String(filters?.status || ''),
            method: String(filters?.method || ''),
            state: String(filters?.state || ''),
            sort_by: String(filters?.sort_by || 'invoice_date'),
            sort_dir: String(filters?.sort_dir || 'desc'),
            per_page: Number(filters?.per_page) || 10,
            cursor: filters?.cursor ? String(filters.cursor) : null,
        };
    },
    {
        immediate: true,
        deep: true,
    },
);

const normalizedActiveTab = computed(() => {
    const allowed = ['invoices', 'payments', 'receivables'];
    return allowed.includes(String(props.activeTab || '')) ? String(props.activeTab) : 'invoices';
});

const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const tabPath = computed(() => {
    if (normalizedActiveTab.value === 'payments') {
        return `${baseOwnerPath.value}/invoice-payments`;
    }

    if (normalizedActiveTab.value === 'receivables') {
        return `${baseOwnerPath.value}/receivables`;
    }

    return `${baseOwnerPath.value}/invoices`;
});

const invoicePaymentPath = (invoiceId) => `${baseOwnerPath.value}/invoices/${invoiceId}/payments`;
const invoiceDueDatePath = (invoiceId) => `${baseOwnerPath.value}/invoices/${invoiceId}/due-date`;
const invoiceReminderPath = (invoiceId) => `${baseOwnerPath.value}/invoices/${invoiceId}/reminder`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));

const canManagePayments = computed(() => (
    permissionNames.value.includes('invoice_payments.manage')
    || permissionNames.value.includes('invoices.manage')
));
const canManageReceivables = computed(() => (
    permissionNames.value.includes('receivables.manage')
    || permissionNames.value.includes('invoices.manage')
));

const invoiceError = computed(() => String(
    paymentForm.errors?.invoice_payment
    || dueDateForm.errors?.invoice_due_date
    || dueDateForm.errors?.invoice_reminder
    || pageErrors.value?.invoice_payment
    || pageErrors.value?.invoice_due_date
    || pageErrors.value?.invoice_reminder
    || pageErrors.value?.invoice
    || '',
));

const rows = computed(() => (Array.isArray(props.records?.data) ? props.records.data : []));
const pagination = computed(() => ({
    mode: String(props.records?.mode || 'cursor'),
    current_page: Number(props.records?.current_page) || 1,
    last_page: Number(props.records?.last_page) || 1,
    per_page: Number(props.records?.per_page) || Number(tableFilters.value?.per_page) || 10,
    total: Number(props.records?.total) || 0,
    from: Number(props.records?.from) || 0,
    to: Number(props.records?.to) || 0,
    current_cursor: props.records?.current_cursor ? String(props.records.current_cursor) : null,
    next_cursor: props.records?.next_cursor ? String(props.records.next_cursor) : null,
    prev_cursor: props.records?.prev_cursor ? String(props.records.prev_cursor) : null,
    has_more_pages: Boolean(props.records?.has_more_pages),
}));

const statusFilterOptions = [
    { value: '', label: 'Semua status' },
    { value: 'unpaid', label: 'Belum Lunas' },
    { value: 'partial', label: 'Sebagian' },
    { value: 'paid', label: 'Lunas' },
];

const paymentMethodOptions = [
    { value: '', label: 'Semua metode' },
    { value: 'cash', label: 'Tunai' },
    { value: 'transfer', label: 'Transfer' },
    { value: 'qris', label: 'QRIS' },
    { value: 'debit', label: 'Debit' },
    { value: 'credit', label: 'Kartu Kredit' },
    { value: 'other', label: 'Lainnya' },
];

const receivableStateOptions = [
    { value: '', label: 'Semua piutang' },
    { value: 'overdue', label: 'Lewat Jatuh Tempo' },
    { value: 'due_soon', label: 'Jatuh Tempo <= 3 Hari' },
];

const columns = computed(() => {
    if (normalizedActiveTab.value === 'payments') {
        return [
            { key: 'paid_at', label: 'Tanggal Bayar', sortable: true, headerClass: 'w-36' },
            { key: 'invoice_code', label: 'Invoice', headerClass: 'w-40' },
            { key: 'method', label: 'Metode', sortable: true, headerClass: 'w-32' },
            { key: 'amount', label: 'Nominal', sortable: true, align: 'right', headerClass: 'w-36' },
            { key: 'reference_number', label: 'Referensi', headerClass: 'w-40' },
            { key: 'workshop_name', label: 'Cabang', headerClass: 'w-44' },
        ];
    }

    if (normalizedActiveTab.value === 'receivables') {
        return [
            { key: 'code', label: 'Invoice', sortable: true, headerClass: 'w-40' },
            { key: 'customer_name', label: 'Pelanggan', sortable: true, headerClass: 'w-56' },
            { key: 'due_date', label: 'Jatuh Tempo', sortable: true, headerClass: 'w-36' },
            { key: 'remaining_amount', label: 'Sisa Piutang', sortable: true, align: 'right', headerClass: 'w-40' },
            { key: 'status', label: 'Status', headerClass: 'w-28' },
            { key: 'reminder_sent_at', label: 'Reminder', headerClass: 'w-36' },
            { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-56' },
        ];
    }

    return [
        { key: 'code', label: 'Invoice', sortable: true, headerClass: 'w-40' },
        { key: 'customer_name', label: 'Pelanggan', headerClass: 'w-56' },
        { key: 'service_order_code', label: 'Service', headerClass: 'w-36' },
        { key: 'invoice_date', label: 'Tanggal', sortable: true, headerClass: 'w-36' },
        { key: 'due_date', label: 'Jatuh Tempo', sortable: true, headerClass: 'w-36' },
        { key: 'status', label: 'Status', sortable: true, headerClass: 'w-28' },
        { key: 'total_amount', label: 'Total', sortable: true, align: 'right', headerClass: 'w-36' },
        { key: 'remaining_amount', label: 'Sisa', sortable: true, align: 'right', headerClass: 'w-36' },
        { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-48' },
    ];
});

const requestTable = (override = {}) => {
    const nextFilters = {
        ...tableFilters.value,
        ...override,
    };

    tableFilters.value = nextFilters;

    fetchOwnerFinanceRecords(tabPath.value, nextFilters, {
        onStart: () => {
            tableLoading.value = true;
        },
        onFinish: () => {
            tableLoading.value = false;
        },
    });
};

const handleSearch = (search) => {
    requestTable({
        search,
        cursor: null,
    });
};

const handleSort = ({ key, direction }) => {
    requestTable({
        sort_by: key,
        sort_dir: direction,
        cursor: null,
    });
};

const handlePerPage = (perPage) => {
    requestTable({
        per_page: perPage,
        cursor: null,
    });
};

const handlePage = (payload) => {
    if (payload && typeof payload === 'object' && payload.type === 'cursor') {
        requestTable({
            cursor: String(payload.cursor || ''),
        });
    }
};

const handleStatusFilter = (value) => {
    requestTable({
        status: String(value || '').trim(),
        cursor: null,
    });
};

const handleMethodFilter = (value) => {
    requestTable({
        method: String(value || '').trim(),
        cursor: null,
    });
};

const handleStateFilter = (value) => {
    requestTable({
        state: String(value || '').trim(),
        cursor: null,
    });
};

const parseDateValue = (value) => {
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? new Date() : parsed;
};

const formatDateForBackend = (value) => {
    const parsedDate = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(parsedDate.getTime())) {
        return '';
    }

    const year = parsedDate.getFullYear();
    const month = String(parsedDate.getMonth() + 1).padStart(2, '0');
    const day = String(parsedDate.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const closePaymentModal = () => {
    isPaymentModalOpen.value = false;
    selectedInvoice.value = null;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentForm.paid_at = new Date();
    paymentForm.amount = null;
    paymentForm.method = 'cash';
    paymentForm.reference_number = '';
    paymentForm.notes = '';
};

const closeDueDateModal = () => {
    isDueDateModalOpen.value = false;
    selectedInvoice.value = null;
    dueDateForm.reset();
    dueDateForm.clearErrors();
    dueDateForm.due_date = new Date();
};

const openPaymentModal = (row) => {
    if (!canManagePayments.value) {
        return;
    }

    const invoiceId = String(row?.id || '').trim();
    if (invoiceId === '') {
        return;
    }

    selectedInvoice.value = row;
    paymentForm.clearErrors();
    paymentForm.paid_at = new Date();
    paymentForm.amount = Number(row?.remaining_amount || 0) > 0 ? Number(row.remaining_amount) : null;
    paymentForm.method = 'cash';
    paymentForm.reference_number = '';
    paymentForm.notes = '';
    isPaymentModalOpen.value = true;
};

const openDueDateModal = (row) => {
    if (!canManageReceivables.value) {
        return;
    }

    const invoiceId = String(row?.id || '').trim();
    if (invoiceId === '') {
        return;
    }

    selectedInvoice.value = row;
    dueDateForm.clearErrors();
    dueDateForm.due_date = parseDateValue(row?.due_date || new Date());
    isDueDateModalOpen.value = true;
};

const submitPayment = () => {
    const invoiceId = String(selectedInvoice.value?.id || '').trim();
    if (invoiceId === '') {
        return;
    }

    storeOwnerInvoicePayment(
        paymentForm.transform((data) => ({
            paid_at: formatDateForBackend(data.paid_at),
            amount: data.amount === null || data.amount === undefined || data.amount === '' ? null : Number(data.amount),
            method: String(data.method || '').trim().toLowerCase(),
            reference_number: String(data.reference_number || '').trim(),
            notes: String(data.notes || '').trim(),
        })),
        invoicePaymentPath(invoiceId),
        {
            onSuccess: () => {
                closePaymentModal();
            },
            onFinish: () => {
                paymentForm.transform((data) => data);
            },
        },
    );
};

const submitDueDate = () => {
    const invoiceId = String(selectedInvoice.value?.id || '').trim();
    if (invoiceId === '') {
        return;
    }

    updateOwnerInvoiceDueDate(
        dueDateForm.transform((data) => ({
            due_date: formatDateForBackend(data.due_date),
        })),
        invoiceDueDatePath(invoiceId),
        {
            onSuccess: () => {
                closeDueDateModal();
            },
            onFinish: () => {
                dueDateForm.transform((data) => data);
            },
        },
    );
};

const markReminder = (row) => {
    if (!canManageReceivables.value) {
        return;
    }

    const invoiceId = String(row?.id || '').trim();
    if (invoiceId === '') {
        return;
    }

    markOwnerInvoiceReminder(invoiceReminderPath(invoiceId), {
        onStart: () => {
            reminderProcessingId.value = invoiceId;
        },
        onFinish: () => {
            reminderProcessingId.value = '';
        },
    });
};

const resolveInvoiceStatusClass = (status) => {
    const normalized = String(status || '').toLowerCase();

    if (normalized === 'paid') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    if (normalized === 'partial') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
    }

    return 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
};

const resolveDueClass = (row) => {
    if (Boolean(row?.is_overdue)) {
        return 'text-rose-600 dark:text-rose-300';
    }

    return 'text-slate-600 dark:text-slate-300';
};

const isReminderProcessing = (invoiceId) => String(reminderProcessingId.value || '') === String(invoiceId || '');

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Keuangan Owner" />

    <AppDashboardLayout
        title="Keuangan"
        subtitle="Invoice, pembayaran, dan piutang terhubung langsung dengan servis selesai"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="`${baseOwnerPath}/invoices`"
                        class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-1.5 text-sm font-semibold"
                        :class="normalizedActiveTab === 'invoices'
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300'
                            : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'"
                    >
                        Invoice
                    </Link>
                    <Link
                        :href="`${baseOwnerPath}/invoice-payments`"
                        class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-1.5 text-sm font-semibold"
                        :class="normalizedActiveTab === 'payments'
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300'
                            : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'"
                    >
                        Pembayaran
                    </Link>
                    <Link
                        :href="`${baseOwnerPath}/receivables`"
                        class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-1.5 text-sm font-semibold"
                        :class="normalizedActiveTab === 'receivables'
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300'
                            : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'"
                    >
                        Piutang
                    </Link>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Invoice</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ summary.invoice_total }}</p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/15">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Total Tagihan</p>
                        <p class="mt-1 text-lg font-semibold text-emerald-700 dark:text-emerald-300">{{ formatRupiah(summary.total_amount) }}</p>
                    </div>
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-400/30 dark:bg-blue-500/15">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Total Pembayaran</p>
                        <p class="mt-1 text-lg font-semibold text-blue-700 dark:text-blue-300">{{ formatRupiah(summary.paid_amount) }}</p>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-400/30 dark:bg-amber-500/15">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Sisa Piutang</p>
                        <p class="mt-1 text-lg font-semibold text-amber-700 dark:text-amber-300">{{ formatRupiah(summary.remaining_amount) }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                <p
                    v-if="flashStatus"
                    class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                >
                    {{ flashStatus }}
                </p>
                <p
                    v-if="invoiceError"
                    class="mb-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/15 dark:text-rose-300"
                >
                    {{ invoiceError }}
                </p>

                <DataTable
                    :columns="columns"
                    :rows="rows"
                    :pagination="pagination"
                    :filters="tableFilters"
                    :loading="tableLoading"
                    :fixed-layout="true"
                    empty-text="Tidak ada data"
                    search-placeholder="Cari invoice, pelanggan, atau referensi..."
                    @update:search="handleSearch"
                    @sort="handleSort"
                    @update:per-page="handlePerPage"
                    @page="handlePage"
                >
                    <template #toolbar-filters>
                        <select
                            v-if="normalizedActiveTab === 'invoices'"
                            :value="tableFilters.status"
                            class="h-10 cursor-pointer rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            @change="handleStatusFilter($event.target.value)"
                        >
                            <option
                                v-for="option in statusFilterOptions"
                                :key="`status-filter-${option.value || 'all'}`"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <select
                            v-if="normalizedActiveTab === 'payments'"
                            :value="tableFilters.method"
                            class="h-10 cursor-pointer rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            @change="handleMethodFilter($event.target.value)"
                        >
                            <option
                                v-for="option in paymentMethodOptions"
                                :key="`method-filter-${option.value || 'all'}`"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <select
                            v-if="normalizedActiveTab === 'receivables'"
                            :value="tableFilters.state"
                            class="h-10 cursor-pointer rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            @change="handleStateFilter($event.target.value)"
                        >
                            <option
                                v-for="option in receivableStateOptions"
                                :key="`state-filter-${option.value || 'all'}`"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </template>

                    <template #cell-code="{ row }">
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.code || '-' }}</p>
                            <p v-if="row.service_order_code" class="text-xs text-slate-500 dark:text-slate-400">SO: {{ row.service_order_code }}</p>
                        </div>
                    </template>

                    <template #cell-customer_name="{ row }">
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.customer_name || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.customer_phone || '-' }}</p>
                        </div>
                    </template>

                    <template #cell-invoice_date="{ row }">
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ row.invoice_date ? formatDateIndonesia(row.invoice_date) : '-' }}</span>
                    </template>

                    <template #cell-due_date="{ row }">
                        <span class="text-sm" :class="resolveDueClass(row)">{{ row.due_date ? formatDateIndonesia(row.due_date) : '-' }}</span>
                    </template>

                    <template #cell-status="{ row }">
                        <span
                            class="inline-flex h-6 items-center rounded-full px-2 text-xs font-semibold"
                            :class="resolveInvoiceStatusClass(row.status)"
                        >
                            {{ row.status_label || '-' }}
                        </span>
                    </template>

                    <template #cell-total_amount="{ row }">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ formatRupiah(row.total_amount || 0) }}</span>
                    </template>

                    <template #cell-remaining_amount="{ row }">
                        <span class="text-sm font-semibold" :class="Boolean(row?.is_overdue) ? 'text-rose-600 dark:text-rose-300' : 'text-slate-700 dark:text-slate-200'">
                            {{ formatRupiah(row.remaining_amount || 0) }}
                        </span>
                    </template>

                    <template #cell-paid_at="{ row }">
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ row.paid_at ? formatDateIndonesia(row.paid_at) : '-' }}</span>
                    </template>

                    <template #cell-method="{ row }">
                        <span class="text-sm text-slate-600 uppercase dark:text-slate-300">{{ row.method || '-' }}</span>
                    </template>

                    <template #cell-amount="{ row }">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ formatRupiah(row.amount || 0) }}</span>
                    </template>

                    <template #cell-reference_number="{ row }">
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ row.reference_number || '-' }}</span>
                    </template>

                    <template #cell-workshop_name="{ row }">
                        <div class="space-y-1">
                            <p class="text-sm text-slate-700 dark:text-slate-200">{{ row.workshop_name || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.workshop_code || '-' }}</p>
                        </div>
                    </template>

                    <template #cell-reminder_sent_at="{ row }">
                        <span class="text-sm text-slate-600 dark:text-slate-300">
                            {{ row.reminder_sent_at ? formatDateTimeIndonesia(row.reminder_sent_at) : 'Belum' }}
                        </span>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-2">
                            <button
                                v-if="normalizedActiveTab !== 'payments' && canManagePayments"
                                type="button"
                                class="inline-flex cursor-pointer items-center rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20"
                                @click="openPaymentModal(row)"
                            >
                                Bayar
                            </button>

                            <button
                                v-if="normalizedActiveTab === 'receivables' && canManageReceivables"
                                type="button"
                                class="inline-flex cursor-pointer items-center rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-sm font-semibold text-amber-700 transition hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
                                @click="openDueDateModal(row)"
                            >
                                Ubah Jatuh Tempo
                            </button>

                            <button
                                v-if="normalizedActiveTab === 'receivables' && canManageReceivables"
                                type="button"
                                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                :disabled="isReminderProcessing(row.id)"
                                @click="markReminder(row)"
                            >
                                {{ isReminderProcessing(row.id) ? 'Memproses...' : 'Tandai Reminder' }}
                            </button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isPaymentModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closePaymentModal"
                />

                <article class="relative z-20 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Input Pembayaran</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Invoice: {{ selectedInvoice?.code || '-' }}</p>

                    <form class="mt-4 space-y-3" @submit.prevent="submitPayment">
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal Bayar</label>
                            <DatePicker v-model="paymentForm.paid_at" placeholder="Pilih tanggal bayar" />
                            <p v-if="paymentForm.errors.paid_at" class="text-xs text-rose-600 dark:text-rose-300">{{ paymentForm.errors.paid_at }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Nominal</label>
                            <CurrencyInput v-model="paymentForm.amount" />
                            <p v-if="paymentForm.errors.amount" class="text-xs text-rose-600 dark:text-rose-300">{{ paymentForm.errors.amount }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Metode</label>
                            <select
                                v-model="paymentForm.method"
                                class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <option
                                    v-for="option in paymentMethodOptions.filter((item) => item.value !== '')"
                                    :key="`payment-method-${option.value}`"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p v-if="paymentForm.errors.method" class="text-xs text-rose-600 dark:text-rose-300">{{ paymentForm.errors.method }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">No Referensi (Opsional)</label>
                            <input
                                v-model="paymentForm.reference_number"
                                type="text"
                                class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                placeholder="Contoh: TRF-001"
                            >
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Catatan (Opsional)</label>
                            <textarea
                                v-model="paymentForm.notes"
                                rows="3"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                placeholder="Catatan pembayaran"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                @click="closePaymentModal"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                                :disabled="paymentForm.processing"
                            >
                                {{ paymentForm.processing ? 'Menyimpan...' : 'Simpan Pembayaran' }}
                            </button>
                        </div>
                    </form>
                </article>
            </div>
        </Transition>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isDueDateModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeDueDateModal"
                />

                <article class="relative z-20 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Ubah Jatuh Tempo</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Invoice: {{ selectedInvoice?.code || '-' }}</p>

                    <form class="mt-4 space-y-3" @submit.prevent="submitDueDate">
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal Jatuh Tempo</label>
                            <DatePicker v-model="dueDateForm.due_date" placeholder="Pilih jatuh tempo" />
                            <p v-if="dueDateForm.errors.due_date" class="text-xs text-rose-600 dark:text-rose-300">{{ dueDateForm.errors.due_date }}</p>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                @click="closeDueDateModal"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                class="inline-flex cursor-pointer items-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 disabled:cursor-not-allowed disabled:opacity-60 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300"
                                :disabled="dueDateForm.processing"
                            >
                                {{ dueDateForm.processing ? 'Menyimpan...' : 'Simpan Jatuh Tempo' }}
                            </button>
                        </div>
                    </form>
                </article>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>

