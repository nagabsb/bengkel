import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    tab: String(filters.tab || 'permissions'),
    permission_search: String(filters.search || '').trim(),
    permission_sort_by: String(filters.sort_by || 'menu_label'),
    permission_sort_dir: String(filters.sort_dir || 'asc'),
    permission_per_page: Number(filters.per_page) || 50,
    ...(String(filters.cursor || '').trim() !== '' ? { permission_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerPermissions = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const syncOwnerRolePermissions = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerPrintSetting = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};
