import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    finance_search: String(filters.search || '').trim(),
    finance_status: String(filters.status || '').trim(),
    finance_method: String(filters.method || '').trim(),
    finance_state: String(filters.state || '').trim(),
    finance_sort_by: String(filters.sort_by || 'invoice_date'),
    finance_sort_dir: String(filters.sort_dir || 'desc'),
    finance_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { finance_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerFinanceRecords = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerInvoicePayment = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

export const updateOwnerInvoiceDueDate = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

export const markOwnerInvoiceReminder = (path, options = {}) => {
    router.patch(path, {}, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};
