import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    brand_search: String(filters.search || '').trim(),
    brand_sort_by: String(filters.sort_by || 'name'),
    brand_sort_dir: String(filters.sort_dir || 'asc'),
    brand_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { brand_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchPlatformVehicleMasters = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const syncPlatformVehicleMasters = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const importPlatformVehicleMasters = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        forceFormData: true,
        ...options,
    });
};
