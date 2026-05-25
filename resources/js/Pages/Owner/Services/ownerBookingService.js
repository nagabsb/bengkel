import { router } from '@inertiajs/vue3';

const normalizeFilters = (filters = {}) => ({
    booking_search: String(filters.search || '').trim(),
    booking_status: String(filters.status || '').trim(),
    booking_sort_by: String(filters.sort_by || 'created_at'),
    booking_sort_dir: String(filters.sort_dir || 'desc'),
    booking_per_page: Number(filters.per_page) || 10,
    ...(String(filters.workshop_id || '').trim() !== '' ? { booking_workshop_id: String(filters.workshop_id).trim() } : {}),
    ...(String(filters.cursor || '').trim() !== '' ? { booking_cursor: String(filters.cursor).trim() } : {}),
});

export const fetchOwnerBookings = (path, filters = {}, options = {}) => {
    router.get(path, normalizeFilters(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

export const storeOwnerBooking = (form, path, options = {}) => {
    form.post(path, {
        preserveScroll: true,
        ...options,
    });
};

export const updateOwnerBookingStatus = (path, status, options = {}) => {
    router.patch(path, { status }, {
        preserveScroll: true,
        preserveState: true,
        ...options,
    });
};
