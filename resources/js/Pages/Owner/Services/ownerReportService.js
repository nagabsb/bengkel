import { router } from '@inertiajs/vue3';

export const normalizeSalesReportFilters = (filters = {}, options = {}) => ({
    sales_report_search: String(filters.search || '').trim(),
    sales_report_sort_by: String(filters.sort_by || 'service_date'),
    sales_report_sort_dir: String(filters.sort_dir || 'desc'),
    sales_report_per_page: Number(filters.per_page) || 10,
    ...(
        options.includeCursor !== false && String(filters.cursor || '').trim() !== ''
            ? { sales_report_cursor: String(filters.cursor).trim() }
            : {}
    ),
});

export const fetchOwnerSalesReport = (path, filters = {}, options = {}) => {
    router.get(path, normalizeSalesReportFilters(filters, { includeCursor: true }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const normalizeExpenseReportFilters = (filters = {}, options = {}) => ({
    expense_report_search: String(filters.search || '').trim(),
    expense_report_workshop_id: String(filters.workshop_id || '__all__'),
    expense_report_category: String(filters.category || '').trim(),
    expense_report_sort_by: String(filters.sort_by || 'expense_date'),
    expense_report_sort_dir: String(filters.sort_dir || 'desc'),
    expense_report_per_page: Number(filters.per_page) || 10,
    ...(
        options.includeCursor !== false && String(filters.cursor || '').trim() !== ''
            ? { expense_report_cursor: String(filters.cursor).trim() }
            : {}
    ),
});

export const fetchOwnerExpenseReport = (path, filters = {}, options = {}) => {
    router.get(path, normalizeExpenseReportFilters(filters, { includeCursor: true }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const normalizeCustomerReportFilters = (filters = {}, options = {}) => ({
    customer_report_search: String(filters.search || '').trim(),
    customer_report_workshop_id: String(filters.workshop_id || '__all__'),
    customer_report_status: String(filters.status || 'all'),
    customer_report_contact: String(filters.contact || 'all'),
    customer_report_sort_by: String(filters.sort_by || 'created_at'),
    customer_report_sort_dir: String(filters.sort_dir || 'desc'),
    customer_report_per_page: Number(filters.per_page) || 10,
    ...(
        options.includeCursor !== false && String(filters.cursor || '').trim() !== ''
            ? { customer_report_cursor: String(filters.cursor).trim() }
            : {}
    ),
});

export const fetchOwnerCustomerReport = (path, filters = {}, options = {}) => {
    router.get(path, normalizeCustomerReportFilters(filters, { includeCursor: true }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const normalizeProfitLossReportFilters = (filters = {}) => ({
    profit_loss_workshop_id: String(filters.workshop_id || '__all__'),
});

export const fetchOwnerProfitLossReport = (path, filters = {}, options = {}) => {
    router.get(path, normalizeProfitLossReportFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const normalizeSparePartReportFilters = (filters = {}, options = {}) => ({
    sparepart_report_search: String(filters.search || '').trim(),
    sparepart_report_workshop_id: String(filters.workshop_id || '__all__'),
    sparepart_report_sort_by: String(filters.sort_by || 'created_at'),
    sparepart_report_sort_dir: String(filters.sort_dir || 'desc'),
    sparepart_report_per_page: Number(filters.per_page) || 10,
    ...(
        options.includeCursor !== false && String(filters.cursor || '').trim() !== ''
            ? { sparepart_report_cursor: String(filters.cursor).trim() }
            : {}
    ),
});

export const fetchOwnerSparePartReport = (path, filters = {}, options = {}) => {
    router.get(path, normalizeSparePartReportFilters(filters, { includeCursor: true }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const normalizeAiMonthlyReportFilters = (filters = {}, options = {}) => ({
    ai_report_search: String(filters.search || '').trim(),
    ai_report_source: String(filters.source || 'all_sources'),
    ai_report_workshop_id: String(filters.workshop_id || '__all__'),
    ai_report_sort_by: String(filters.sort_by || 'created_at'),
    ai_report_sort_dir: String(filters.sort_dir || 'desc'),
    ai_report_per_page: Number(filters.per_page) || 10,
    ...(
        options.includeCursor !== false && String(filters.cursor || '').trim() !== ''
            ? { ai_report_cursor: String(filters.cursor).trim() }
            : {}
    ),
});

export const fetchOwnerAiMonthlyReport = (path, filters = {}, options = {}) => {
    router.get(path, normalizeAiMonthlyReportFilters(filters, { includeCursor: true }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};
