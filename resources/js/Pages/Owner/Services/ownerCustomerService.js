import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    customer_search: String(filters.search || '').trim(),
    customer_sort_by: String(filters.sort_by || 'created_at'),
    customer_sort_dir: String(filters.sort_dir || 'desc'),
    customer_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { customer_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerCustomers = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerCustomer = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerCustomer = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const destroyOwnerCustomer = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};
