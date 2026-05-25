import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    sparepart_search: String(filters.search || '').trim(),
    ...(String(filters.supplier_id || '').trim() !== '' ? { sparepart_supplier_id: String(filters.supplier_id).trim() } : {}),
    ...(String(filters.warehouse_id || '').trim() !== '' ? { sparepart_warehouse_id: String(filters.warehouse_id).trim() } : {}),
    sparepart_sort_by: String(filters.sort_by || 'created_at'),
    sparepart_sort_dir: String(filters.sort_dir || 'desc'),
    sparepart_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { sparepart_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerSpareparts = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerSparepart = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerSparepart = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const destroyOwnerSparepart = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};
