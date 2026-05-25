<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerBookingFormCard from './Bookings/Components/OwnerBookingFormCard.vue';
import OwnerBookingTableCard from './Bookings/Components/OwnerBookingTableCard.vue';
import {
    fetchOwnerBookings,
    storeOwnerBooking,
    updateOwnerBookingStatus,
} from './Services/ownerBookingService';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    tenantId: {
        type: String,
        default: '',
    },
    package: {
        type: Object,
        default: null,
    },
    menuItems: {
        type: Array,
        default: () => [],
    },
    activeWorkshop: {
        type: Object,
        default: () => ({
            id: '',
            name: '',
            code: '',
        }),
    },
    bookings: {
        type: Object,
        default: () => ({
            mode: 'cursor',
            data: [],
            per_page: 10,
            total: 0,
            from: 0,
            to: 0,
            current_cursor: null,
            next_cursor: null,
            prev_cursor: null,
            has_more_pages: false,
        }),
    },
    bookingFilters: {
        type: Object,
        default: () => ({
            search: '',
            status: 'active',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
            workshop_id: '',
        }),
    },
    bookingSummary: {
        type: Object,
        default: () => ({
            total: 0,
            queued: 0,
            in_service: 0,
            completed: 0,
            cancelled: 0,
        }),
    },
    customerOptions: {
        type: Array,
        default: () => [],
    },
    customerVehicleOptions: {
        type: Array,
        default: () => [],
    },
    vehicleMasterOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const logoutForm = useForm({});
const bookingForm = useForm({
    workshop_id: '',
    customer_id: null,
    customer_vehicle_id: null,
    vehicle_master_id: null,
    vehicle_type: 'motor',
    vehicle_brand: '',
    vehicle_model: '',
    vehicle_plate_number: '',
    booking_date: new Date(),
    booking_time: null,
    customer_name: '',
    customer_phone: '',
    complaint: '',
    notes: '',
});
const newCustomerOptionValue = '__new_customer__';
const newVehicleOptionValue = '__new_vehicle__';

const tableLoading = ref(false);
const tableFilters = ref({
    search: '',
    status: 'active',
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
    workshop_id: '',
});

const isBookingModalOpen = ref(false);
const statusProcessingBookingId = ref('');
const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const bookingsPath = computed(() => `${baseOwnerPath.value}/bookings`);
const bookingStorePath = computed(() => `${baseOwnerPath.value}/bookings`);
const bookingStatusPath = (bookingId) => `${baseOwnerPath.value}/bookings/${bookingId}/status`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const bookingError = computed(() => String(
    bookingForm.errors?.create_booking
    || bookingForm.errors?.update_booking_status
    || pageErrors.value?.create_booking
    || pageErrors.value?.update_booking_status
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageBookings = computed(() => (
    permissionNames.value.includes('bookings.manage')
    || permissionNames.value.includes('users.manage')
));
const workshopSwitcher = computed(() => {
    const payload = page.props?.ownerWorkshopSwitcher;
    return payload && typeof payload === 'object' ? payload : null;
});
const activeWorkshopId = computed(() => String(workshopSwitcher.value?.active_workshop_id || '').trim());
const isGlobalWorkshopFilter = computed(() => activeWorkshopId.value === '__all__');
const workshopOptions = computed(() => (
    Array.isArray(workshopSwitcher.value?.workshops)
        ? workshopSwitcher.value.workshops
            .map((workshop) => ({
                value: String(workshop?.id || '').trim(),
                label: String(workshop?.name || '').trim(),
                subtitle: String(workshop?.code || '').trim(),
                is_all: Boolean(workshop?.is_all),
            }))
            .filter((workshop) => workshop.value !== '' && workshop.label !== '' && !workshop.is_all)
        : []
));
const selectedWorkshopId = computed(() => String(bookingForm.workshop_id || '').trim());
const scopedCustomerOptions = computed(() => (
    Array.isArray(props.customerOptions)
        ? props.customerOptions.filter((customer) => {
            if (selectedWorkshopId.value === '') {
                return true;
            }

            return String(customer?.workshop_id || '').trim() === selectedWorkshopId.value;
        })
        : []
));
const scopedCustomerVehicleOptions = computed(() => (
    Array.isArray(props.customerVehicleOptions)
        ? props.customerVehicleOptions.filter((vehicle) => {
            const selectedCustomerId = String(bookingForm.customer_id || '').trim();
            if (selectedCustomerId === '' || selectedCustomerId === newCustomerOptionValue) {
                return false;
            }

            const matchesCustomer = String(vehicle?.customer_id || '').trim() === selectedCustomerId;
            if (!matchesCustomer) {
                return false;
            }

            if (selectedWorkshopId.value === '') {
                return true;
            }

            return String(vehicle?.workshop_id || '').trim() === selectedWorkshopId.value;
        })
        : []
));

const resolveDefaultWorkshopId = () => {
    if (activeWorkshopId.value !== '' && activeWorkshopId.value !== '__all__') {
        return activeWorkshopId.value;
    }

    return String(workshopOptions.value[0]?.value || '').trim();
};

watch(
    () => props.bookingFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            status: String(filters?.status ?? 'active'),
            sort_by: String(filters?.sort_by || 'created_at'),
            sort_dir: String(filters?.sort_dir || 'desc'),
            per_page: Number(filters?.per_page) || 10,
            cursor: filters?.cursor ? String(filters.cursor) : null,
            workshop_id: String(filters?.workshop_id || ''),
        };
    },
    {
        immediate: true,
        deep: true,
    },
);

