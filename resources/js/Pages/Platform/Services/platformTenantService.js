import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    tenant_search: String(filters.search || '').trim(),
    tenant_sort_by: String(filters.sort_by || 'created_at'),
    tenant_sort_dir: String(filters.sort_dir || 'desc'),
    tenant_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { tenant_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchPlatformTenants = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const exportPlatformTenants = (path, filters = {}) => {
    if (typeof window === 'undefined') {
        return;
    }

    const params = new URLSearchParams(normalizeFilters(filters));
    params.delete('tenant_per_page');
    params.delete('tenant_cursor');

    const queryString = params.toString();
    window.location.assign(queryString !== '' ? `${path}?${queryString}` : path);
};
