import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    plan_search: String(filters.search || '').trim(),
    plan_sort_by: String(filters.sort_by || 'price'),
    plan_sort_dir: String(filters.sort_dir || 'asc'),
    plan_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { plan_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchPlatformPlans = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};


