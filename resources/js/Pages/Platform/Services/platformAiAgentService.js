import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    ai_agent_search: String(filters.search || '').trim(),
    ai_agent_sort_by: String(filters.sort_by || 'priority_order'),
    ai_agent_sort_dir: String(filters.sort_dir || 'asc'),
    ai_agent_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { ai_agent_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchPlatformAiAgents = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};
