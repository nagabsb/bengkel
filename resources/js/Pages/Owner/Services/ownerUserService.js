import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    user_search: String(filters.search || '').trim(),
    user_sort_by: String(filters.sort_by || 'created_at'),
    user_sort_dir: String(filters.sort_dir || 'desc'),
    user_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { user_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerUsers = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerUser = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerUser = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const destroyOwnerUser = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