watch(
    selectedWorkshopId,
    () => {
        const selectedCustomerId = String(bookingForm.customer_id || '').trim();
        if (selectedCustomerId === '' || selectedCustomerId === newCustomerOptionValue) {
            return;
        }

        const customerStillAvailable = scopedCustomerOptions.value.some((customer) => (
            String(customer?.id || '').trim() === selectedCustomerId
        ));

        if (!customerStillAvailable) {
            bookingForm.customer_id = null;
            bookingForm.customer_vehicle_id = null;
            bookingForm.vehicle_master_id = null;
            bookingForm.vehicle_type = 'motor';
            bookingForm.vehicle_brand = '';
            bookingForm.vehicle_model = '';
            bookingForm.vehicle_plate_number = '';
            bookingForm.customer_name = '';
            bookingForm.customer_phone = '';
        }
    },
);

const requestTable = (override = {}) => {
    const nextFilters = {
        ...tableFilters.value,
        ...override,
    };

    tableFilters.value = nextFilters;

    fetchOwnerBookings(bookingsPath.value, nextFilters, {
        onStart: () => {
            tableLoading.value = true;
        },
        onFinish: () => {
            tableLoading.value = false;
        },
    });
};

const handleSearch = (search) => {
    requestTable({
        search,
        cursor: null,
    });
};

const handleStatusFilter = (status) => {
    requestTable({
        status: String(status || '').trim(),
        cursor: null,
    });
};

const handleWorkshopFilter = (workshopId) => {
    requestTable({
        workshop_id: String(workshopId || '').trim(),
        cursor: null,
    });
};

const handleSort = ({ key, direction }) => {
    requestTable({
        sort_by: key,
        sort_dir: direction,
        cursor: null,
    });
};

const handlePerPage = (perPage) => {
    requestTable({
        per_page: perPage,
        cursor: null,
    });
};

const handlePage = (payload) => {
    if (payload && typeof payload === 'object' && payload.type === 'cursor') {
        requestTable({
            cursor: String(payload.cursor || ''),
        });
    }
};

const resetBookingForm = () => {
    bookingForm.clearErrors();
    bookingForm.workshop_id = resolveDefaultWorkshopId();
    bookingForm.customer_id = null;
    bookingForm.customer_vehicle_id = null;
    bookingForm.vehicle_master_id = null;
    bookingForm.vehicle_type = 'motor';
    bookingForm.vehicle_brand = '';
    bookingForm.vehicle_model = '';
    bookingForm.vehicle_plate_number = '';
    bookingForm.booking_date = new Date();
    bookingForm.booking_time = null;
    bookingForm.customer_name = '';
    bookingForm.customer_phone = '';
    bookingForm.complaint = '';
    bookingForm.notes = '';
};

const openCreateBookingModal = () => {
    if (!canManageBookings.value) {
        return;
    }

    resetBookingForm();
    isBookingModalOpen.value = true;
};

const closeBookingModal = () => {
    isBookingModalOpen.value = false;
    resetBookingForm();
};

