import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    expense_category_search: String(filters.search || '').trim(),
    expense_category_sort_by: String(filters.sort_by || 'name'),
    expense_category_sort_dir: String(filters.sort_dir || 'asc'),
    expense_category_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { expense_category_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerExpenseCategories = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerExpenseCategory = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerExpenseCategory = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const destroyOwnerExpenseCategory = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};
