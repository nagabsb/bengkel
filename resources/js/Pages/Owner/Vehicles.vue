<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerVehicleTableCard from './Vehicles/Components/OwnerVehicleTableCard.vue';
import OwnerVehicleFormCard from './Vehicles/Components/OwnerVehicleFormCard.vue';
import {
    destroyOwnerVehicle,
    fetchOwnerVehicles,
    storeOwnerVehicle,
    syncOwnerVehiclesFromPlatform,
    updateOwnerVehicle,
} from './Services/ownerVehicleService';

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
    vehicles: {
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
    vehicleFilters: {
        type: Object,
        default: () => ({
            search: '',
            vehicle_type: '',
            vehicle_brand: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    vehicleSummary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
            motor: 0,
            mobil: 0,
        }),
    },
    vehicleBrandOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const logoutForm = useForm({});
const vehicleForm = useForm({
    vehicle_type: 'motor',
    brand: '',
    model: '',
    is_active: true,
});
const syncForm = useForm({});

const tableLoading = ref(false);
const tableFilters = ref({
    search: '',
    vehicle_type: '',
    vehicle_brand: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});

const editingVehicleId = ref(null);
const deletingVehicleId = ref(null);
const isVehicleModalOpen = ref(false);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const isEditMode = computed(() => String(editingVehicleId.value || '').trim() !== '');
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const vehiclesPath = computed(() => `${baseOwnerPath.value}/vehicles`);
const vehicleStorePath = computed(() => `${baseOwnerPath.value}/vehicles`);
const vehicleUpdatePath = (vehicleId) => `${baseOwnerPath.value}/vehicles/${vehicleId}`;
const vehicleDeletePath = (vehicleId) => `${baseOwnerPath.value}/vehicles/${vehicleId}`;
const vehicleSyncPath = computed(() => `${baseOwnerPath.value}/vehicles/sync`);

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const vehicleError = computed(() => String(
    vehicleForm.errors?.create_vehicle
    || vehicleForm.errors?.update_vehicle
    || vehicleForm.errors?.delete_vehicle
    || pageErrors.value?.create_vehicle
    || pageErrors.value?.update_vehicle
    || pageErrors.value?.delete_vehicle
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageVehicles = computed(() => permissionNames.value.includes('service_orders.manage'));

watch(
    () => props.vehicleFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            vehicle_type: String(filters?.vehicle_type || ''),
            vehicle_brand: String(filters?.vehicle_brand || ''),
            sort_by: String(filters?.sort_by || 'created_at'),
            sort_dir: String(filters?.sort_dir || 'desc'),
            per_page: Number(filters?.per_page) || 10,
            cursor: filters?.cursor ? String(filters.cursor) : null,
        };
    },
    {
        immediate: true,
        deep: true,
    },
);

const requestTable = (override = {}) => {
    const nextFilters = {
        ...tableFilters.value,
        ...override,
    };

    tableFilters.value = nextFilters;

    fetchOwnerVehicles(vehiclesPath.value, nextFilters, {
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

const handleSort = ({ key, direction }) => {
    requestTable({
        sort_by: key,
        sort_dir: direction,
        cursor: null,
    });
};

const handleTypeFilter = (vehicleType) => {
    requestTable({
        vehicle_type: String(vehicleType || '').trim(),
        vehicle_brand: '',
        cursor: null,
    });
};

const handleBrandFilter = (vehicleBrand) => {
    requestTable({
        vehicle_brand: String(vehicleBrand || '').trim(),
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

const resetVehicleForm = () => {
    editingVehicleId.value = null;
    vehicleForm.clearErrors();
    vehicleForm.vehicle_type = 'motor';
    vehicleForm.brand = '';
    vehicleForm.model = '';
    vehicleForm.is_active = true;
};

const openCreateVehicleModal = () => {
    if (!canManageVehicles.value) {
        return;
    }

    resetVehicleForm();
    isVehicleModalOpen.value = true;
};

const startEditVehicle = (vehicle) => {
    if (!canManageVehicles.value) {
        return;
    }

    const vehicleId = String(vehicle?.id || '').trim();
    if (vehicleId === '') {
        return;
    }

    editingVehicleId.value = vehicleId;
    vehicleForm.clearErrors();
    vehicleForm.vehicle_type = String(vehicle?.vehicle_type || 'motor').trim().toLowerCase() === 'mobil' ? 'mobil' : 'motor';
    vehicleForm.brand = String(vehicle?.brand || '');
    vehicleForm.model = String(vehicle?.model || '');
    vehicleForm.is_active = Boolean(vehicle?.is_active);
    isVehicleModalOpen.value = true;
};

const closeVehicleModal = () => {
    isVehicleModalOpen.value = false;
    resetVehicleForm();
};

const submitVehicleForm = () => {
    const payload = (data) => ({
        ...data,
        vehicle_type: String(data.vehicle_type || 'motor').trim().toLowerCase() === 'mobil' ? 'mobil' : 'motor',
        brand: String(data.brand || '').trim(),
        model: String(data.model || '').trim(),
        is_active: Boolean(data.is_active),
    });

    if (isEditMode.value) {
        updateOwnerVehicle(
            vehicleForm.transform(payload),
            vehicleUpdatePath(editingVehicleId.value),
            {
                onSuccess: closeVehicleModal,
            },
        );

        return;
    }

    storeOwnerVehicle(
        vehicleForm.transform(payload),
        vehicleStorePath.value,
        {
            onSuccess: closeVehicleModal,
        },
    );
};

const deleteVehicle = (vehicle) => {
    if (!canManageVehicles.value) {
        return;
    }

    const vehicleId = String(vehicle?.id || '').trim();
    if (vehicleId === '') {
        return;
    }

    if (!window.confirm('Hapus master kendaraan ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    destroyOwnerVehicle(vehicleDeletePath(vehicleId), {
        onStart: () => {
            deletingVehicleId.value = vehicleId;
        },
        onFinish: () => {
            deletingVehicleId.value = null;
        },
    });
};

const syncFromPlatform = () => {
    if (!canManageVehicles.value) {
        return;
    }

    syncOwnerVehiclesFromPlatform(syncForm, vehicleSyncPath.value);
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
    isVehicleModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isVehicleModalOpen.value) {
        closeVehicleModal();
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
    <Head title="Kendaraan Owner" />

    <AppDashboardLayout
        title="Kendaraan"
        subtitle="Kelola master kendaraan tenant agar input servis lebih konsisten"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <OwnerVehicleTableCard
                :vehicles="vehicles"
                :filters="tableFilters"
                :vehicle-summary="vehicleSummary"
                :vehicle-brand-options="vehicleBrandOptions"
                :flash-status="flashStatus"
                :error-message="vehicleError"
                :table-loading="tableLoading"
                :form-processing="vehicleForm.processing"
                :deleting-vehicle-id="deletingVehicleId"
                :syncing-master="syncForm.processing"
                :can-manage="canManageVehicles"
                @create="openCreateVehicleModal"
                @edit="startEditVehicle"
                @delete="deleteVehicle"
                @sync="syncFromPlatform"
                @search="handleSearch"
                @filter-type="handleTypeFilter"
                @filter-brand="handleBrandFilter"
                @sort="handleSort"
                @per-page="handlePerPage"
                @page="handlePage"
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
                v-if="isVehicleModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeVehicleModal"
                />

                <div class="relative z-20 w-full max-w-2xl">
                    <OwnerVehicleFormCard
                        :is-edit-mode="isEditMode"
                        :form="vehicleForm"
                        :errors="pageErrors"
                        @close="closeVehicleModal"
                        @submit="submitVehicleForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>