const formatDateForBackend = (value) => {
    const parsedDate = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(parsedDate.getTime())) {
        return '';
    }

    const year = parsedDate.getFullYear();
    const month = String(parsedDate.getMonth() + 1).padStart(2, '0');
    const day = String(parsedDate.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const formatTimeForBackend = (value) => {
    if (typeof value === 'string') {
        const normalized = value.trim();
        if (normalized === '') {
            return '';
        }

        const matches = normalized.match(/^(\d{1,2}):(\d{2})$/);
        if (!matches) {
            return '';
        }

        const hours = Math.min(Math.max(Number(matches[1]), 0), 23);
        const minutes = Math.min(Math.max(Number(matches[2]), 0), 59);

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
    }

    if (value instanceof Date && !Number.isNaN(value.getTime())) {
        return `${String(value.getHours()).padStart(2, '0')}:${String(value.getMinutes()).padStart(2, '0')}`;
    }

    if (value && typeof value === 'object') {
        const hours = Number(value.hours);
        const minutes = Number(value.minutes);

        if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
            return '';
        }

        const normalizedHours = Math.min(Math.max(hours, 0), 23);
        const normalizedMinutes = Math.min(Math.max(minutes, 0), 59);

        return `${String(normalizedHours).padStart(2, '0')}:${String(normalizedMinutes).padStart(2, '0')}`;
    }

    return '';
};

const submitBookingForm = () => {
    const selectedCustomerId = String(bookingForm.customer_id || '').trim();
    if (selectedCustomerId === '') {
        bookingForm.setError('customer_id', 'Pilih pelanggan atau pilih "Tambah pelanggan baru".');
        return;
    }

    bookingForm.clearErrors('customer_id');

    const payload = (data) => {
        const normalizedCustomerId = String(data.customer_id || '').trim();
        const isNewCustomer = normalizedCustomerId === newCustomerOptionValue;
        const selectedCustomerId = !isNewCustomer && normalizedCustomerId !== ''
            ? normalizedCustomerId
            : '';
        const normalizedCustomerVehicleId = String(data.customer_vehicle_id || '').trim();
        const isNewVehicleFromSelect = normalizedCustomerVehicleId === newVehicleOptionValue;
        const hasManualVehicleInput = (
            String(data.vehicle_master_id || '').trim() !== ''
            || String(data.vehicle_brand || '').trim() !== ''
            || String(data.vehicle_model || '').trim() !== ''
            || String(data.vehicle_plate_number || '').trim() !== ''
        );
        const shouldSendNewVehiclePayload = isNewVehicleFromSelect || (isNewCustomer && hasManualVehicleInput);
        const selectedCustomer = selectedCustomerId !== ''
            ? scopedCustomerOptions.value.find((customer) => (
                String(customer?.id || '').trim() === selectedCustomerId
            ))
            : null;

        return {
            workshop_id: String(data.workshop_id || '').trim(),
            customer_id: selectedCustomerId !== '' ? selectedCustomerId : null,
            customer_vehicle_id: selectedCustomerId !== '' && !isNewVehicleFromSelect
                ? (normalizedCustomerVehicleId || null)
                : null,
            vehicle_master_id: shouldSendNewVehiclePayload
                ? (String(data.vehicle_master_id || '').trim() || null)
                : null,
            vehicle_type: shouldSendNewVehiclePayload
                ? String(data.vehicle_type || 'motor').trim().toLowerCase()
                : null,
            vehicle_brand: shouldSendNewVehiclePayload
                ? String(data.vehicle_brand || '').trim()
                : null,
            vehicle_model: shouldSendNewVehiclePayload
                ? String(data.vehicle_model || '').trim()
                : null,
            vehicle_plate_number: shouldSendNewVehiclePayload
                ? String(data.vehicle_plate_number || '').trim().toUpperCase()
                : null,
            booking_date: formatDateForBackend(data.booking_date),
            booking_time: formatTimeForBackend(data.booking_time),
            customer_name: String(
                isNewCustomer
                    ? (data.customer_name || '')
                    : (selectedCustomer?.name || data.customer_name || ''),
            ).trim(),
            customer_phone: String(
                isNewCustomer
                    ? (data.customer_phone || '')
                    : (selectedCustomer?.phone || data.customer_phone || ''),
            ).trim(),
            complaint: String(data.complaint || '').trim(),
            notes: String(data.notes || '').trim(),
        };
    };

    storeOwnerBooking(
        bookingForm.transform(payload),
        bookingStorePath.value,
        {
            onSuccess: closeBookingModal,
        },
    );
};

