<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerWarehouseTableCard from './Warehouses/Components/OwnerWarehouseTableCard.vue';
import OwnerWarehouseFormCard from './Warehouses/Components/OwnerWarehouseFormCard.vue';
import {
    destroyOwnerWarehouse,
    fetchOwnerWarehouses,
    storeOwnerWarehouse,
    updateOwnerWarehouse,
} from './Services/ownerWarehouseService';

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
    warehouses: {
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
    warehouseFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    warehouseSummary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
        }),
    },
});

const page = usePage();
const logoutForm = useForm({});
const warehouseForm = useForm({
    workshop_id: '',
    name: '',
    code: '',
    address: '',
    notes: '',
    is_active: true,
});

const tableLoading = ref(false);
const tableFilters = ref({
    search: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});

const editingWarehouseId = ref(null);
const deletingWarehouseId = ref(null);
const isWarehouseModalOpen = ref(false);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const isEditMode = computed(() => String(editingWarehouseId.value || '').trim() !== '');
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const warehousesPath = computed(() => `${baseOwnerPath.value}/warehouses`);
const warehouseStorePath = computed(() => `${baseOwnerPath.value}/warehouses`);
const warehouseUpdatePath = (warehouseId) => `${baseOwnerPath.value}/warehouses/${warehouseId}`;
const warehouseDeletePath = (warehouseId) => `${baseOwnerPath.value}/warehouses/${warehouseId}`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const warehouseError = computed(() => String(
    warehouseForm.errors?.create_warehouse
    || warehouseForm.errors?.update_warehouse
    || warehouseForm.errors?.delete_warehouse
    || pageErrors.value?.create_warehouse
    || pageErrors.value?.update_warehouse
    || pageErrors.value?.delete_warehouse
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageWarehouses = computed(() => (
    permissionNames.value.includes('warehouses.manage')
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

const resolveDefaultWorkshopId = () => {
    if (activeWorkshopId.value !== '' && activeWorkshopId.value !== '__all__') {
        return activeWorkshopId.value;
    }

    return String(workshopOptions.value[0]?.value || '').trim();
};

watch(
    () => props.warehouseFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
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

    fetchOwnerWarehouses(warehousesPath.value, nextFilters, {
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

const resetWarehouseForm = () => {
    editingWarehouseId.value = null;
    warehouseForm.clearErrors();
    warehouseForm.workshop_id = resolveDefaultWorkshopId();
    warehouseForm.name = '';
    warehouseForm.code = '';
    warehouseForm.address = '';
    warehouseForm.notes = '';
    warehouseForm.is_active = true;
};

const openCreateWarehouseModal = () => {
    if (!canManageWarehouses.value) {
        return;
    }

    resetWarehouseForm();
    isWarehouseModalOpen.value = true;
};

const startEditWarehouse = (warehouse) => {
    if (!canManageWarehouses.value) {
        return;
    }

    const warehouseId = String(warehouse?.id || '').trim();
    if (warehouseId === '') {
        return;
    }

    editingWarehouseId.value = warehouseId;
    warehouseForm.clearErrors();
    warehouseForm.workshop_id = String(warehouse?.workshop_id || '').trim() || resolveDefaultWorkshopId();
    warehouseForm.name = String(warehouse?.name || '');
    warehouseForm.code = String(warehouse?.code || '');
    warehouseForm.address = String(warehouse?.address || '');
    warehouseForm.notes = String(warehouse?.notes || '');
    warehouseForm.is_active = Boolean(warehouse?.is_active);
    isWarehouseModalOpen.value = true;
};

const closeWarehouseModal = () => {
    isWarehouseModalOpen.value = false;
    resetWarehouseForm();
};

const submitWarehouseForm = () => {
    const payload = (data) => ({
        ...data,
        workshop_id: String(data.workshop_id || '').trim(),
        name: String(data.name || '').trim(),
        code: String(data.code || '').trim(),
        address: String(data.address || '').trim(),
        notes: String(data.notes || '').trim(),
        is_active: Boolean(data.is_active),
    });

    if (isEditMode.value) {
        updateOwnerWarehouse(
            warehouseForm.transform(payload),
            warehouseUpdatePath(editingWarehouseId.value),
            {
                onSuccess: closeWarehouseModal,
            },
        );

        return;
    }

    storeOwnerWarehouse(
        warehouseForm.transform(payload),
        warehouseStorePath.value,
        {
            onSuccess: closeWarehouseModal,
        },
    );
};

const deleteWarehouse = (warehouse) => {
    if (!canManageWarehouses.value) {
        return;
    }

    const warehouseId = String(warehouse?.id || '').trim();
    if (warehouseId === '') {
        return;
    }

    if (!window.confirm('Hapus gudang ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    destroyOwnerWarehouse(warehouseDeletePath(warehouseId), {
        onStart: () => {
            deletingWarehouseId.value = warehouseId;
        },
        onFinish: () => {
            deletingWarehouseId.value = null;
        },
    });
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
    isWarehouseModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isWarehouseModalOpen.value) {
        closeWarehouseModal();
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
    <Head title="Gudang Owner" />

    <AppDashboardLayout
        title="Gudang"
        subtitle="Kelola master gudang per cabang aktif"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <OwnerWarehouseTableCard
                :warehouses="warehouses"
                :filters="tableFilters"
                :warehouse-summary="warehouseSummary"
                :active-workshop="activeWorkshop"
                :flash-status="flashStatus"
                :error-message="warehouseError"
                :table-loading="tableLoading"
                :form-processing="warehouseForm.processing"
                :deleting-warehouse-id="deletingWarehouseId"
                :can-manage="canManageWarehouses"
                @create="openCreateWarehouseModal"
                @edit="startEditWarehouse"
                @delete="deleteWarehouse"
                @search="handleSearch"
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
                v-if="isWarehouseModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeWarehouseModal"
                />

                <div class="relative z-20 w-full max-w-xl">
                    <OwnerWarehouseFormCard
                        :is-edit-mode="isEditMode"
                        :form="warehouseForm"
                        :errors="pageErrors"
                        :workshop-options="workshopOptions"
                        :is-workshop-selectable="isGlobalWorkshopFilter"
                        @close="closeWarehouseModal"
                        @submit="submitWarehouseForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>

