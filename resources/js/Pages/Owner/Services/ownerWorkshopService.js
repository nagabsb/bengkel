import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    workshop_search: String(filters.search || '').trim(),
    workshop_sort_by: String(filters.sort_by || 'created_at'),
    workshop_sort_dir: String(filters.sort_dir || 'desc'),
    workshop_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { workshop_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerWorkshops = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerWorkshop = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerWorkshop = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const switchOwnerWorkshopPlan = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

export const destroyOwnerWorkshop = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