const updateBookingStatusAction = (booking, status, confirmationMessage = '') => {
    if (!canManageBookings.value) {
        return;
    }

    const bookingId = String(booking?.id || '').trim();
    if (bookingId === '') {
        return;
    }

    if (confirmationMessage !== '' && !window.confirm(confirmationMessage)) {
        return;
    }

    const normalizedStatus = String(status || '').trim().toLowerCase();
    updateOwnerBookingStatus(bookingStatusPath(bookingId), normalizedStatus, {
        onStart: () => {
            statusProcessingBookingId.value = bookingId;
        },
        onFinish: () => {
            statusProcessingBookingId.value = '';
        },
    });
};

const startService = (booking) => {
    updateBookingStatusAction(booking, 'in_service');
};

const resolvePageScrollContainer = () => {
    if (!pageContentRef.value || typeof pageContentRef.value.closest !== 'function') {
        if (typeof document === 'undefined') {
            return null;
        }

        const fallbackContainer = document.querySelector('.dashboard-scroll');
        if (fallbackContainer instanceof HTMLElement) {
            return fallbackContainer;
        }

        return document.documentElement instanceof HTMLElement ? document.documentElement : null;
    }

    const container = pageContentRef.value.closest('.dashboard-scroll');
    if (container instanceof HTMLElement) {
        return container;
    }

    if (typeof document === 'undefined') {
        return null;
    }

    const fallbackContainer = document.querySelector('.dashboard-scroll');
    if (fallbackContainer instanceof HTMLElement) {
        return fallbackContainer;
    }

    return document.documentElement instanceof HTMLElement ? document.documentElement : null;
};

const setPageScrollLock = (isLocked) => {
    const container = lockedScrollContainer.value ?? resolvePageScrollContainer();
    if (!(container instanceof HTMLElement)) {
        return;
    }

    if (isLocked) {
        lockedScrollContainer.value = container;
        previousOverflow.value = container.style.overflow;
        previousOverflowY.value = container.style.overflowY;
        container.style.overflow = 'hidden';
        container.style.overflowY = 'hidden';
        return;
    }

    container.style.overflow = previousOverflow.value;
    container.style.overflowY = previousOverflowY.value;
    previousOverflow.value = '';
    previousOverflowY.value = '';
    lockedScrollContainer.value = null;
};

watch(
    isBookingModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isBookingModalOpen.value) {
        closeBookingModal();
    }
};

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEscapeKey);
    setPageScrollLock(false);
});

onMounted(() => {
    window.addEventListener('keydown', handleEscapeKey);
});

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Booking Servis Owner" />

    <AppDashboardLayout
        title="Booking Servis"
        subtitle="Atur jadwal, nomor antrian, dan progres layanan pelanggan"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <OwnerBookingTableCard
                :bookings="bookings"
                :filters="tableFilters"
                :booking-summary="bookingSummary"
                :active-workshop="activeWorkshop"
                :workshop-options="workshopOptions"
                :is-global-workshop-filter="isGlobalWorkshopFilter"
                :flash-status="flashStatus"
                :error-message="bookingError"
                :table-loading="tableLoading"
                :form-processing="bookingForm.processing"
                :status-processing-booking-id="statusProcessingBookingId"
                :can-manage="canManageBookings"
                @create="openCreateBookingModal"
                @search="handleSearch"
                @status="handleStatusFilter"
                @workshop="handleWorkshopFilter"
                @sort="handleSort"
                @per-page="handlePerPage"
                @page="handlePage"
                @start-service="startService"
            />
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isBookingModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeBookingModal"
                />

                <div class="relative z-20 w-full max-w-2xl">
                    <OwnerBookingFormCard
                        :form="bookingForm"
                        :errors="pageErrors"
                        :customer-options="scopedCustomerOptions"
                        :filtered-vehicle-options="scopedCustomerVehicleOptions"
                        :vehicle-master-options="vehicleMasterOptions"
                        :workshop-options="workshopOptions"
                        :is-workshop-selectable="isGlobalWorkshopFilter"
                        @close="closeBookingModal"
                        @submit="submitBookingForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>

