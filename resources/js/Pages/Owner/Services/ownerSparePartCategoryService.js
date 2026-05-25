import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    sparepart_category_search: String(filters.search || '').trim(),
    sparepart_category_sort_by: String(filters.sort_by || 'created_at'),
    sparepart_category_sort_dir: String(filters.sort_dir || 'desc'),
    sparepart_category_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { sparepart_category_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerSparePartCategories = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerSparePartCategory = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerSparePartCategory = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const destroyOwnerSparePartCategory = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

