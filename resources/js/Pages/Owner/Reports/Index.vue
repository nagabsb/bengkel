<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppDashboardLayout from '../../../Layouts/AppDashboardLayout.vue';
import DataTable from '../../../Components/UI/DataTable.vue';
import { formatRupiah } from '../../../Utils/formatCurrency';
import { formatDateIndonesia, formatDateTimeIndonesia } from '../../../Utils/indonesiaDate';
import {
    fetchOwnerAiMonthlyReport,
    fetchOwnerCustomerReport,
    fetchOwnerExpenseReport,
    fetchOwnerProfitLossReport,
    fetchOwnerSalesReport,
    fetchOwnerSparePartReport,
    normalizeSalesReportFilters,
} from '../Services/ownerReportService';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    tenantId: {
        type: String,
        default: '',
    },
    tenantProfile: {
        type: Object,
        default: () => ({
            name: '',
            phone: '',
            address: '',
        }),
    },
    package: {
        type: Object,
        default: null,
    },
    menuItems: {
        type: Array,
        default: () => [],
    },
    reportType: {
        type: String,
        default: 'sales',
    },
    reportConfig: {
        type: Object,
        default: () => ({
            title: 'Laporan',
            description: '',
        }),
    },
    aiFeatureEnabled: {
        type: Boolean,
        default: true,
    },
    reportSummary: {
        type: Object,
        default: () => ({
            cards: [],
            highlights: [],
        }),
    },
    reportGeneratedAt: {
        type: String,
        default: '',
    },
    printSetting: {
        type: Object,
        default: () => ({
            printer_name: 'Printer Utama',
            print_type: 'thermal',
            paper_size: '80mm',
        }),
    },
    salesReports: {
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
    salesReportFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'service_date',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    expenseReports: {
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
    expenseReportFilters: {
        type: Object,
        default: () => ({
            search: '',
            workshop_id: '__all__',
            category: '',
            sort_by: 'expense_date',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    expenseWorkshopOptions: {
        type: Array,
        default: () => ([{ value: '__all__', label: 'Semua Cabang' }]),
    },
    expenseCategoryOptions: {
        type: Array,
        default: () => ([]),
    },
    customerReports: {
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
    customerReportFilters: {
        type: Object,
        default: () => ({
            search: '',
            workshop_id: '__all__',
            status: 'all',
            contact: 'all',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    customerWorkshopOptions: {
        type: Array,
        default: () => ([{ value: '__all__', label: 'Semua Cabang' }]),
    },
    profitLossReport: {
        type: Object,
        default: () => ({
            period_label: '-',
            scope_label: 'Semua Cabang',
            rows: [],
            summary: {
                total_revenue: 0,
                total_expense: 0,
                gross_profit: 0,
                net_profit: 0,
                net_margin_pct: 0,
                completed_orders: 0,
                avg_ticket: 0,
                service_revenue: 0,
                sparepart_revenue: 0,
                sparepart_cogs: 0,
                operational_expense: 0,
            },
        }),
    },
    profitLossReportFilters: {
        type: Object,
        default: () => ({
            workshop_id: '__all__',
        }),
    },
    profitLossWorkshopOptions: {
        type: Array,
        default: () => ([{ value: '__all__', label: 'Semua Cabang' }]),
    },
    sparePartReports: {
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
    sparePartReportFilters: {
        type: Object,
        default: () => ({
            search: '',
            workshop_id: '__all__',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    sparePartWorkshopOptions: {
        type: Array,
        default: () => ([
            { value: '__all__', label: 'Semua Cabang' },
        ]),
    },
    sparePartReorderInsights: {
        type: Object,
        default: () => ({
            is_available: false,
            scope_label: 'Semua Cabang',
            usage_window_days: 60,
            lead_time_days: 14,
            summary: {
                items_need_reorder: 0,
                critical_items: 0,
                estimated_reorder_cost: 0,
            },
            rows: [],
            disclaimer: 'Prediksi reorder berbasis pemakaian historis dan parameter stok minimum.',
            empty_message: 'Prediksi reorder belum tersedia.',
        }),
    },
    aiMonthlyReports: {
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
    aiMonthlyReportFilters: {
        type: Object,
        default: () => ({
            search: '',
            source: 'all_sources',
            workshop_id: '__all__',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    aiMonthlyReportSources: {
        type: Array,
        default: () => ([
            { value: 'all_sources', label: 'Semua Sumber' },
            { value: 'owner_service_runtime', label: 'Penggunaan Owner' },
            { value: 'platform_prompt_test', label: 'Test Output Superadmin' },
        ]),
    },
    aiMonthlyWorkshopOptions: {
        type: Array,
        default: () => ([
            { value: '__all__', label: 'Semua Cabang' },
        ]),
    },
    aiMonthlyInsights: {
        type: Object,
        default: () => ({
            feature_breakdown: [],
            status_breakdown: [],
            daily_trend: [],
        }),
    },
    aiMonthlyBusinessReport: {
        type: Object,
        default: () => ({
            is_available: false,
            generated_at: '',
            period_label: '-',
            scope_label: 'Semua Cabang',
            total_revenue: 0,
            service_revenue: 0,
            sparepart_revenue: 0,
            gross_profit_estimate: 0,
            total_orders: 0,
            completed_orders: 0,
            new_customers: 0,
            order_completion_text: '0 / 0',
            executive_summary: '',
            highlights: [],
            risks: [],
            recommendations: [],
            next_month_focus: [],
            disclaimer: '',
            empty_message: 'Belum ada output laporan AI bulanan.',
        }),
    },
});

const logoutForm = useForm({});
const tableLoading = ref(false);
const expenseTableLoading = ref(false);
const customerTableLoading = ref(false);
const profitLossTableLoading = ref(false);
const sparePartTableLoading = ref(false);
const aiTableLoading = ref(false);
const isSalesDetailModalOpen = ref(false);
const selectedSalesRow = ref(null);
const autoPrintSalesOrderId = ref('');
const autoPrintReturnTo = ref('');
const shouldAutoPrintSalesNote = ref(false);
const hasAutoPrintSalesNoteTriggered = ref(false);
const hasAutoPrintReturnTriggered = ref(false);
const salesTableFilters = ref({
    search: '',
    sort_by: 'service_date',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});
const expenseTableFilters = ref({
    search: '',
    workshop_id: '__all__',
    category: '',
    sort_by: 'expense_date',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});
const customerTableFilters = ref({
    search: '',
    workshop_id: '__all__',
    status: 'all',
    contact: 'all',
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});
const profitLossTableFilters = ref({
    workshop_id: '__all__',
});
const sparePartTableFilters = ref({
    search: '',
    workshop_id: '__all__',
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});
const aiTableFilters = ref({
    search: '',
    source: 'all_sources',
    workshop_id: '__all__',
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});

const pageTitle = computed(() => String(props.reportConfig?.title || 'Laporan'));
const pageDescription = computed(() => String(props.reportConfig?.description || ''));
const selectedSalesInvoiceDisplay = computed(() => resolveSalesInvoiceForDisplay(selectedSalesRow.value));
const cards = computed(() => (Array.isArray(props.reportSummary?.cards) ? props.reportSummary.cards : []));
const reportHighlights = computed(() => (
    Array.isArray(props.reportSummary?.highlights)
        ? props.reportSummary.highlights
        : []
));
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const salesReportPath = computed(() => `${baseOwnerPath.value}/reports/sales`);
const expenseReportPath = computed(() => `${baseOwnerPath.value}/reports/expenses`);
const customerReportPath = computed(() => `${baseOwnerPath.value}/reports/customers`);
const profitLossReportPath = computed(() => `${baseOwnerPath.value}/reports/profit-loss`);
const sparePartReportPath = computed(() => `${baseOwnerPath.value}/reports/spareparts`);
const aiMonthlyReportPath = computed(() => `${baseOwnerPath.value}/reports/ai-monthly`);
const exportSalesReportPath = computed(() => `${baseOwnerPath.value}/reports/sales/export`);
const isSalesReport = computed(() => String(props.reportType || '').trim() === 'sales');
const isExpenseReport = computed(() => String(props.reportType || '').trim() === 'expenses');
const isCustomerReport = computed(() => String(props.reportType || '').trim() === 'customers');
const isProfitLossReport = computed(() => String(props.reportType || '').trim() === 'profit_loss');
const isSparePartReport = computed(() => String(props.reportType || '').trim() === 'spareparts');
const isAiMonthlyReport = computed(() => String(props.reportType || '').trim() === 'ai_monthly');
const canUseAiFeature = computed(() => Boolean(props.aiFeatureEnabled));
const salesNotePaperSizeMm = computed(() => {
    const normalizedPaperSize = String(props.printSetting?.paper_size || '80mm')
        .trim()
        .toLowerCase();

    return normalizedPaperSize === '58mm' ? 58 : 80;
});
const salesNotePaperSizeLabel = computed(() => `${salesNotePaperSizeMm.value}mm`);
const salesNotePrinterName = computed(() => String(props.printSetting?.printer_name || 'Printer Utama'));
const resolveAutoPrintSalesContext = () => {
    if (typeof window === 'undefined' || typeof URLSearchParams === 'undefined') {
        return {
            enabled: false,
            orderId: '',
            returnTo: '',
        };
    }

    const searchParams = new URLSearchParams(window.location.search || '');
    const autoPrintFlag = String(searchParams.get('auto_print_note') || '').trim().toLowerCase();
    const autoPrintOrderId = String(searchParams.get('auto_print_order_id') || '').trim();
    const autoPrintReturnTo = String(searchParams.get('auto_print_return_to') || '').trim();
    const safeReturnTo = autoPrintReturnTo.startsWith('/') ? autoPrintReturnTo : '';
    const enabled = ['1', 'true', 'yes'].includes(autoPrintFlag) && autoPrintOrderId !== '';

    return {
        enabled,
        orderId: autoPrintOrderId,
        returnTo: safeReturnTo,
    };
};

onMounted(() => {
    const autoPrintContext = resolveAutoPrintSalesContext();
    shouldAutoPrintSalesNote.value = autoPrintContext.enabled;
    autoPrintSalesOrderId.value = autoPrintContext.orderId;
    autoPrintReturnTo.value = autoPrintContext.returnTo;
});
const kpiThemeByKey = {
    total_orders: 'sky',
    completed_orders: 'emerald',
    month_revenue: 'amber',
    avg_ticket: 'rose',
    total_customers: 'sky',
    active_customers: 'emerald',
    new_customers: 'amber',
    customers_with_phone: 'rose',
    pl_total_revenue: 'sky',
    pl_total_expense: 'amber',
    pl_gross_profit: 'emerald',
    pl_net_profit: 'rose',
    ai_total_requests: 'sky',
    ai_success_rate_pct: 'emerald',
    ai_total_tokens: 'amber',
    ai_avg_latency_ms: 'rose',
};
const kpiThemeFallback = ['sky', 'emerald', 'amber', 'rose', 'cyan', 'orange'];
const kpiThemes = {
    sky: {
        card: 'border-sky-200/80 bg-gradient-to-br from-sky-50 via-white to-sky-100/60 dark:border-sky-400/30 dark:bg-gradient-to-br dark:from-sky-500/20 dark:via-slate-900 dark:to-slate-900',
        label: 'text-sky-700 dark:text-sky-300',
        value: 'text-sky-900 dark:text-sky-100',
        badge: 'border-sky-200 bg-sky-100 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/20 dark:text-sky-300',
        meter: 'bg-sky-500 dark:bg-sky-400',
    },
    emerald: {
        card: 'border-emerald-200/80 bg-gradient-to-br from-emerald-50 via-white to-emerald-100/60 dark:border-emerald-400/30 dark:bg-gradient-to-br dark:from-emerald-500/20 dark:via-slate-900 dark:to-slate-900',
        label: 'text-emerald-700 dark:text-emerald-300',
        value: 'text-emerald-900 dark:text-emerald-100',
        badge: 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/20 dark:text-emerald-300',
        meter: 'bg-emerald-500 dark:bg-emerald-400',
    },
    amber: {
        card: 'border-amber-200/80 bg-gradient-to-br from-amber-50 via-white to-amber-100/60 dark:border-amber-400/30 dark:bg-gradient-to-br dark:from-amber-500/20 dark:via-slate-900 dark:to-slate-900',
        label: 'text-amber-700 dark:text-amber-300',
        value: 'text-amber-900 dark:text-amber-100',
        badge: 'border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/20 dark:text-amber-300',
        meter: 'bg-amber-500 dark:bg-amber-400',
    },
    rose: {
        card: 'border-rose-200/80 bg-gradient-to-br from-rose-50 via-white to-rose-100/60 dark:border-rose-400/30 dark:bg-gradient-to-br dark:from-rose-500/20 dark:via-slate-900 dark:to-slate-900',
        label: 'text-rose-700 dark:text-rose-300',
        value: 'text-rose-900 dark:text-rose-100',
        badge: 'border-rose-200 bg-rose-100 text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/20 dark:text-rose-300',
        meter: 'bg-rose-500 dark:bg-rose-400',
    },
    cyan: {
        card: 'border-cyan-200/80 bg-gradient-to-br from-cyan-50 via-white to-cyan-100/60 dark:border-cyan-400/30 dark:bg-gradient-to-br dark:from-cyan-500/20 dark:via-slate-900 dark:to-slate-900',
        label: 'text-cyan-700 dark:text-cyan-300',
        value: 'text-cyan-900 dark:text-cyan-100',
        badge: 'border-cyan-200 bg-cyan-100 text-cyan-700 dark:border-cyan-400/30 dark:bg-cyan-500/20 dark:text-cyan-300',
        meter: 'bg-cyan-500 dark:bg-cyan-400',
    },
    orange: {
        card: 'border-orange-200/80 bg-gradient-to-br from-orange-50 via-white to-orange-100/60 dark:border-orange-400/30 dark:bg-gradient-to-br dark:from-orange-500/20 dark:via-slate-900 dark:to-slate-900',
        label: 'text-orange-700 dark:text-orange-300',
        value: 'text-orange-900 dark:text-orange-100',
        badge: 'border-orange-200 bg-orange-100 text-orange-700 dark:border-orange-400/30 dark:bg-orange-500/20 dark:text-orange-300',
        meter: 'bg-orange-500 dark:bg-orange-400',
    },
};
const maxCardAbsoluteValue = computed(() => {
    const values = cards.value.map((card) => Math.abs(Number(card?.value || 0)));
    const max = values.length > 0 ? Math.max(...values) : 0;

    return max > 0 ? max : 1;
});
const salesRows = computed(() => (Array.isArray(props.salesReports?.data) ? props.salesReports.data : []));
const salesPagination = computed(() => ({
    mode: String(props.salesReports?.mode || 'cursor'),
    current_page: Number(props.salesReports?.current_page) || 1,
    last_page: Number(props.salesReports?.last_page) || 1,
    per_page: Number(props.salesReports?.per_page) || Number(props.salesReportFilters?.per_page) || 10,
    total: Number(props.salesReports?.total) || 0,
    from: Number(props.salesReports?.from) || 0,
    to: Number(props.salesReports?.to) || 0,
    current_cursor: props.salesReports?.current_cursor ? String(props.salesReports.current_cursor) : null,
    next_cursor: props.salesReports?.next_cursor ? String(props.salesReports.next_cursor) : null,
    prev_cursor: props.salesReports?.prev_cursor ? String(props.salesReports.prev_cursor) : null,
    has_more_pages: Boolean(props.salesReports?.has_more_pages),
}));
const salesColumns = computed(() => [
    { key: 'code', label: 'Kode Servis', sortable: true, headerClass: 'w-44' },
    { key: 'service_date', label: 'Tanggal Servis', sortable: true, headerClass: 'w-44' },
    { key: 'customer_name', label: 'Pelanggan', headerClass: 'w-56' },
    { key: 'vehicle_name', label: 'Kendaraan', headerClass: 'w-60' },
    { key: 'plate_number', label: 'Nopol', headerClass: 'w-36' },
    { key: 'status_label', label: 'Status', headerClass: 'w-32' },
    { key: 'total_amount', label: 'Total', sortable: true, align: 'right', headerClass: 'w-40' },
    { key: 'created_at', label: 'Dicatat', sortable: true, headerClass: 'w-44' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-56' },
]);
const expenseRows = computed(() => (Array.isArray(props.expenseReports?.data) ? props.expenseReports.data : []));
const expensePagination = computed(() => ({
    mode: String(props.expenseReports?.mode || 'cursor'),
    current_page: Number(props.expenseReports?.current_page) || 1,
    last_page: Number(props.expenseReports?.last_page) || 1,
    per_page: Number(props.expenseReports?.per_page) || Number(props.expenseReportFilters?.per_page) || 10,
    total: Number(props.expenseReports?.total) || 0,
    from: Number(props.expenseReports?.from) || 0,
    to: Number(props.expenseReports?.to) || 0,
    current_cursor: props.expenseReports?.current_cursor ? String(props.expenseReports.current_cursor) : null,
    next_cursor: props.expenseReports?.next_cursor ? String(props.expenseReports.next_cursor) : null,
    prev_cursor: props.expenseReports?.prev_cursor ? String(props.expenseReports.prev_cursor) : null,
    has_more_pages: Boolean(props.expenseReports?.has_more_pages),
}));
const expenseColumns = computed(() => [
    { key: 'expense_date', label: 'Tanggal', sortable: true, headerClass: 'w-40' },
    { key: 'workshop_name', label: 'Cabang', headerClass: 'w-52' },
    { key: 'category', label: 'Kategori', sortable: true, headerClass: 'w-40' },
    { key: 'description', label: 'Deskripsi', headerClass: 'w-72' },
    { key: 'amount', label: 'Nominal', sortable: true, align: 'right', headerClass: 'w-40' },
    { key: 'created_at', label: 'Dicatat', sortable: true, headerClass: 'w-44' },
]);
const expenseWorkshopOptions = computed(() => (
    Array.isArray(props.expenseWorkshopOptions) && props.expenseWorkshopOptions.length > 0
        ? props.expenseWorkshopOptions
        : [{ value: '__all__', label: 'Semua Cabang' }]
));
const expenseCategoryOptions = computed(() => (
    Array.isArray(props.expenseCategoryOptions)
        ? props.expenseCategoryOptions
            .map((category) => String(category || '').trim())
            .filter((category) => category !== '')
        : []
));
const customerRows = computed(() => (Array.isArray(props.customerReports?.data) ? props.customerReports.data : []));
const customerPagination = computed(() => ({
    mode: String(props.customerReports?.mode || 'cursor'),
    current_page: Number(props.customerReports?.current_page) || 1,
    last_page: Number(props.customerReports?.last_page) || 1,
    per_page: Number(props.customerReports?.per_page) || Number(props.customerReportFilters?.per_page) || 10,
    total: Number(props.customerReports?.total) || 0,
    from: Number(props.customerReports?.from) || 0,
    to: Number(props.customerReports?.to) || 0,
    current_cursor: props.customerReports?.current_cursor ? String(props.customerReports.current_cursor) : null,
    next_cursor: props.customerReports?.next_cursor ? String(props.customerReports.next_cursor) : null,
    prev_cursor: props.customerReports?.prev_cursor ? String(props.customerReports.prev_cursor) : null,
    has_more_pages: Boolean(props.customerReports?.has_more_pages),
}));
const customerColumns = computed(() => [
    { key: 'name', label: 'Pelanggan', sortable: true, headerClass: 'w-56' },
    { key: 'phone', label: 'Telepon', sortable: true, headerClass: 'w-40' },
    { key: 'email', label: 'Email', sortable: true, headerClass: 'w-48' },
    { key: 'workshop_name', label: 'Cabang', headerClass: 'w-44' },
    { key: 'contact_quality', label: 'Kontak', headerClass: 'w-36' },
    { key: 'is_active', label: 'Status', sortable: true, headerClass: 'w-28' },
    { key: 'created_at', label: 'Terdaftar', sortable: true, headerClass: 'w-44' },
]);
const customerWorkshopOptions = computed(() => (
    Array.isArray(props.customerWorkshopOptions) && props.customerWorkshopOptions.length > 0
        ? props.customerWorkshopOptions
        : [{ value: '__all__', label: 'Semua Cabang' }]
));
const customerStatusOptions = [
    { value: 'all', label: 'Semua Status' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];
const customerContactOptions = [
    { value: 'all', label: 'Semua Kontak' },
    { value: 'complete', label: 'Kontak Lengkap' },
    { value: 'phone_only', label: 'Hanya Telepon' },
    { value: 'email_only', label: 'Hanya Email' },
    { value: 'missing', label: 'Belum Lengkap' },
];
const profitLossRows = computed(() => (
    Array.isArray(props.profitLossReport?.rows)
        ? props.profitLossReport.rows
        : []
));
const profitLossSummary = computed(() => (
    props.profitLossReport && typeof props.profitLossReport.summary === 'object'
        ? props.profitLossReport.summary
        : {
            total_revenue: 0,
            total_expense: 0,
            gross_profit: 0,
            net_profit: 0,
            net_margin_pct: 0,
            completed_orders: 0,
            avg_ticket: 0,
            service_revenue: 0,
            sparepart_revenue: 0,
            sparepart_cogs: 0,
            operational_expense: 0,
        }
));
const profitLossPagination = computed(() => ({
    mode: 'offset',
    current_page: 1,
    last_page: 1,
    per_page: Math.max(profitLossRows.value.length, 1),
    total: profitLossRows.value.length,
    from: profitLossRows.value.length > 0 ? 1 : 0,
    to: profitLossRows.value.length,
}));
const profitLossColumns = computed(() => [
    { key: 'group', label: 'Kelompok', headerClass: 'w-32' },
    { key: 'label', label: 'Komponen', headerClass: 'w-56' },
    { key: 'formula', label: 'Sumber Data', headerClass: 'w-72' },
    { key: 'amount', label: 'Nominal', align: 'right', headerClass: 'w-44' },
]);
const profitLossWorkshopOptions = computed(() => (
    Array.isArray(props.profitLossWorkshopOptions) && props.profitLossWorkshopOptions.length > 0
        ? props.profitLossWorkshopOptions
        : [{ value: '__all__', label: 'Semua Cabang' }]
));
const sparePartRows = computed(() => (Array.isArray(props.sparePartReports?.data) ? props.sparePartReports.data : []));
const sparePartPagination = computed(() => ({
    mode: String(props.sparePartReports?.mode || 'cursor'),
    current_page: Number(props.sparePartReports?.current_page) || 1,
    last_page: Number(props.sparePartReports?.last_page) || 1,
    per_page: Number(props.sparePartReports?.per_page) || Number(props.sparePartReportFilters?.per_page) || 10,
    total: Number(props.sparePartReports?.total) || 0,
    from: Number(props.sparePartReports?.from) || 0,
    to: Number(props.sparePartReports?.to) || 0,
    current_cursor: props.sparePartReports?.current_cursor ? String(props.sparePartReports.current_cursor) : null,
    next_cursor: props.sparePartReports?.next_cursor ? String(props.sparePartReports.next_cursor) : null,
    prev_cursor: props.sparePartReports?.prev_cursor ? String(props.sparePartReports.prev_cursor) : null,
    has_more_pages: Boolean(props.sparePartReports?.has_more_pages),
}));
const sparePartColumns = computed(() => [
    { key: 'name', label: 'Nama Sparepart', sortable: true, headerClass: 'w-56' },
    { key: 'sku', label: 'SKU', sortable: true, headerClass: 'w-36' },
    { key: 'category', label: 'Kategori', headerClass: 'w-40' },
    { key: 'stock_total', label: 'Stok', sortable: true, align: 'right', headerClass: 'w-28' },
    { key: 'minimum_stock_total', label: 'Min Stok', sortable: true, align: 'right', headerClass: 'w-28' },
    { key: 'used_qty', label: 'Terpakai', sortable: true, align: 'right', headerClass: 'w-28' },
    { key: 'usage_revenue', label: 'Omzet', sortable: true, align: 'right', headerClass: 'w-40' },
    { key: 'stock_status_rank', label: 'Status Stok', sortable: true, headerClass: 'w-32' },
    { key: 'created_at', label: 'Dicatat', sortable: true, headerClass: 'w-44' },
]);
const sparePartWorkshopOptions = computed(() => (
    Array.isArray(props.sparePartWorkshopOptions) && props.sparePartWorkshopOptions.length > 0
        ? props.sparePartWorkshopOptions
        : [{ value: '__all__', label: 'Semua Cabang' }]
));
const sparePartReorderInsights = computed(() => (
    props.sparePartReorderInsights && typeof props.sparePartReorderInsights === 'object'
        ? props.sparePartReorderInsights
        : null
));
const sparePartReorderSummary = computed(() => (
    sparePartReorderInsights.value && typeof sparePartReorderInsights.value.summary === 'object'
        ? sparePartReorderInsights.value.summary
        : {
            items_need_reorder: 0,
            critical_items: 0,
            estimated_reorder_cost: 0,
        }
));
const sparePartReorderRows = computed(() => (
    Array.isArray(sparePartReorderInsights.value?.rows)
        ? sparePartReorderInsights.value.rows
        : []
));
const aiRows = computed(() => (Array.isArray(props.aiMonthlyReports?.data) ? props.aiMonthlyReports.data : []));
const aiPagination = computed(() => ({
    mode: String(props.aiMonthlyReports?.mode || 'cursor'),
    current_page: Number(props.aiMonthlyReports?.current_page) || 1,
    last_page: Number(props.aiMonthlyReports?.last_page) || 1,
    per_page: Number(props.aiMonthlyReports?.per_page) || Number(props.aiMonthlyReportFilters?.per_page) || 10,
    total: Number(props.aiMonthlyReports?.total) || 0,
    from: Number(props.aiMonthlyReports?.from) || 0,
    to: Number(props.aiMonthlyReports?.to) || 0,
    current_cursor: props.aiMonthlyReports?.current_cursor ? String(props.aiMonthlyReports.current_cursor) : null,
    next_cursor: props.aiMonthlyReports?.next_cursor ? String(props.aiMonthlyReports.next_cursor) : null,
    prev_cursor: props.aiMonthlyReports?.prev_cursor ? String(props.aiMonthlyReports.prev_cursor) : null,
    has_more_pages: Boolean(props.aiMonthlyReports?.has_more_pages),
}));
const aiColumns = computed(() => [
    { key: 'created_at', label: 'Waktu', sortable: true, headerClass: 'w-44' },
    { key: 'service_order_code', label: 'Kode Servis', headerClass: 'w-40' },
    { key: 'source', label: 'Sumber', sortable: true, headerClass: 'w-44' },
    { key: 'feature_key', label: 'Fitur AI', sortable: true, headerClass: 'w-48' },
    { key: 'status', label: 'Status', sortable: true, headerClass: 'w-28' },
    { key: 'total_tokens', label: 'Token', sortable: true, align: 'right', headerClass: 'w-28' },
    { key: 'latency_ms', label: 'Latensi', sortable: true, align: 'right', headerClass: 'w-28' },
    { key: 'generated_by_name', label: 'User', headerClass: 'w-40' },
]);
const aiSourceOptions = computed(() => (
    Array.isArray(props.aiMonthlyReportSources) && props.aiMonthlyReportSources.length > 0
        ? props.aiMonthlyReportSources
        : [
            { value: 'all_sources', label: 'Semua Sumber' },
            { value: 'owner_service_runtime', label: 'Penggunaan Owner' },
            { value: 'platform_prompt_test', label: 'Test Output Superadmin' },
        ]
));
const aiWorkshopOptions = computed(() => (
    Array.isArray(props.aiMonthlyWorkshopOptions) && props.aiMonthlyWorkshopOptions.length > 0
        ? props.aiMonthlyWorkshopOptions
        : [{ value: '__all__', label: 'Semua Cabang' }]
));
const aiBusinessReport = computed(() => (
    props.aiMonthlyBusinessReport && typeof props.aiMonthlyBusinessReport === 'object'
        ? props.aiMonthlyBusinessReport
        : null
));
const hasAiBusinessReport = computed(() => Boolean(
    isAiMonthlyReport.value
    && aiBusinessReport.value
));
const aiBusinessHighlights = computed(() => (
    Array.isArray(aiBusinessReport.value?.highlights)
        ? aiBusinessReport.value.highlights
        : []
));
const aiBusinessRisks = computed(() => (
    Array.isArray(aiBusinessReport.value?.risks)
        ? aiBusinessReport.value.risks
        : []
));
const aiBusinessRecommendations = computed(() => (
    Array.isArray(aiBusinessReport.value?.recommendations)
        ? aiBusinessReport.value.recommendations
        : []
));
const aiBusinessNextMonthFocus = computed(() => (
    Array.isArray(aiBusinessReport.value?.next_month_focus)
        ? aiBusinessReport.value.next_month_focus
        : []
));
const aiFeatureBreakdown = computed(() => (
    Array.isArray(props.aiMonthlyInsights?.feature_breakdown)
        ? props.aiMonthlyInsights.feature_breakdown
        : []
));
const aiStatusBreakdown = computed(() => (
    Array.isArray(props.aiMonthlyInsights?.status_breakdown)
        ? props.aiMonthlyInsights.status_breakdown
        : []
));
const aiDailyTrend = computed(() => (
    Array.isArray(props.aiMonthlyInsights?.daily_trend)
        ? props.aiMonthlyInsights.daily_trend
        : []
));
const aiMaxFeatureTotal = computed(() => {
    const values = aiFeatureBreakdown.value.map((row) => Number(row?.total || 0));
    const max = values.length > 0 ? Math.max(...values) : 0;

    return max > 0 ? max : 1;
});
const aiMaxStatusTotal = computed(() => {
    const values = aiStatusBreakdown.value.map((row) => Number(row?.total || 0));
    const max = values.length > 0 ? Math.max(...values) : 0;

    return max > 0 ? max : 1;
});
const aiMaxTrendTotal = computed(() => {
    const values = aiDailyTrend.value.map((row) => Number(row?.total || 0));
    const max = values.length > 0 ? Math.max(...values) : 0;

    return max > 0 ? max : 1;
});

watch(
    () => props.salesReportFilters,
    (filters) => {
        salesTableFilters.value = {
            search: String(filters?.search || ''),
            sort_by: String(filters?.sort_by || 'service_date'),
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

watch(
    () => props.expenseReportFilters,
    (filters) => {
        expenseTableFilters.value = {
            search: String(filters?.search || ''),
            workshop_id: String(filters?.workshop_id || '__all__'),
            category: String(filters?.category || ''),
            sort_by: String(filters?.sort_by || 'expense_date'),
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

watch(
    () => props.customerReportFilters,
    (filters) => {
        customerTableFilters.value = {
            search: String(filters?.search || ''),
            workshop_id: String(filters?.workshop_id || '__all__'),
            status: String(filters?.status || 'all'),
            contact: String(filters?.contact || 'all'),
            sort_by: String(filters?.sort_by || 'created_at'),
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

watch(
    () => props.profitLossReportFilters,
    (filters) => {
        profitLossTableFilters.value = {
            workshop_id: String(filters?.workshop_id || '__all__'),
        };
    },
    {
        immediate: true,
        deep: true,
    },
);

watch(
    () => props.sparePartReportFilters,
    (filters) => {
        sparePartTableFilters.value = {
            search: String(filters?.search || ''),
            workshop_id: String(filters?.workshop_id || '__all__'),
            sort_by: String(filters?.sort_by || 'created_at'),
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

watch(
    () => props.aiMonthlyReportFilters,
    (filters) => {
        aiTableFilters.value = {
            search: String(filters?.search || ''),
            source: String(filters?.source || 'all_sources'),
            workshop_id: String(filters?.workshop_id || '__all__'),
            sort_by: String(filters?.sort_by || 'created_at'),
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

const formatCardValue = (card) => {
    const value = Number(card?.value || 0);

    if (String(card?.format || '') === 'currency') {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(value);
    }

    return new Intl.NumberFormat('id-ID').format(value);
};

const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));

const resolveKpiTheme = (card, index) => {
    const normalizedKey = String(card?.key || '').trim().toLowerCase();
    const themeKey = kpiThemeByKey[normalizedKey] || kpiThemeFallback[index % kpiThemeFallback.length];

    return kpiThemes[themeKey] || kpiThemes.sky;
};

const resolveKpiFormatLabel = (card) => (
    String(card?.format || '').trim().toLowerCase() === 'currency'
        ? 'Rupiah'
        : 'Jumlah'
);

const resolveKpiMeterWidth = (card) => {
    const absoluteValue = Math.abs(Number(card?.value || 0));
    const ratio = absoluteValue / maxCardAbsoluteValue.value;
    const percent = Math.round(ratio * 100);

    return `${Math.max(20, Math.min(100, percent))}%`;
};

const requestSalesReport = (override = {}) => {
    if (!isSalesReport.value) {
        return;
    }

    const nextFilters = {
        ...salesTableFilters.value,
        ...override,
    };

    salesTableFilters.value = nextFilters;

    fetchOwnerSalesReport(salesReportPath.value, nextFilters, {
        onStart: () => {
            tableLoading.value = true;
        },
        onFinish: () => {
            tableLoading.value = false;
        },
    });
};

const handleSearch = (search) => {
    requestSalesReport({
        search,
        cursor: null,
    });
};

const handleSort = ({ key, direction }) => {
    requestSalesReport({
        sort_by: key,
        sort_dir: direction,
        cursor: null,
    });
};

const handlePerPage = (perPage) => {
    requestSalesReport({
        per_page: perPage,
        cursor: null,
    });
};

const handlePage = (payload) => {
    if (!payload || typeof payload !== 'object' || payload.type !== 'cursor') {
        return;
    }

    requestSalesReport({
        cursor: String(payload.cursor || ''),
    });
};

const requestExpenseReport = (override = {}) => {
    if (!isExpenseReport.value) {
        return;
    }

    const nextFilters = {
        ...expenseTableFilters.value,
        ...override,
    };

    expenseTableFilters.value = nextFilters;

    fetchOwnerExpenseReport(expenseReportPath.value, nextFilters, {
        onStart: () => {
            expenseTableLoading.value = true;
        },
        onFinish: () => {
            expenseTableLoading.value = false;
        },
    });
};

const handleExpenseSearch = (search) => {
    requestExpenseReport({
        search,
        cursor: null,
    });
};

const handleExpenseSort = ({ key, direction }) => {
    requestExpenseReport({
        sort_by: key,
        sort_dir: direction,
        cursor: null,
    });
};

const handleExpensePerPage = (perPage) => {
    requestExpenseReport({
        per_page: perPage,
        cursor: null,
    });
};

const handleExpenseWorkshopChange = (event) => {
    const nextWorkshopId = String(event?.target?.value || '__all__').trim();

    requestExpenseReport({
        workshop_id: nextWorkshopId || '__all__',
        cursor: null,
    });
};

const handleExpenseCategoryChange = (event) => {
    const nextCategory = String(event?.target?.value || '').trim();

    requestExpenseReport({
        category: nextCategory,
        cursor: null,
    });
};

const handleExpensePage = (payload) => {
    if (!payload || typeof payload !== 'object' || payload.type !== 'cursor') {
        return;
    }

    requestExpenseReport({
        cursor: String(payload.cursor || ''),
    });
};

const requestCustomerReport = (override = {}) => {
    if (!isCustomerReport.value) {
        return;
    }

    const nextFilters = {
        ...customerTableFilters.value,
        ...override,
    };

    customerTableFilters.value = nextFilters;

    fetchOwnerCustomerReport(customerReportPath.value, nextFilters, {
        onStart: () => {
            customerTableLoading.value = true;
        },
        onFinish: () => {
            customerTableLoading.value = false;
        },
    });
};

const handleCustomerSearch = (search) => {
    requestCustomerReport({
        search,
        cursor: null,
    });
};

const handleCustomerSort = ({ key, direction }) => {
    requestCustomerReport({
        sort_by: key,
        sort_dir: direction,
        cursor: null,
    });
};

const handleCustomerPerPage = (perPage) => {
    requestCustomerReport({
        per_page: perPage,
        cursor: null,
    });
};

const handleCustomerWorkshopChange = (event) => {
    const nextWorkshopId = String(event?.target?.value || '__all__').trim();

    requestCustomerReport({
        workshop_id: nextWorkshopId || '__all__',
        cursor: null,
    });
};

const handleCustomerStatusChange = (event) => {
    const nextStatus = String(event?.target?.value || 'all').trim();

    requestCustomerReport({
        status: nextStatus || 'all',
        cursor: null,
    });
};

const handleCustomerContactChange = (event) => {
    const nextContact = String(event?.target?.value || 'all').trim();

    requestCustomerReport({
        contact: nextContact || 'all',
        cursor: null,
    });
};

const handleCustomerPage = (payload) => {
    if (!payload || typeof payload !== 'object' || payload.type !== 'cursor') {
        return;
    }

    requestCustomerReport({
        cursor: String(payload.cursor || ''),
    });
};

const requestProfitLossReport = (override = {}) => {
    if (!isProfitLossReport.value) {
        return;
    }

    const nextFilters = {
        ...profitLossTableFilters.value,
        ...override,
    };

    profitLossTableFilters.value = nextFilters;

    fetchOwnerProfitLossReport(profitLossReportPath.value, nextFilters, {
        onStart: () => {
            profitLossTableLoading.value = true;
        },
        onFinish: () => {
            profitLossTableLoading.value = false;
        },
    });
};

const handleProfitLossWorkshopChange = (event) => {
    const nextWorkshopId = String(event?.target?.value || '__all__').trim();

    requestProfitLossReport({
        workshop_id: nextWorkshopId || '__all__',
    });
};

const requestSparePartReport = (override = {}) => {
    if (!isSparePartReport.value) {
        return;
    }

    const nextFilters = {
        ...sparePartTableFilters.value,
        ...override,
    };

    sparePartTableFilters.value = nextFilters;

    fetchOwnerSparePartReport(sparePartReportPath.value, nextFilters, {
        onStart: () => {
            sparePartTableLoading.value = true;
        },
        onFinish: () => {
            sparePartTableLoading.value = false;
        },
    });
};

const handleSparePartSearch = (search) => {
    requestSparePartReport({
        search,
        cursor: null,
    });
};

const handleSparePartSort = ({ key, direction }) => {
    requestSparePartReport({
        sort_by: key,
        sort_dir: direction,
        cursor: null,
    });
};

const handleSparePartPerPage = (perPage) => {
    requestSparePartReport({
        per_page: perPage,
        cursor: null,
    });
};

const handleSparePartWorkshopChange = (event) => {
    const nextWorkshopId = String(event?.target?.value || '__all__').trim();

    requestSparePartReport({
        workshop_id: nextWorkshopId || '__all__',
        cursor: null,
    });
};

const handleSparePartPage = (payload) => {
    if (!payload || typeof payload !== 'object' || payload.type !== 'cursor') {
        return;
    }

    requestSparePartReport({
        cursor: String(payload.cursor || ''),
    });
};

const requestAiMonthlyReport = (override = {}) => {
    if (!isAiMonthlyReport.value) {
        return;
    }

    const nextFilters = {
        ...aiTableFilters.value,
        ...override,
    };

    aiTableFilters.value = nextFilters;

    fetchOwnerAiMonthlyReport(aiMonthlyReportPath.value, nextFilters, {
        onStart: () => {
            aiTableLoading.value = true;
        },
        onFinish: () => {
            aiTableLoading.value = false;
        },
    });
};

const handleAiSearch = (search) => {
    requestAiMonthlyReport({
        search,
        cursor: null,
    });
};

const handleAiSort = ({ key, direction }) => {
    requestAiMonthlyReport({
        sort_by: key,
        sort_dir: direction,
        cursor: null,
    });
};

const handleAiPerPage = (perPage) => {
    requestAiMonthlyReport({
        per_page: perPage,
        cursor: null,
    });
};

const handleAiSourceChange = (event) => {
    const nextSource = String(event?.target?.value || 'all_sources').trim();

    requestAiMonthlyReport({
        source: nextSource || 'all_sources',
        cursor: null,
    });
};

const handleAiWorkshopChange = (event) => {
    const nextWorkshopId = String(event?.target?.value || '__all__').trim();

    requestAiMonthlyReport({
        workshop_id: nextWorkshopId || '__all__',
        cursor: null,
    });
};

const handleAiPage = (payload) => {
    if (!payload || typeof payload !== 'object' || payload.type !== 'cursor') {
        return;
    }

    requestAiMonthlyReport({
        cursor: String(payload.cursor || ''),
    });
};

const resolveSafeText = (value, fallback = '-') => {
    const normalized = String(value ?? '').trim();

    return normalized !== '' ? normalized : fallback;
};

const resolveOptionalText = (value) => {
    const normalized = String(value ?? '').trim();

    return normalized !== '' ? normalized : null;
};

const resolveOptionalCurrency = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const amount = Number(value);
    if (!Number.isFinite(amount)) {
        return null;
    }

    return formatRupiah(amount);
};

const toNonNegativeNumber = (value, fallback = 0) => {
    const normalizedFallback = Number.isFinite(Number(fallback)) ? Number(fallback) : 0;
    const normalized = Number(value);

    if (!Number.isFinite(normalized)) {
        return Math.max(0, normalizedFallback);
    }

    return Math.max(0, normalized);
};

const isCompletedSalesStatus = (row) => {
    if (!row || typeof row !== 'object') {
        return false;
    }

    const normalizedStatus = String(row.status || row.status_label || '').trim().toLowerCase();

    return ['done', 'completed', 'selesai', 'finished'].includes(normalizedStatus)
        || normalizedStatus.includes('selesai');
};

const resolveSalesInvoiceForDisplay = (row) => {
    if (!row || typeof row !== 'object') {
        return null;
    }

    const invoice = row.invoice && typeof row.invoice === 'object' ? row.invoice : null;
    if (!invoice) {
        return null;
    }

    const totalAmount = toNonNegativeNumber(invoice.total_amount ?? row.total_amount ?? 0);
    if (isCompletedSalesStatus(row)) {
        return {
            code: resolveOptionalText(invoice.code) || '-',
            status_label: 'Lunas',
            total_amount: totalAmount,
            paid_amount: totalAmount,
            remaining_amount: 0,
        };
    }

    return {
        code: resolveOptionalText(invoice.code) || '-',
        status_label: resolveOptionalText(invoice.status_label) || 'Belum Lunas',
        total_amount: totalAmount,
        paid_amount: toNonNegativeNumber(invoice.paid_amount),
        remaining_amount: toNonNegativeNumber(invoice.remaining_amount),
    };
};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const resolvePrintableDate = (value) => {
    if (!value) {
        return '-';
    }

    return formatDateIndonesia(value, { dateStyle: 'medium' });
};

const resolvePrintableDateTime = (value) => {
    if (!value) {
        return '-';
    }

    return formatDateTimeIndonesia(value, { dateStyle: 'medium', timeStyle: 'short' });
};

const resolveFeatureMeterWidth = (value) => {
    const ratio = Number(value || 0) / aiMaxFeatureTotal.value;
    const percent = Math.round(ratio * 100);

    return `${Math.max(10, Math.min(100, percent))}%`;
};

const resolveStatusMeterWidth = (value) => {
    const ratio = Number(value || 0) / aiMaxStatusTotal.value;
    const percent = Math.round(ratio * 100);

    return `${Math.max(10, Math.min(100, percent))}%`;
};

const resolveTrendBarHeight = (value) => {
    const ratio = Number(value || 0) / aiMaxTrendTotal.value;
    const percent = Math.round(ratio * 100);

    return `${Math.max(8, Math.min(100, percent))}%`;
};

const resolveAiStatusBadgeClass = (status) => {
    const normalized = String(status || '').trim().toLowerCase();

    if (normalized === 'success') {
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    if (normalized === 'failed') {
        return 'bg-rose-50 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
    }

    return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200';
};

const resolveSparePartStatusBadgeClass = (status) => {
    const normalized = String(status || '').trim().toLowerCase();

    if (normalized === 'healthy') {
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    if (normalized === 'low') {
        return 'bg-amber-50 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
    }

    if (normalized === 'inactive') {
        return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200';
    }

    return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200';
};

const resolveCustomerStatusBadgeClass = (isActive) => (
    Boolean(isActive)
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
        : 'bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-200'
);

const resolveCustomerContactBadgeClass = (contactQuality) => {
    const normalized = String(contactQuality || '').trim().toLowerCase();

    if (normalized === 'complete') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    if (normalized === 'missing') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
};

const resolveProfitLossGroupBadgeClass = (group) => {
    const normalized = String(group || '').trim().toLowerCase();

    if (normalized === 'pendapatan') {
        return 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300';
    }

    if (normalized === 'beban') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
    }

    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
};

const resolveProfitLossAmountClass = (value) => (
    Number(value || 0) < 0
        ? 'text-rose-600 dark:text-rose-300'
        : 'text-slate-800 dark:text-slate-100'
);

const resolveReorderPriorityBadgeClass = (priority) => {
    const normalized = String(priority || '').trim().toLowerCase();

    if (normalized === 'critical') {
        return 'bg-rose-50 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
    }

    if (normalized === 'high') {
        return 'bg-amber-50 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
    }

    return 'bg-sky-50 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300';
};

const openPrintWindow = ({ title, subtitle = '', bodyHtml = '', page = {} }) => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const normalizedPage = page && typeof page === 'object' ? page : {};
    const pageSize = String(normalizedPage.size || 'A4 portrait');
    const pageMargin = String(normalizedPage.margin || '12mm');
    const mainMaxWidth = String(normalizedPage.maxWidth || '920px');
    const bodyFontFamily = String(normalizedPage.fontFamily || "'Segoe UI', Arial, sans-serif");
    const bodyFontSize = String(normalizedPage.fontSize || '14px');
    const isThermal = Boolean(normalizedPage.isThermal);
    const titleFontSize = isThermal ? '16px' : '22px';
    const subtitleFontSize = isThermal ? '11px' : '13px';
    const sectionMarginTop = isThermal ? '10px' : '18px';
    const sectionBorderRadius = isThermal ? '6px' : '10px';
    const sectionPadding = isThermal ? '8px' : '14px';
    const sectionHeaderFontSize = isThermal ? '12px' : '14px';
    const cellPadding = isThermal ? '4px 6px' : '8px 10px';
    const cellFontSize = isThermal ? '10px' : '12px';
    const noteFontSize = isThermal ? '10px' : '12px';
    const titleLetterSpacing = isThermal ? '0.3px' : '0';
    const subtitleMarginTop = isThermal ? '2px' : '4px';
    const subtitleColor = isThermal ? '#111827' : '#475569';
    const sectionBorder = isThermal ? '1px dashed #1f2937' : '1px solid #dbe5e1';
    const sectionBackground = isThermal ? '#ffffff' : '#ffffff';
    const subtitleText = String(subtitle || '').trim();
    const subtitleHtml = subtitleText !== '' ? `<p class="sub">${escapeHtml(subtitleText)}</p>` : '';

    const printMarkup = `<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>${escapeHtml(title)}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ${bodyFontFamily}; font-size: ${bodyFontSize}; color: #0f172a; background: #ffffff; }
        main { max-width: ${mainMaxWidth}; margin: 0 auto; padding: 28px 24px; }
        h1 { margin: 0; font-size: ${titleFontSize}; letter-spacing: ${titleLetterSpacing}; color: #0b3d2e; }
        .sub { margin-top: ${subtitleMarginTop}; font-size: ${subtitleFontSize}; color: ${subtitleColor}; white-space: pre-line; }
        .section { margin-top: ${sectionMarginTop}; border: ${sectionBorder}; border-radius: ${sectionBorderRadius}; background: ${sectionBackground}; padding: ${sectionPadding}; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: ${cellPadding}; font-size: ${cellFontSize}; text-align: left; vertical-align: top; }
        th { background: #f8fafc; font-weight: 700; color: #334155; }
        h3 { font-size: ${sectionHeaderFontSize}; }
        p { font-size: ${noteFontSize}; }
        .note-lines { margin: 0; padding: 0; list-style: none; }
        .note-line { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; padding: 4px 0; border-bottom: 1px dashed #e2e8f0; }
        .note-line:last-child { border-bottom: 0; padding-bottom: 0; }
        .note-label { color: #475569; min-width: 82px; }
        .note-value { color: #0f172a; font-weight: 600; text-align: right; word-break: break-word; }
        .note-title { margin: 0 0 8px; font-size: ${sectionHeaderFontSize}; color: #0f172a; text-transform: uppercase; letter-spacing: 0.35px; }
        .note-text { margin: 0; color: #0f172a; white-space: pre-wrap; word-break: break-word; }
        .note-text + .note-text { margin-top: 6px; }
        .note-footer { margin-top: 10px; text-align: center; color: ${isThermal ? '#0f172a' : '#64748b'}; font-size: max(8px, ${isThermal ? '8.5px' : '10px'}); }
        .thermal-mode img { display: none !important; }
        .thermal-mode main { padding: 8px 8px 10px; }
        .thermal-mode h1 { text-align: center; color: #111827; font-size: 14px; letter-spacing: 0.45px; text-transform: uppercase; }
        .thermal-mode .sub { text-align: center; color: #111827; font-size: 9.5px; margin-top: 2px; line-height: 1.4; }
        .receipt-root { margin-top: 8px; }
        .receipt-rule { border-top: 1px dashed #111827; margin: 7px 0; height: 0; }
        .receipt-rule.strong { border-top-style: solid; }
        .receipt-center { text-align: center; }
        .receipt-order-code { margin: 0; font-size: 10px; letter-spacing: 0.45px; text-transform: uppercase; text-align: center; }
        .receipt-meta-line { margin: 0; display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; font-size: 10px; color: #0f172a; }
        .receipt-section-title { margin: 0 0 4px; text-align: center; font-size: 10px; letter-spacing: 0.65px; text-transform: uppercase; font-weight: 700; color: #0f172a; }
        .receipt-kv-list { margin: 0; padding: 0; list-style: none; }
        .receipt-kv-row { display: grid; grid-template-columns: minmax(84px, 1fr) auto minmax(0, 1fr); gap: 4px; align-items: flex-start; padding: 2px 0; border-bottom: 1px dotted #1f2937; }
        .receipt-kv-row:last-child { border-bottom: 0; }
        .receipt-kv-label,
        .receipt-kv-colon,
        .receipt-kv-value { font-size: 10px; line-height: 1.35; min-height: 8px; }
        .receipt-kv-label,
        .receipt-kv-colon { color: #0f172a; }
        .receipt-kv-value { color: #0f172a; text-align: right; font-weight: 700; word-break: break-word; }
        .receipt-kv-value--left { text-align: left; font-weight: 500; }
        .receipt-highlight { margin-top: 6px; border: 1px solid #111827; padding: 4px 6px; display: flex; justify-content: space-between; gap: 8px; font-size: 11px; font-weight: 700; color: #0f172a; background: #ffffff; }
        .receipt-note { margin: 0; font-size: 10px; line-height: 1.42; color: #0f172a; white-space: pre-wrap; word-break: break-word; min-height: 8px; }
        .receipt-note + .receipt-note { margin-top: 4px; }
        .receipt-items { margin: 0; padding: 0; list-style: none; }
        .receipt-item-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; padding: 3px 0; border-bottom: 1px dotted #1f2937; }
        .receipt-item-row:last-child { border-bottom: 0; }
        .receipt-item-main { min-width: 0; }
        .receipt-item-label { margin: 0; font-size: 10px; color: #0f172a; }
        .receipt-item-meta { margin: 1px 0 0; font-size: 9px; color: #334155; white-space: pre-wrap; }
        .receipt-item-amount { margin: 0; font-size: 10px; font-weight: 700; color: #0f172a; white-space: nowrap; text-align: right; }
        .text-right { text-align: right; }
        .muted { color: #64748b; }
        @media print {
            @page { size: ${pageSize}; margin: ${pageMargin}; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            main { padding: 0; }
        }
    </style>
</head>
<body class="${isThermal ? 'thermal-mode' : ''}">
    <main>
        <h1>${escapeHtml(title)}</h1>
        ${subtitleHtml}
        ${bodyHtml}
    </main>
</body>
</html>`;
    const popupAutoScript = `
<script>
(function () {
    if (window.__ownerReportAutoPrintInitialized) {
        return;
    }

    window.__ownerReportAutoPrintInitialized = true;

    var closeWindow = function () {
        try {
            window.close();
        } catch (_error) {
            // noop
        }
    };

    var triggerPrint = function () {
        if (window.__ownerReportAutoPrintTriggered) {
            return;
        }

        window.__ownerReportAutoPrintTriggered = true;

        try {
            window.focus();
        } catch (_error) {
            // noop
        }

        try {
            window.print();
        } catch (_error) {
            // noop
        }

        window.setTimeout(closeWindow, 1000);
    };

    window.addEventListener('afterprint', function () {
        window.setTimeout(closeWindow, 120);
    }, { once: true });

    if (document.readyState === 'complete') {
        window.setTimeout(triggerPrint, 120);
    } else {
        window.addEventListener('load', function () {
            window.setTimeout(triggerPrint, 120);
        }, { once: true });
    }

    window.setTimeout(triggerPrint, 700);
    window.setTimeout(closeWindow, 30000);
})();
</scr` + `ipt>`;
    const popupPrintMarkup = printMarkup.replace('</body>', `${popupAutoScript}</body>`);

    const popupWindow = window.open('', '_blank', 'width=920,height=760');
    if (popupWindow && typeof popupWindow.document !== 'undefined') {
        let hasTriggeredPopupPrint = false;
        const closePopupWindow = () => {
            if (!popupWindow.closed) {
                popupWindow.close();
            }
        };

        const triggerPopupPrint = () => {
            if (
                hasTriggeredPopupPrint
                || popupWindow.closed
                || popupWindow.__ownerReportAutoPrintTriggered === true
            ) {
                return;
            }

            hasTriggeredPopupPrint = true;
            popupWindow.focus();
            popupWindow.print();
            setTimeout(closePopupWindow, 1000);
        };

        const handlePopupAfterPrint = () => {
            popupWindow.removeEventListener('afterprint', handlePopupAfterPrint);
            setTimeout(closePopupWindow, 120);
        };

        try {
            popupWindow.addEventListener('afterprint', handlePopupAfterPrint);
            popupWindow.document.open();
            popupWindow.document.write(popupPrintMarkup);
            popupWindow.document.close();

            setTimeout(triggerPopupPrint, 1600);
            setTimeout(closePopupWindow, 32000);
            return;
        } catch (_error) {
            closePopupWindow();
        }
    }

    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.opacity = '0';
    iframe.style.pointerEvents = 'none';

    const cleanup = () => {
        if (iframe.parentNode) {
            iframe.parentNode.removeChild(iframe);
        }
    };

    let hasTriggeredPrint = false;
    const triggerPrint = () => {
        if (hasTriggeredPrint) {
            return;
        }

        hasTriggeredPrint = true;
        const frameWindow = iframe.contentWindow;
        if (!frameWindow) {
            cleanup();
            return;
        }

        const handleAfterPrint = () => {
            frameWindow.removeEventListener('afterprint', handleAfterPrint);
            cleanup();
        };

        frameWindow.addEventListener('afterprint', handleAfterPrint);
        setTimeout(() => {
            frameWindow.focus();
            frameWindow.print();
        }, 120);

        setTimeout(cleanup, 15000);
    };

    iframe.onload = triggerPrint;
    document.body.appendChild(iframe);

    const frameDocument = iframe.contentDocument || iframe.contentWindow?.document;
    if (!frameDocument) {
        cleanup();
        return;
    }

    frameDocument.open();
    frameDocument.write(printMarkup);
    frameDocument.close();

    setTimeout(triggerPrint, 300);
};

const openSalesDetail = (row) => {
    selectedSalesRow.value = row && typeof row === 'object' ? row : null;
    isSalesDetailModalOpen.value = selectedSalesRow.value !== null;
};

const closeSalesDetail = () => {
    isSalesDetailModalOpen.value = false;
    selectedSalesRow.value = null;
};

const printSalesNote = (row) => {
    if (!row || typeof row !== 'object') {
        return;
    }

    const invoice = row.invoice && typeof row.invoice === 'object' ? row.invoice : null;
    const printedAtText = resolvePrintableDateTime(new Date());
    const printerNameText = resolveOptionalText(salesNotePrinterName.value);
    const paperSizeText = resolveOptionalText(salesNotePaperSizeLabel.value);
    const orderCode = resolveOptionalText(row.code);
    const serviceDateText = resolvePrintableDate(row.service_date);
    const workshopNameText = resolveOptionalText(row.workshop_name)
        || resolveOptionalText(props.tenantProfile?.name)
        || 'BENGKEL';
    const workshopPhoneText = resolveOptionalText(row.workshop_phone);
    const workshopAddressText = resolveOptionalText(row.workshop_address);
    const customerNameText = resolveSafeText(row.customer_name, '-');
    const customerPhoneText = resolveSafeText(row.customer_phone, '-');
    const complaintText = resolveOptionalText(row.complaint) || '-';
    const completionNotesText = resolveOptionalText(row.completion_notes);

    const normalizeAmount = (value, fallback = 0) => {
        const normalizedFallback = Number.isFinite(Number(fallback)) ? Number(fallback) : 0;
        const normalized = Number(value);
        if (!Number.isFinite(normalized)) {
            return Math.max(0, Math.round(normalizedFallback));
        }

        return Math.max(0, Math.round(normalized));
    };

    const buildReceiptRow = (label, value, align = 'right') => {
        const normalizedValue = resolveOptionalText(value);
        if (!normalizedValue && normalizedValue !== '-') {
            return '';
        }

        const valueClass = align === 'left'
            ? 'receipt-kv-value receipt-kv-value--left'
            : 'receipt-kv-value';

        return `
            <li class="receipt-kv-row">
                <span class="receipt-kv-label">${escapeHtml(label)}</span>
                <span class="receipt-kv-colon">:</span>
                <span class="${valueClass}">${escapeHtml(normalizedValue || '-')}</span>
            </li>
        `;
    };

    const normalizeReceiptItems = (items) => (
        Array.isArray(items)
            ? items
                .map((item) => {
                    if (!item || typeof item !== 'object') {
                        return null;
                    }

                    const label = resolveOptionalText(item.label);
                    if (!label) {
                        return null;
                    }

                    const qty = normalizeAmount(item.qty, 1);
                    const unitPrice = normalizeAmount(item.unit_price);
                    const subtotal = normalizeAmount(item.subtotal, qty * unitPrice);

                    return {
                        label,
                        qty: qty > 0 ? qty : 1,
                        unit_label: resolveOptionalText(item.unit_label),
                        unit_price: unitPrice,
                        subtotal,
                        notes: resolveOptionalText(item.notes),
                        description: resolveOptionalText(item.description),
                    };
                })
                .filter((item) => item !== null)
            : []
    );

    const serviceItems = normalizeReceiptItems(row.service_items);
    if (serviceItems.length < 1) {
        const serviceFeeAmount = normalizeAmount(row.service_fee);
        if (serviceFeeAmount > 0) {
            serviceItems.push({
                label: 'Biaya Jasa Servis',
                qty: 1,
                unit_label: 'jasa',
                unit_price: serviceFeeAmount,
                subtotal: serviceFeeAmount,
                notes: null,
                description: null,
            });
        }
    }
    const sparePartItems = normalizeReceiptItems(row.sparepart_items);

    const sumItems = (items) => items.reduce((total, item) => total + normalizeAmount(item.subtotal), 0);
    const serviceItemsTotal = sumItems(serviceItems);
    const sparePartItemsTotal = sumItems(sparePartItems);

    const subtotalServiceAmount = normalizeAmount(row.subtotal_service_amount, serviceItemsTotal);
    const subtotalSparePartAmount = normalizeAmount(row.subtotal_sparepart_amount, sparePartItemsTotal);
    let grandTotalAmount = normalizeAmount(row.grand_total_amount, row.total_amount);
    let subtotalAmount = normalizeAmount(row.subtotal_amount, subtotalServiceAmount + subtotalSparePartAmount);
    let discountAmount = normalizeAmount(row.discount_amount, subtotalAmount > grandTotalAmount ? subtotalAmount - grandTotalAmount : 0);

    if (subtotalAmount < grandTotalAmount + discountAmount) {
        subtotalAmount = grandTotalAmount + discountAmount;
    }
    if (subtotalAmount < 1) {
        subtotalAmount = grandTotalAmount;
        discountAmount = 0;
    }
    if (grandTotalAmount < 1 && subtotalAmount > 0) {
        grandTotalAmount = subtotalAmount;
        discountAmount = 0;
    }

    const buildItemRows = (items) => {
        if (!Array.isArray(items) || items.length < 1) {
            return '<p class="receipt-note">-</p>';
        }

        return `
            <ul class="receipt-items">
                ${items.map((item) => {
                    const qtyText = item.qty > 0
                        ? `${item.qty}${item.unit_label ? ` ${item.unit_label}` : ''}`
                        : null;
                    const unitPriceText = resolveOptionalCurrency(item.unit_price);
                    const itemNotesText = item.notes || item.description;
                    const metaParts = [
                        qtyText,
                        unitPriceText ? `x ${unitPriceText}` : null,
                        itemNotesText,
                    ].filter((part) => Boolean(part));
                    const metaText = metaParts.join(' | ');

                    return `
                        <li class="receipt-item-row">
                            <div class="receipt-item-main">
                                <p class="receipt-item-label">${escapeHtml(item.label)}</p>
                                ${metaText !== '' ? `<p class="receipt-item-meta">${escapeHtml(metaText)}</p>` : ''}
                            </div>
                            <p class="receipt-item-amount">${escapeHtml(resolveOptionalCurrency(item.subtotal) || '-')}</p>
                        </li>
                    `;
                }).join('')}
            </ul>
        `;
    };

    const detailRowsHtml = [
        buildReceiptRow('No. Polisi', resolveSafeText(row.plate_number, '-'), 'left'),
        buildReceiptRow('Kendaraan', resolveSafeText(row.vehicle_name, '-'), 'left'),
        buildReceiptRow('Odometer', row.odometer !== null && row.odometer !== undefined ? `${row.odometer} km` : null, 'left'),
        buildReceiptRow('Tanggal', serviceDateText !== '-' ? serviceDateText : '-', 'left'),
        buildReceiptRow('Status', resolveSafeText(row.status_label, 'Selesai')),
    ].filter((item) => item !== '').join('');

    const notesRowsHtml = [
        buildReceiptRow('Keluhan', complaintText, 'left'),
        buildReceiptRow('Tindakan', completionNotesText, 'left'),
    ].filter((item) => item !== '').join('');

    const isCompletedStatus = isCompletedSalesStatus(row);
    const invoiceTotalAmount = normalizeAmount(invoice?.total_amount, grandTotalAmount);
    const invoiceStatusText = isCompletedStatus ? 'Lunas' : resolveOptionalText(invoice?.status_label);
    const paidAmountText = isCompletedStatus
        ? resolveOptionalCurrency(invoiceTotalAmount)
        : resolveOptionalCurrency(invoice?.paid_amount);
    const remainingAmountText = isCompletedStatus
        ? null
        : resolveOptionalCurrency(invoice?.remaining_amount);
    const invoiceRowsHtml = [
        buildReceiptRow('No. Invoice', resolveOptionalText(invoice?.code) || '-', 'left'),
        buildReceiptRow('Status', resolveOptionalText(invoiceStatusText) || '-', 'left'),
        buildReceiptRow('Total Invoice', resolveOptionalCurrency(invoice?.total_amount ?? (isCompletedStatus ? invoiceTotalAmount : grandTotalAmount)) || '-'),
        buildReceiptRow('Terbayar', paidAmountText),
    ].filter((item) => item !== '').join('');

    const noteHeaderHtml = `
        <section>
            <p class="receipt-section-title">*** NOTA SERVIS ***</p>
            ${orderCode ? `<p class="receipt-order-code">${escapeHtml(orderCode)}</p>` : ''}
        </section>
    `;

    const bodyHtml = `
        <div class="receipt-root">
            ${noteHeaderHtml}
            <div class="receipt-rule"></div>
            <p class="receipt-meta-line"><span>Customer : ${escapeHtml(customerNameText)}</span><span>No. HP : ${escapeHtml(customerPhoneText)}</span></p>
            <div class="receipt-rule"></div>
            <section>
                <p class="receipt-section-title">INFO KENDARAAN</p>
                ${detailRowsHtml !== '' ? `<ul class="receipt-kv-list">${detailRowsHtml}</ul>` : '<p class="receipt-note">Data kendaraan tidak tersedia.</p>'}
            </section>
            <div class="receipt-rule"></div>
            <section>
                <p class="receipt-section-title">KELUHAN & CATATAN</p>
                ${notesRowsHtml !== '' ? `<ul class="receipt-kv-list">${notesRowsHtml}</ul>` : '<p class="receipt-note">-</p>'}
            </section>
            <div class="receipt-rule"></div>
            <section>
                <p class="receipt-section-title">JASA SERVIS</p>
                ${buildItemRows(serviceItems)}
            </section>
            <div class="receipt-rule"></div>
            <section>
                <p class="receipt-section-title">SPAREPART</p>
                ${buildItemRows(sparePartItems)}
            </section>
            <div class="receipt-rule"></div>
            <section>
                <ul class="receipt-kv-list">
                    ${buildReceiptRow('Subtotal Jasa', resolveOptionalCurrency(subtotalServiceAmount) || '-')}
                    ${buildReceiptRow('Subtotal Sparepart', resolveOptionalCurrency(subtotalSparePartAmount) || '-')}
                    ${buildReceiptRow('Subtotal', resolveOptionalCurrency(subtotalAmount) || '-')}
                    ${discountAmount > 0 ? buildReceiptRow('Diskon', `- ${resolveOptionalCurrency(discountAmount)}`) : ''}
                </ul>
                <div class="receipt-highlight">
                    <span>TOTAL</span>
                    <span>${escapeHtml(resolveOptionalCurrency(grandTotalAmount) || '-')}</span>
                </div>
            </section>
            <div class="receipt-rule"></div>
            <section>
                <p class="receipt-section-title">INFORMASI INVOICE</p>
                ${invoiceRowsHtml !== '' ? `<ul class="receipt-kv-list">${invoiceRowsHtml}</ul>` : '<p class="receipt-note">Belum ada invoice.</p>'}
                ${remainingAmountText ? `
                    <div class="receipt-highlight">
                        <span>SISA TAGIHAN</span>
                        <span>${escapeHtml(remainingAmountText)}</span>
                    </div>
                ` : ''}
            </section>
            <div class="receipt-rule strong"></div>
            <p class="note-footer">
                Terima kasih telah mempercayakan<br />
                kendaraan Anda kepada kami.
            </p>
            <div class="receipt-rule"></div>
        </div>
    `;

    const subtitleLines = [
        workshopAddressText,
        workshopPhoneText ? `No. HP: ${workshopPhoneText}` : null,
    ].filter((part) => Boolean(part));
    const subtitle = subtitleLines.join('\n');

    openPrintWindow({
        title: workshopNameText.toUpperCase(),
        subtitle,
        bodyHtml,
        page: {
            size: `${salesNotePaperSizeMm.value}mm auto`,
            margin: '3mm',
            maxWidth: `${Math.max(48, salesNotePaperSizeMm.value - 6)}mm`,
            fontFamily: "'Courier New', 'Consolas', monospace",
            fontSize: '10px',
            isThermal: true,
        },
    });
};

const triggerAutoPrintSalesNote = () => {
    if (!isSalesReport.value || !shouldAutoPrintSalesNote.value || hasAutoPrintSalesNoteTriggered.value) {
        return;
    }

    const targetOrderId = String(autoPrintSalesOrderId.value || '').trim();
    if (targetOrderId === '') {
        return;
    }

    const targetRow = salesRows.value.find((row) => String(row?.id || '').trim() === targetOrderId);
    if (!targetRow) {
        return;
    }

    hasAutoPrintSalesNoteTriggered.value = true;
    printSalesNote(targetRow);

    const returnToPath = String(autoPrintReturnTo.value || '').trim();
    if (returnToPath === '' || hasAutoPrintReturnTriggered.value || typeof window === 'undefined') {
        return;
    }

    hasAutoPrintReturnTriggered.value = true;
    setTimeout(() => {
        window.location.assign(returnToPath);
    }, 2000);
};

watch(
    () => [isSalesReport.value, shouldAutoPrintSalesNote.value, autoPrintSalesOrderId.value, salesRows.value.length],
    () => {
        triggerAutoPrintSalesNote();
    },
    {
        immediate: true,
    },
);

const printSalesReportPage = () => {
    const subtitle = `${resolveSafeText(pageTitle.value)} | Dicetak ${resolvePrintableDateTime(new Date())}`;
    const cardsHtml = cards.value.length > 0
        ? cards.value.map((card) => `
            <tr>
                <th>${escapeHtml(resolveSafeText(card?.label))}</th>
                <td>${escapeHtml(formatCardValue(card))}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="2" class="muted">Tidak ada data KPI.</td></tr>';

    const rowsHtml = salesRows.value.length > 0
        ? salesRows.value.map((row, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(resolveSafeText(row.code))}</td>
                <td>${escapeHtml(resolvePrintableDate(row.service_date))}</td>
                <td>${escapeHtml(resolveSafeText(row.customer_name))}</td>
                <td>${escapeHtml(resolveSafeText(row.vehicle_name))}</td>
                <td>${escapeHtml(resolveSafeText(row.plate_number))}</td>
                <td>${escapeHtml(resolveSafeText(row.status_label, 'Selesai'))}</td>
                <td class="text-right">${escapeHtml(formatRupiah(row.total_amount || 0))}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="8" class="muted">Tidak ada data servis.</td></tr>';

    const bodyHtml = `
        <section class="section">
            <h3 style="margin: 0 0 10px; font-size: 14px; color: #0f172a;">Ringkasan KPI</h3>
            <table>
                <tbody>${cardsHtml}</tbody>
            </table>
        </section>
        <section class="section">
            <h3 style="margin: 0 0 10px; font-size: 14px; color: #0f172a;">Detail Servis (sesuai filter aktif)</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 42px;">No</th>
                        <th>Kode</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Kendaraan</th>
                        <th>Nopol</th>
                        <th>Status</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>${rowsHtml}</tbody>
            </table>
        </section>
    `;

    openPrintWindow({
        title: resolveSafeText(pageTitle.value, 'Laporan Servis'),
        subtitle,
        bodyHtml,
    });
};

const exportSalesReport = () => {
    if (typeof window === 'undefined' || typeof URLSearchParams === 'undefined') {
        return;
    }

    const query = normalizeSalesReportFilters(salesTableFilters.value, { includeCursor: false });
    const searchParams = new URLSearchParams(query);
    const exportUrl = `${exportSalesReportPath.value}?${searchParams.toString()}`;
    window.location.assign(exportUrl);
};

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head :title="pageTitle" />

    <AppDashboardLayout
        :title="pageTitle"
        :subtitle="pageDescription"
        role-label="Owner"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div class="space-y-4">
            <section v-if="!isAiMonthlyReport" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="(card, index) in cards"
                    :key="String(card.key || card.label || '')"
                    class="rounded-2xl border p-4 shadow-sm transition hover:-translate-y-0.5"
                    :class="resolveKpiTheme(card, index).card"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p
                            class="text-xs font-semibold uppercase tracking-wide"
                            :class="resolveKpiTheme(card, index).label"
                        >
                            {{ card.label }}
                        </p>
                        <span
                            class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                            :class="resolveKpiTheme(card, index).badge"
                        >
                            {{ resolveKpiFormatLabel(card) }}
                        </span>
                    </div>
                    <p class="mt-3 text-2xl font-semibold leading-tight" :class="resolveKpiTheme(card, index).value">
                        {{ formatCardValue(card) }}
                    </p>
                    <div class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-white/70 dark:bg-slate-800/80">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="resolveKpiTheme(card, index).meter"
                            :style="{ width: resolveKpiMeterWidth(card) }"
                        />
                    </div>
                </article>
                <article
                    v-if="cards.length === 0"
                    class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400 md:col-span-2 xl:col-span-4"
                >
                    Tidak ada data
                </article>
            </section>

            <section
                v-if="isAiMonthlyReport"
                class="space-y-4 rounded-2xl border border-cyan-100 bg-white p-4 shadow-sm dark:border-cyan-500/20 dark:bg-slate-900"
            >
                <template v-if="canUseAiFeature">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Analisa laporan: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ aiBusinessReport?.scope_label || 'Semua Cabang' }}</span>
                    </p>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span>Cabang</span>
                        <select
                            :value="aiTableFilters.workshop_id"
                            class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-cyan-300 dark:focus:ring-cyan-500/20"
                            @change="handleAiWorkshopChange"
                        >
                            <option
                                v-for="option in aiWorkshopOptions"
                                :key="`ai-workshop-${option.value}`"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <template v-if="aiBusinessReport?.is_available">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Periode</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ aiBusinessReport.period_label || '-' }}</p>
                        </article>
                        <article class="rounded-xl border border-sky-200 bg-sky-50/70 p-3 dark:border-sky-400/30 dark:bg-sky-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Omzet Total</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(aiBusinessReport.total_revenue) }}</p>
                        </article>
                        <article class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Order Selesai</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ aiBusinessReport.order_completion_text || '0 / 0' }}</p>
                        </article>
                        <article class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-3 dark:border-indigo-400/30 dark:bg-indigo-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Customer Baru</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ formatNumber(aiBusinessReport.new_customers) }}</p>
                        </article>
                    </div>

                    <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Ringkasan Eksekutif</p>
                        <p class="mt-2 text-sm leading-relaxed text-slate-700 dark:text-slate-200">
                            {{ aiBusinessReport.executive_summary || '-' }}
                        </p>
                    </article>

                    <div class="grid gap-3 md:grid-cols-3">
                        <article class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Omzet Jasa</p>
                            <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(aiBusinessReport.service_revenue) }}</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Omzet Sparepart</p>
                            <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(aiBusinessReport.sparepart_revenue) }}</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Estimasi Laba Kotor</p>
                            <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(aiBusinessReport.gross_profit_estimate) }}</p>
                        </article>
                    </div>

                    <article class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Highlights</p>
                        <ul v-if="aiBusinessHighlights.length > 0" class="mt-2 space-y-1.5 text-sm text-slate-700 dark:text-slate-200">
                            <li v-for="(item, index) in aiBusinessHighlights" :key="`ai-highlight-${index}`">- {{ item }}</li>
                        </ul>
                        <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tidak ada data</p>
                    </article>

                    <div class="grid gap-3 md:grid-cols-3">
                        <article class="rounded-xl border border-rose-200 bg-rose-50/60 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Risiko</p>
                            <ul v-if="aiBusinessRisks.length > 0" class="mt-2 space-y-1.5 text-sm text-slate-700 dark:text-slate-200">
                                <li v-for="(item, index) in aiBusinessRisks" :key="`ai-risk-${index}`">- {{ item }}</li>
                            </ul>
                            <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tidak ada data</p>
                        </article>
                        <article class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Rekomendasi</p>
                            <ul v-if="aiBusinessRecommendations.length > 0" class="mt-2 space-y-1.5 text-sm text-slate-700 dark:text-slate-200">
                                <li v-for="(item, index) in aiBusinessRecommendations" :key="`ai-reco-${index}`">- {{ item }}</li>
                            </ul>
                            <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tidak ada data</p>
                        </article>
                        <article class="rounded-xl border border-cyan-200 bg-cyan-50/60 p-3 dark:border-cyan-400/30 dark:bg-cyan-500/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">Fokus Bulan Depan</p>
                            <ul v-if="aiBusinessNextMonthFocus.length > 0" class="mt-2 space-y-1.5 text-sm text-slate-700 dark:text-slate-200">
                                <li v-for="(item, index) in aiBusinessNextMonthFocus" :key="`ai-focus-${index}`">- {{ item }}</li>
                            </ul>
                            <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tidak ada data</p>
                        </article>
                    </div>

                    <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Catatan wajib</p>
                        <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                            {{ aiBusinessReport.disclaimer || 'Laporan AI adalah ringkasan awal, validasi akhir tetap oleh owner/manager bengkel.' }}
                        </p>
                    </article>
                </template>

                <article
                    v-else
                    class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300"
                >
                    {{ aiBusinessReport?.empty_message || 'Belum ada output laporan AI bulanan.' }}
                </article>
                </template>

                <article
                    v-else
                    class="rounded-xl border border-dashed border-cyan-200 bg-cyan-50/70 p-4 text-sm text-cyan-800 dark:border-cyan-500/40 dark:bg-cyan-500/10 dark:text-cyan-200"
                >
                    Fitur AI belum tersedia pada paket aktif. Upgrade paket untuk membuka Laporan AI Bulanan.
                </article>
            </section>

            <section
                v-if="isAiMonthlyReport && canUseAiFeature && !hasAiBusinessReport"
                class="rounded-2xl border border-cyan-100 bg-white p-4 shadow-sm dark:border-cyan-500/20 dark:bg-slate-900"
            >
                <div class="grid gap-4 lg:grid-cols-3">
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Breakdown Fitur AI</h4>
                        <div v-if="aiFeatureBreakdown.length > 0" class="mt-3 space-y-3">
                            <div
                                v-for="feature in aiFeatureBreakdown"
                                :key="`ai-feature-${feature.feature_key}`"
                                class="space-y-1.5"
                            >
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <p class="font-semibold text-slate-700 dark:text-slate-200">{{ feature.feature_label }}</p>
                                    <p class="text-slate-500 dark:text-slate-400">{{ formatNumber(feature.total) }} req</p>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div
                                        class="h-full rounded-full bg-cyan-500"
                                        :style="{ width: resolveFeatureMeterWidth(feature.total) }"
                                    />
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    Sukses {{ feature.success_rate }}% • Token {{ formatNumber(feature.total_tokens) }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tidak ada data</p>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Breakdown Status</h4>
                        <div v-if="aiStatusBreakdown.length > 0" class="mt-3 space-y-3">
                            <div
                                v-for="status in aiStatusBreakdown"
                                :key="`ai-status-${status.status}`"
                                class="space-y-1.5"
                            >
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <p class="font-semibold text-slate-700 dark:text-slate-200">{{ status.status_label }}</p>
                                    <p class="text-slate-500 dark:text-slate-400">{{ formatNumber(status.total) }} req</p>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div
                                        class="h-full rounded-full bg-emerald-500"
                                        :style="{ width: resolveStatusMeterWidth(status.total) }"
                                    />
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tidak ada data</p>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Tren 14 Hari Terakhir</h4>
                        <div v-if="aiDailyTrend.length > 0" class="mt-3">
                            <div class="flex h-28 items-end gap-1.5">
                                <div
                                    v-for="day in aiDailyTrend"
                                    :key="`ai-trend-${day.date}`"
                                    class="group flex flex-1 flex-col items-center justify-end gap-1"
                                >
                                    <span
                                        class="w-full rounded-t bg-sky-400/80 transition group-hover:bg-sky-500 dark:bg-sky-300/70 dark:group-hover:bg-sky-300"
                                        :style="{ height: resolveTrendBarHeight(day.total) }"
                                        :title="`${day.label}: ${formatNumber(day.total)} request`"
                                    />
                                    <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ day.label }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tidak ada data</p>
                    </article>
                </div>
            </section>

            <section
                v-if="isAiMonthlyReport && canUseAiFeature && !hasAiBusinessReport"
                class="rounded-2xl border border-cyan-100 bg-white p-4 shadow-sm dark:border-cyan-500/20 dark:bg-slate-900"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Aktivitas AI terbaru dalam bulan berjalan.
                    </p>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span>Sumber</span>
                        <select
                            :value="aiTableFilters.source"
                            class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-cyan-300 dark:focus:ring-cyan-500/20"
                            @change="handleAiSourceChange"
                        >
                            <option
                                v-for="option in aiSourceOptions"
                                :key="`ai-source-${option.value}`"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <DataTable
                    :columns="aiColumns"
                    :rows="aiRows"
                    :pagination="aiPagination"
                    :filters="aiTableFilters"
                    :loading="aiTableLoading"
                    search-placeholder="Cari sumber, fitur, status, kode servis, atau user..."
                    empty-text="Tidak ada data"
                    @update:search="handleAiSearch"
                    @sort="handleAiSort"
                    @update:per-page="handleAiPerPage"
                    @page="handleAiPage"
                >
                    <template #cell-created_at="{ value }">
                        {{ formatDateTimeIndonesia(value, { dateStyle: 'medium', timeStyle: 'short' }) }}
                    </template>

                    <template #cell-service_order_code="{ value }">
                        {{ value || '-' }}
                    </template>

                    <template #cell-source="{ row }">
                        <span class="inline-flex rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-300">
                            {{ row.source_label || '-' }}
                        </span>
                    </template>

                    <template #cell-feature_key="{ row }">
                        {{ row.feature_label || '-' }}
                    </template>

                    <template #cell-status="{ row }">
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="resolveAiStatusBadgeClass(row.status)"
                        >
                            {{ row.status_label || '-' }}
                        </span>
                    </template>

                    <template #cell-total_tokens="{ value }">
                        {{ formatNumber(value) }}
                    </template>

                    <template #cell-latency_ms="{ value }">
                        {{ value || value === 0 ? `${formatNumber(value)} ms` : '-' }}
                    </template>

                    <template #cell-generated_by_name="{ value }">
                        {{ value || '-' }}
                    </template>
                </DataTable>
            </section>

            <section
                v-if="isExpenseReport"
                class="rounded-2xl border border-rose-100 bg-white p-4 shadow-sm dark:border-rose-500/20 dark:bg-slate-900"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Detail pengeluaran mengikuti cabang aktif, kategori, pencarian, dan pengurutan.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <span>Cabang</span>
                            <select
                                :value="expenseTableFilters.workshop_id"
                                class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 outline-none transition focus:border-rose-400 focus:ring-2 focus:ring-rose-100 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-rose-300 dark:focus:ring-rose-500/20"
                                @change="handleExpenseWorkshopChange"
                            >
                                <option
                                    v-for="option in expenseWorkshopOptions"
                                    :key="`expense-workshop-${option.value}`"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <span>Kategori</span>
                            <select
                                :value="expenseTableFilters.category"
                                class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 outline-none transition focus:border-rose-400 focus:ring-2 focus:ring-rose-100 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-rose-300 dark:focus:ring-rose-500/20"
                                @change="handleExpenseCategoryChange"
                            >
                                <option value="">
                                    Semua Kategori
                                </option>
                                <option
                                    v-for="category in expenseCategoryOptions"
                                    :key="`expense-category-${category}`"
                                    :value="category"
                                >
                                    {{ category }}
                                </option>
                            </select>
                        </label>
                    </div>
                </div>

                <DataTable
                    :columns="expenseColumns"
                    :rows="expenseRows"
                    :pagination="expensePagination"
                    :filters="expenseTableFilters"
                    :loading="expenseTableLoading"
                    search-placeholder="Cari deskripsi, kategori, referensi, atau catatan pengeluaran..."
                    empty-text="Tidak ada data"
                    @update:search="handleExpenseSearch"
                    @sort="handleExpenseSort"
                    @update:per-page="handleExpensePerPage"
                    @page="handleExpensePage"
                >
                    <template #cell-expense_date="{ value }">
                        {{ formatDateIndonesia(value, { dateStyle: 'medium' }) }}
                    </template>

                    <template #cell-workshop_name="{ row }">
                        <div class="space-y-0.5">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ row.workshop_name || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.workshop_code || '-' }}</p>
                        </div>
                    </template>

                    <template #cell-category="{ value }">
                        {{ value || '-' }}
                    </template>

                    <template #cell-description="{ row }">
                        <div class="space-y-0.5">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ row.description || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Ref: {{ row.reference_number || '-' }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ row.notes || '-' }}
                            </p>
                        </div>
                    </template>

                    <template #cell-amount="{ value }">
                        {{ formatRupiah(value) }}
                    </template>

                    <template #cell-created_at="{ value }">
                        {{ formatDateTimeIndonesia(value, { dateStyle: 'medium', timeStyle: 'short' }) }}
                    </template>
                </DataTable>
            </section>

            <section
                v-if="isCustomerReport"
                class="rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm dark:border-indigo-500/20 dark:bg-slate-900"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Analisa pelanggan mengikuti cabang, status pelanggan, dan kelengkapan kontak.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <span>Cabang</span>
                            <select
                                :value="customerTableFilters.workshop_id"
                                class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-indigo-300 dark:focus:ring-indigo-500/20"
                                @change="handleCustomerWorkshopChange"
                            >
                                <option
                                    v-for="option in customerWorkshopOptions"
                                    :key="`customer-workshop-${option.value}`"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <span>Status</span>
                            <select
                                :value="customerTableFilters.status"
                                class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-indigo-300 dark:focus:ring-indigo-500/20"
                                @change="handleCustomerStatusChange"
                            >
                                <option
                                    v-for="option in customerStatusOptions"
                                    :key="`customer-status-${option.value}`"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <span>Kontak</span>
                            <select
                                :value="customerTableFilters.contact"
                                class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-indigo-300 dark:focus:ring-indigo-500/20"
                                @change="handleCustomerContactChange"
                            >
                                <option
                                    v-for="option in customerContactOptions"
                                    :key="`customer-contact-${option.value}`"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                    </div>
                </div>

                <article
                    v-if="reportHighlights.length > 0"
                    class="mb-3 rounded-xl border border-indigo-100 bg-indigo-50/70 p-3 dark:border-indigo-500/30 dark:bg-indigo-500/10"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Insight</p>
                    <ul class="mt-2 space-y-1.5 text-sm text-slate-700 dark:text-slate-200">
                        <li v-for="(item, index) in reportHighlights" :key="`customer-highlight-${index}`">- {{ item }}</li>
                    </ul>
                </article>

                <DataTable
                    :columns="customerColumns"
                    :rows="customerRows"
                    :pagination="customerPagination"
                    :filters="customerTableFilters"
                    :loading="customerTableLoading"
                    search-placeholder="Cari nama, telepon, email, alamat, atau catatan pelanggan..."
                    empty-text="Tidak ada data"
                    @update:search="handleCustomerSearch"
                    @sort="handleCustomerSort"
                    @update:per-page="handleCustomerPerPage"
                    @page="handleCustomerPage"
                >
                    <template #cell-name="{ row }">
                        <div class="space-y-0.5">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ row.name || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.address || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.notes || '-' }}</p>
                        </div>
                    </template>

                    <template #cell-phone="{ value }">
                        {{ value || '-' }}
                    </template>

                    <template #cell-email="{ value }">
                        {{ value || '-' }}
                    </template>

                    <template #cell-workshop_name="{ row }">
                        <div class="space-y-0.5">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ row.workshop_name || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.workshop_code || '-' }}</p>
                        </div>
                    </template>

                    <template #cell-contact_quality="{ row }">
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="resolveCustomerContactBadgeClass(row.contact_quality)"
                        >
                            {{ row.contact_quality_label || '-' }}
                        </span>
                    </template>

                    <template #cell-is_active="{ row }">
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="resolveCustomerStatusBadgeClass(row.is_active)"
                        >
                            {{ row.status_label || '-' }}
                        </span>
                    </template>

                    <template #cell-created_at="{ value }">
                        {{ formatDateTimeIndonesia(value, { dateStyle: 'medium', timeStyle: 'short' }) }}
                    </template>
                </DataTable>
            </section>

            <section
                v-if="isProfitLossReport"
                class="rounded-2xl border border-teal-100 bg-white p-4 shadow-sm dark:border-teal-500/20 dark:bg-slate-900"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Periode: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ props.profitLossReport?.period_label || '-' }}</span>
                        • Scope: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ props.profitLossReport?.scope_label || 'Semua Cabang' }}</span>
                    </p>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span>Cabang</span>
                        <select
                            :value="profitLossTableFilters.workshop_id"
                            class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-100 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-teal-300 dark:focus:ring-teal-500/20"
                            @change="handleProfitLossWorkshopChange"
                        >
                            <option
                                v-for="option in profitLossWorkshopOptions"
                                :key="`profit-loss-workshop-${option.value}`"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <div class="mb-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-xl border border-sky-200 bg-sky-50/70 p-3 dark:border-sky-400/30 dark:bg-sky-500/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Total Pendapatan</p>
                        <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(profitLossSummary.total_revenue) }}</p>
                    </article>
                    <article class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Total Beban</p>
                        <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(profitLossSummary.total_expense) }}</p>
                    </article>
                    <article class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Laba Kotor</p>
                        <p class="mt-1 text-xl font-semibold" :class="resolveProfitLossAmountClass(profitLossSummary.gross_profit)">{{ formatRupiah(profitLossSummary.gross_profit) }}</p>
                    </article>
                    <article class="rounded-xl border border-teal-200 bg-teal-50/70 p-3 dark:border-teal-400/30 dark:bg-teal-500/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">Laba Bersih</p>
                        <p class="mt-1 text-xl font-semibold" :class="resolveProfitLossAmountClass(profitLossSummary.net_profit)">{{ formatRupiah(profitLossSummary.net_profit) }}</p>
                    </article>
                </div>

                <DataTable
                    :columns="profitLossColumns"
                    :rows="profitLossRows"
                    :pagination="profitLossPagination"
                    :filters="{ search: '', per_page: profitLossPagination.per_page, sort_by: '', sort_dir: 'asc' }"
                    :loading="profitLossTableLoading"
                    :show-search="false"
                    :show-per-page="false"
                    empty-text="Tidak ada data"
                >
                    <template #cell-group="{ row }">
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="resolveProfitLossGroupBadgeClass(row.group)"
                        >
                            {{ row.group || '-' }}
                        </span>
                    </template>

                    <template #cell-label="{ value }">
                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ value || '-' }}</p>
                    </template>

                    <template #cell-formula="{ value }">
                        <p class="text-xs text-slate-600 dark:text-slate-300">{{ value || '-' }}</p>
                    </template>

                    <template #cell-amount="{ value }">
                        <p class="font-semibold" :class="resolveProfitLossAmountClass(value)">
                            {{ formatRupiah(value) }}
                        </p>
                    </template>
                </DataTable>
            </section>

            <section
                v-if="isSparePartReport"
                class="space-y-3 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-sky-500/20 dark:bg-slate-900"
            >
                <template v-if="canUseAiFeature">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Prediksi reorder untuk <span class="font-semibold text-slate-700 dark:text-slate-200">{{ sparePartReorderInsights?.scope_label || 'Semua Cabang' }}</span>.
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Window {{ sparePartReorderInsights?.usage_window_days || 60 }} hari • Lead time {{ sparePartReorderInsights?.lead_time_days || 14 }} hari
                    </p>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <article class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Item Perlu Reorder</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ formatNumber(sparePartReorderSummary.items_need_reorder) }}</p>
                    </article>
                    <article class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Prioritas Kritis</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ formatNumber(sparePartReorderSummary.critical_items) }}</p>
                    </article>
                    <article class="rounded-xl border border-sky-200 bg-sky-50/70 p-3 dark:border-sky-400/30 dark:bg-sky-500/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Estimasi Nilai Reorder</p>
                        <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ formatRupiah(sparePartReorderSummary.estimated_reorder_cost) }}</p>
                    </article>
                </div>

                <article class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70">
                    <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Prediksi Reorder Prioritas</h4>

                    <div v-if="sparePartReorderRows.length > 0" class="mt-3 overflow-x-auto">
                        <table class="min-w-full border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/60">
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sparepart</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Stok</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pemakaian/Hari</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Saran Order</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Habis (Hari)</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Estimasi Biaya</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Prioritas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in sparePartReorderRows"
                                    :key="`sparepart-reorder-${row.id}`"
                                    class="border-t border-slate-100 dark:border-slate-800"
                                >
                                    <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ row.name || '-' }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.sku || '-' }} • {{ row.supplier_name || '-' }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm text-slate-700 dark:text-slate-200">{{ formatNumber(row.stock_total) }}</td>
                                    <td class="px-3 py-2 text-right text-sm text-slate-700 dark:text-slate-200">{{ formatNumber(row.avg_daily_usage) }}</td>
                                    <td class="px-3 py-2 text-right text-sm font-semibold text-slate-800 dark:text-slate-100">{{ formatNumber(row.recommended_qty) }}</td>
                                    <td class="px-3 py-2 text-right text-sm text-slate-700 dark:text-slate-200">
                                        {{ row.estimated_stockout_days || row.estimated_stockout_days === 0 ? formatNumber(row.estimated_stockout_days) : '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm text-slate-700 dark:text-slate-200">{{ formatRupiah(row.estimated_reorder_cost) }}</td>
                                    <td class="px-3 py-2 text-sm">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="resolveReorderPriorityBadgeClass(row.priority)"
                                        >
                                            {{ row.priority_label || '-' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                        {{ sparePartReorderInsights?.empty_message || 'Tidak ada data' }}
                    </p>

                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        {{ sparePartReorderInsights?.disclaimer || 'Prediksi reorder berbasis pemakaian historis dan parameter stok minimum.' }}
                    </p>
                </article>
                </template>

                <article
                    v-else
                    class="rounded-xl border border-dashed border-sky-200 bg-sky-50/70 p-4 text-sm text-sky-800 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-200"
                >
                    Prediksi reorder AI belum tersedia pada paket aktif. Silakan upgrade paket untuk mengaktifkan fitur ini.
                </article>
            </section>

            <section
                v-if="isSparePartReport"
                class="rounded-2xl border border-amber-100 bg-white p-4 shadow-sm dark:border-amber-500/20 dark:bg-slate-900"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Analisa sparepart mengikuti cabang aktif, filter pencarian, dan pengurutan.
                    </p>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span>Cabang</span>
                        <select
                            :value="sparePartTableFilters.workshop_id"
                            class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-100 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-amber-300 dark:focus:ring-amber-500/20"
                            @change="handleSparePartWorkshopChange"
                        >
                            <option
                                v-for="option in sparePartWorkshopOptions"
                                :key="`sparepart-workshop-${option.value}`"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <DataTable
                    :columns="sparePartColumns"
                    :rows="sparePartRows"
                    :pagination="sparePartPagination"
                    :filters="sparePartTableFilters"
                    :loading="sparePartTableLoading"
                    search-placeholder="Cari nama sparepart, SKU, kategori, unit, atau supplier..."
                    empty-text="Tidak ada data"
                    @update:search="handleSparePartSearch"
                    @sort="handleSparePartSort"
                    @update:per-page="handleSparePartPerPage"
                    @page="handleSparePartPage"
                >
                    <template #cell-name="{ row }">
                        <div class="space-y-0.5">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ row.name || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.supplier_name || '-' }}</p>
                        </div>
                    </template>

                    <template #cell-sku="{ value }">
                        {{ value || '-' }}
                    </template>

                    <template #cell-category="{ row }">
                        <div class="space-y-0.5">
                            <p class="text-slate-700 dark:text-slate-200">{{ row.category || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Unit: {{ row.unit || '-' }}</p>
                        </div>
                    </template>

                    <template #cell-stock_total="{ value }">
                        {{ formatNumber(value) }}
                    </template>

                    <template #cell-minimum_stock_total="{ value }">
                        {{ formatNumber(value) }}
                    </template>

                    <template #cell-used_qty="{ value }">
                        {{ formatNumber(value) }}
                    </template>

                    <template #cell-usage_revenue="{ value }">
                        {{ formatRupiah(value) }}
                    </template>

                    <template #cell-stock_status_rank="{ row }">
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="resolveSparePartStatusBadgeClass(row.stock_status)"
                        >
                            {{ row.stock_status_label || '-' }}
                        </span>
                    </template>

                    <template #cell-created_at="{ value }">
                        {{ formatDateTimeIndonesia(value, { dateStyle: 'medium', timeStyle: 'short' }) }}
                    </template>
                </DataTable>
            </section>

            <section
                v-if="isSalesReport"
                class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Detail mengikuti filter pencarian dan pengurutan aktif.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-sky-200 bg-sky-50 px-3 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-300 dark:hover:bg-sky-500/20"
                            @click="printSalesReportPage"
                        >
                            Cetak Halaman
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                            @click="exportSalesReport"
                        >
                            Export Excel
                        </button>
                    </div>
                </div>

                <DataTable
                    :columns="salesColumns"
                    :rows="salesRows"
                    :pagination="salesPagination"
                    :filters="salesTableFilters"
                    :loading="tableLoading"
                    search-placeholder="Cari kode servis, pelanggan, atau kendaraan..."
                    empty-text="Tidak ada data"
                    @update:search="handleSearch"
                    @sort="handleSort"
                    @update:per-page="handlePerPage"
                    @page="handlePage"
                >
                    <template #cell-service_date="{ value }">
                        {{ formatDateIndonesia(value, { dateStyle: 'medium' }) }}
                    </template>

                    <template #cell-customer_name="{ row }">
                        <div class="space-y-0.5">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ row.customer_name || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.customer_phone || '-' }}</p>
                        </div>
                    </template>

                    <template #cell-vehicle_name="{ row }">
                        <div class="space-y-0.5">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ row.vehicle_name || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.plate_number || '-' }}</p>
                        </div>
                    </template>

                    <template #cell-plate_number="{ value }">
                        {{ value || '-' }}
                    </template>

                    <template #cell-status_label="{ value }">
                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                            {{ value || 'Selesai' }}
                        </span>
                    </template>

                    <template #cell-total_amount="{ value }">
                        {{ formatRupiah(value) }}
                    </template>

                    <template #cell-created_at="{ value }">
                        {{ formatDateTimeIndonesia(value, { dateStyle: 'medium', timeStyle: 'short' }) }}
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                            <button
                                type="button"
                                class="inline-flex h-8 min-w-[78px] cursor-pointer items-center justify-center rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                                @click="openSalesDetail(row)"
                            >
                                Detail
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-8 min-w-[92px] cursor-pointer items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                                @click="printSalesNote(row)"
                            >
                                Cetak Nota
                            </button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </div>

        <div
            v-if="isSalesDetailModalOpen && selectedSalesRow"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6 backdrop-blur-sm"
            @click.self="closeSalesDetail"
        >
            <article class="w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                <header class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Detail Servis</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ selectedSalesRow.code || '-' }} • {{ selectedSalesRow.service_date ? formatDateIndonesia(selectedSalesRow.service_date, { dateStyle: 'medium' }) : '-' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                        @click="closeSalesDetail"
                    >
                        Tutup
                    </button>
                </header>

                <div class="max-h-[70vh] space-y-4 overflow-y-auto px-5 py-4">
                    <section class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pelanggan</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ selectedSalesRow.customer_name || '-' }}</p>
                            <p class="text-xs text-slate-600 dark:text-slate-300">{{ selectedSalesRow.customer_phone || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ selectedSalesRow.customer_email || '-' }}</p>
                        </div>
                        <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kendaraan</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ selectedSalesRow.vehicle_name || '-' }}</p>
                            <p class="text-xs text-slate-600 dark:text-slate-300">{{ selectedSalesRow.plate_number || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">KM: {{ selectedSalesRow.odometer ?? '-' }}</p>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ selectedSalesRow.status_label || 'Selesai' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Servis</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ formatRupiah(selectedSalesRow.total_amount || 0) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Dicatat</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ selectedSalesRow.created_at ? formatDateTimeIndonesia(selectedSalesRow.created_at, { dateStyle: 'medium', timeStyle: 'short' }) : '-' }}</p>
                        </div>
                    </section>

                    <section class="space-y-2">
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Keluhan</p>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ selectedSalesRow.complaint || '-' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/70">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan Selesai</p>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ selectedSalesRow.completion_notes || '-' }}</p>
                        </div>
                    </section>

                    <section class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Informasi Invoice</p>
                        <template v-if="selectedSalesInvoiceDisplay">
                            <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ selectedSalesInvoiceDisplay.code || '-' }} • {{ selectedSalesInvoiceDisplay.status_label || '-' }}</p>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                Total: {{ formatRupiah(selectedSalesInvoiceDisplay.total_amount || 0) }} • Terbayar: {{ formatRupiah(selectedSalesInvoiceDisplay.paid_amount || 0) }} • Sisa: {{ formatRupiah(selectedSalesInvoiceDisplay.remaining_amount || 0) }}
                            </p>
                        </template>
                        <p v-else class="mt-1 text-sm text-slate-600 dark:text-slate-300">Belum ada invoice.</p>
                    </section>
                </div>

                <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-900">
                    <button
                        type="button"
                        class="inline-flex h-10 cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                        @click="printSalesNote(selectedSalesRow)"
                    >
                        Cetak Nota
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 cursor-pointer items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                        @click="closeSalesDetail"
                    >
                        Tutup
                    </button>
                </footer>
            </article>
        </div>
    </AppDashboardLayout>
</template>


