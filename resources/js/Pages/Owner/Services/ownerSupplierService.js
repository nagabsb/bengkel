import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    supplier_search: String(filters.search || '').trim(),
    supplier_sort_by: String(filters.sort_by || 'created_at'),
    supplier_sort_dir: String(filters.sort_dir || 'desc'),
    supplier_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { supplier_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerSuppliers = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerSupplier = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerSupplier = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const destroyOwnerSupplier = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

