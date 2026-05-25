import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => {
    const completionSearch = String(
        filters.completion_sparepart_search
        || filters.completion_search
        || '',
    ).trim();
    const completionCursor = String(
        filters.completion_sparepart_cursor
        || filters.completion_cursor
        || '',
    ).trim();
    const completionCategory = String(
        filters.completion_sparepart_category
        || filters.completion_category
        || '',
    ).trim();
    const completionPerPage = Number(filters.completion_sparepart_per_page || filters.completion_per_page) || 20;

    return {
        order_search: String(filters.search || '').trim(),
        order_sort_by: String(filters.sort_by || 'service_date'),
        order_sort_dir: String(filters.sort_dir || 'desc'),
        order_per_page: Number(filters.per_page) || 10,
        ...(String(filters.cursor || '').trim() !== '' ? { order_cursor: String(filters.cursor).trim() } : {}),
        ...(completionSearch !== '' ? { completion_sparepart_search: completionSearch } : {}),
        ...(completionCategory !== '' ? { completion_sparepart_category: completionCategory } : {}),
        ...(completionCursor !== '' ? { completion_sparepart_cursor: completionCursor } : {}),
        ...(completionPerPage > 0 ? { completion_sparepart_per_page: completionPerPage } : {}),
    };
};

export const fetchOwnerServiceOrders = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerServiceOrder = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const storeOwnerServiceOrderEstimate = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

export const generateOwnerServiceOrderEstimateAiDraft = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

export const generateOwnerServiceOrderDiagnosisAiDraft = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

export const updateOwnerServiceOrderStatus = (path, payload, options = {}) => {
    const body = typeof payload === 'string'
        ? { status: payload }
        : (payload && typeof payload === 'object' ? payload : {});

    router.patch(path, body, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};
