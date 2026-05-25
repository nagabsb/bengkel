import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    sparepart_unit_search: String(filters.search || '').trim(),
    sparepart_unit_sort_by: String(filters.sort_by || 'created_at'),
    sparepart_unit_sort_dir: String(filters.sort_dir || 'desc'),
    sparepart_unit_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { sparepart_unit_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerSparePartUnits = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerSparePartUnit = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerSparePartUnit = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const destroyOwnerSparePartUnit = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

