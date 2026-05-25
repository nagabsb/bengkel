import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    vehicle_search: String(filters.search || '').trim(),
    vehicle_type: String(filters.vehicle_type || '').trim(),
    vehicle_brand: String(filters.vehicle_brand || '').trim(),
    vehicle_sort_by: String(filters.sort_by || 'created_at'),
    vehicle_sort_dir: String(filters.sort_dir || 'desc'),
    vehicle_per_page: Number(filters.per_page) || 10,
    ...(String(filters.cursor || '').trim() !== '' ? { vehicle_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerVehicles = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerVehicle = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerVehicle = (form, path, options = {}) => {
    form.patch(path, {
        preserveScroll: true,
        ...options,
    });
};

export const destroyOwnerVehicle = (path, options = {}) => {
    router.delete(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};

export const syncOwnerVehiclesFromPlatform = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};
