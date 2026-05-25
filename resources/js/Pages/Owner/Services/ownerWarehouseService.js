import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    warehouse_search: String(filters.search || '').trim(),
    warehouse_sort_by: String(filters.sort_by || 'created_at'),
    warehouse_sort_dir: String(filters.sort_dir || 'desc'),
    warehouse_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { warehouse_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerWarehouses = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerWarehouse = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerWarehouse = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const destroyOwnerWarehouse = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};
