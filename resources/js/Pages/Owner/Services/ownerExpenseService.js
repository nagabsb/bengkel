import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    expense_search: String(filters.search || '').trim(),
    expense_category: String(filters.category || '').trim(),
    expense_sort_by: String(filters.sort_by || 'expense_date'),
    expense_sort_dir: String(filters.sort_dir || 'desc'),
    expense_period: String(filters.period || 'all'),
    expense_per_page: Number(filters.per_page) || 10,
    ...(String(filters.date_from || '').trim() !== '' ? { expense_date_from: String(filters.date_from).trim() } : {}),
    ...(String(filters.date_to || '').trim() !== '' ? { expense_date_to: String(filters.date_to).trim() } : {}),
    ...(String(filters.workshop_id || '').trim() !== '' ? { expense_workshop_id: String(filters.workshop_id).trim() } : {}),
    ...(String(filters.cursor || '').trim() !== '' ? { expense_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerExpenses = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerExpense = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerExpense = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const destroyOwnerExpense = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};
