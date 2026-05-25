<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerSparepartTableCard from './Spareparts/Components/OwnerSparepartTableCard.vue';
import OwnerSparepartFormCard from './Spareparts/Components/OwnerSparepartFormCard.vue';
import {
    destroyOwnerSparepart,
    fetchOwnerSpareparts,
    storeOwnerSparepart,
    updateOwnerSparepart,
} from './Services/ownerSparepartService';

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
    spareparts: {
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
    sparePartFilters: {
        type: Object,
        default: () => ({
            search: '',
            supplier_id: null,
            warehouse_id: null,
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    sparePartSummary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
            low_stock: 0,
        }),
    },
    supplierOptions: {
        type: Array,
        default: () => [],
    },
    warehouseOptions: {
        type: Array,
        default: () => [],
    },
    sparePartCategoryOptions: {
        type: Array,
        default: () => [],
    },
    sparePartUnitOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const logoutForm = useForm({});
const sparePartForm = useForm({
    workshop_id: '',
    supplier_id: '',
    warehouse_id: '',
    name: '',
    sku: '',
    category: '',
    unit: '',
    purchase_price: null,
    selling_price: null,
    stock: 0,
    minimum_stock: 0,
    notes: '',
    is_active: true,
});

const tableLoading = ref(false);
const tableFilters = ref({
    search: '',
    supplier_id: null,
    warehouse_id: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});

const editingSparePartId = ref(null);
const deletingSparePartId = ref(null);
const isSparePartModalOpen = ref(false);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const isEditMode = computed(() => String(editingSparePartId.value || '').trim() !== '');
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const sparepartsPath = computed(() => `${baseOwnerPath.value}/spareparts`);
const sparepartStorePath = computed(() => `${baseOwnerPath.value}/spareparts`);
const sparepartUpdatePath = (sparePartId) => `${baseOwnerPath.value}/spareparts/${sparePartId}`;
const sparepartDeletePath = (sparePartId) => `${baseOwnerPath.value}/spareparts/${sparePartId}`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const sparePartError = computed(() => String(
    sparePartForm.errors?.create_sparepart
    || sparePartForm.errors?.update_sparepart
    || sparePartForm.errors?.delete_sparepart
    || pageErrors.value?.create_sparepart
    || pageErrors.value?.update_sparepart
    || pageErrors.value?.delete_sparepart
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageSpareparts = computed(() => (
    permissionNames.value.includes('spareparts.manage')
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

const availableWarehouseOptionsForForm = computed(() => {
    const selectedWorkshopId = String(sparePartForm.workshop_id || '').trim();

    return Array.isArray(props.warehouseOptions)
        ? props.warehouseOptions.filter((warehouse) => {
            const warehouseWorkshopId = String(warehouse?.workshop_id || '').trim();
            if (selectedWorkshopId === '') {
                return true;
            }

            return warehouseWorkshopId === selectedWorkshopId;
        })
        : [];
});

const resolveDefaultWarehouseId = () => (
    Array.isArray(availableWarehouseOptionsForForm.value) && availableWarehouseOptionsForForm.value.length > 0
        ? String(availableWarehouseOptionsForForm.value[0]?.id || '').trim()
        : ''
);
const defaultWarehouseId = computed(() => (
    resolveDefaultWarehouseId()
));

watch(
    () => props.sparePartFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            supplier_id: filters?.supplier_id ? String(filters.supplier_id) : null,
            warehouse_id: filters?.warehouse_id ? String(filters.warehouse_id) : null,
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

watch(
    () => String(sparePartForm.workshop_id || '').trim(),
    () => {
        const currentWarehouseId = String(sparePartForm.warehouse_id || '').trim();
        const isWarehouseStillValid = availableWarehouseOptionsForForm.value
            .some((warehouse) => String(warehouse?.id || '').trim() === currentWarehouseId);

        if (isWarehouseStillValid) {
            return;
        }

        sparePartForm.warehouse_id = resolveDefaultWarehouseId();
    },
);

const requestTable = (override = {}) => {
    const nextFilters = {
        ...tableFilters.value,
        ...override,
    };

    tableFilters.value = nextFilters;

    fetchOwnerSpareparts(sparepartsPath.value, nextFilters, {
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

const handleSupplierFilter = (supplierId) => {
    requestTable({
        supplier_id: String(supplierId || '').trim() || null,
        cursor: null,
    });
};

const handleWarehouseFilter = (warehouseId) => {
    requestTable({
        warehouse_id: String(warehouseId || '').trim() || null,
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

const resetSparePartForm = () => {
    editingSparePartId.value = null;
    sparePartForm.clearErrors();
    sparePartForm.workshop_id = resolveDefaultWorkshopId();
    sparePartForm.supplier_id = '';
    sparePartForm.warehouse_id = String(tableFilters.value.warehouse_id || '').trim() || defaultWarehouseId.value;
    sparePartForm.name = '';
    sparePartForm.sku = '';
    sparePartForm.category = '';
    sparePartForm.unit = '';
    sparePartForm.purchase_price = null;
    sparePartForm.selling_price = null;
    sparePartForm.stock = 0;
    sparePartForm.minimum_stock = 0;
    sparePartForm.notes = '';
    sparePartForm.is_active = true;
};

const openCreateSparePartModal = () => {
    if (!canManageSpareparts.value) {
        return;
    }

    resetSparePartForm();
    isSparePartModalOpen.value = true;
};

const startEditSparePart = (sparePart) => {
    if (!canManageSpareparts.value) {
        return;
    }

    const sparePartId = String(sparePart?.id || '').trim();
    if (sparePartId === '') {
        return;
    }

    editingSparePartId.value = sparePartId;
    sparePartForm.clearErrors();
    sparePartForm.workshop_id = String(sparePart?.workshop_id || '').trim() || resolveDefaultWorkshopId();
    sparePartForm.supplier_id = String(sparePart?.supplier_id || '');
    sparePartForm.warehouse_id = String(
        sparePart?.stock_warehouse_id
        || tableFilters.value.warehouse_id
        || defaultWarehouseId.value
        || '',
    ).trim();
    sparePartForm.name = String(sparePart?.name || '');
    sparePartForm.sku = String(sparePart?.sku || '');
    sparePartForm.category = String(sparePart?.category || '');
    sparePartForm.unit = String(sparePart?.unit || '');
    sparePartForm.purchase_price = Number.isFinite(Number(sparePart?.purchase_price))
        ? Number(sparePart?.purchase_price)
        : null;
    sparePartForm.selling_price = Number.isFinite(Number(sparePart?.selling_price))
        ? Number(sparePart?.selling_price)
        : null;
    sparePartForm.stock = Number.isFinite(Number(sparePart?.stock))
        ? Number(sparePart?.stock)
        : 0;
    sparePartForm.minimum_stock = Number.isFinite(Number(sparePart?.minimum_stock))
        ? Number(sparePart?.minimum_stock)
        : 0;
    sparePartForm.notes = String(sparePart?.notes || '');
    sparePartForm.is_active = Boolean(sparePart?.is_active);
    isSparePartModalOpen.value = true;
};

const closeSparePartModal = () => {
    isSparePartModalOpen.value = false;
    resetSparePartForm();
};

const toNullableInteger = (value) => {
    if (value === null || value === undefined) {
        return null;
    }

    const normalized = String(value).trim();
    if (normalized === '' || Number.isNaN(Number(normalized))) {
        return null;
    }

    return Math.max(Number.parseInt(normalized, 10), 0);
};

const submitSparePartForm = () => {
    const payload = (data) => ({
        ...data,
        workshop_id: String(data.workshop_id || '').trim(),
        supplier_id: String(data.supplier_id || '').trim(),
        warehouse_id: String(data.warehouse_id || '').trim(),
        name: String(data.name || '').trim(),
        sku: String(data.sku || '').trim(),
        category: String(data.category || '').trim(),
        unit: String(data.unit || '').trim(),
        notes: String(data.notes || '').trim(),
        purchase_price: toNullableInteger(data.purchase_price),
        selling_price: toNullableInteger(data.selling_price),
        stock: toNullableInteger(data.stock),
        minimum_stock: toNullableInteger(data.minimum_stock),
        is_active: Boolean(data.is_active),
    });

    if (isEditMode.value) {
        updateOwnerSparepart(
            sparePartForm.transform(payload),
            sparepartUpdatePath(editingSparePartId.value),
            {
                onSuccess: closeSparePartModal,
            },
        );

        return;
    }

    storeOwnerSparepart(
        sparePartForm.transform(payload),
        sparepartStorePath.value,
        {
            onSuccess: closeSparePartModal,
        },
    );
};

const deleteSparePart = (sparePart) => {
    if (!canManageSpareparts.value) {
        return;
    }

    const sparePartId = String(sparePart?.id || '').trim();
    if (sparePartId === '') {
        return;
    }

    if (!window.confirm('Hapus sparepart ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    destroyOwnerSparepart(sparepartDeletePath(sparePartId), {
        onStart: () => {
            deletingSparePartId.value = sparePartId;
        },
        onFinish: () => {
            deletingSparePartId.value = null;
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
    isSparePartModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isSparePartModalOpen.value) {
        closeSparePartModal();
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
    <Head title="Sparepart Owner" />

    <AppDashboardLayout
        title="Sparepart"
        subtitle="Kelola data sparepart tenant Anda"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <OwnerSparepartTableCard
                :spareparts="spareparts"
                :filters="tableFilters"
                :supplier-options="supplierOptions"
                :warehouse-options="warehouseOptions"
                :spare-part-summary="sparePartSummary"
                :flash-status="flashStatus"
                :error-message="sparePartError"
                :table-loading="tableLoading"
                :form-processing="sparePartForm.processing"
                :deleting-spare-part-id="deletingSparePartId"
                :can-manage="canManageSpareparts"
                @create="openCreateSparePartModal"
                @edit="startEditSparePart"
                @delete="deleteSparePart"
                @search="handleSearch"
                @supplier-filter="handleSupplierFilter"
                @warehouse-filter="handleWarehouseFilter"
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
                v-if="isSparePartModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeSparePartModal"
                />

                <div class="relative z-20 w-full max-w-2xl">
                    <OwnerSparepartFormCard
                        :is-edit-mode="isEditMode"
                        :form="sparePartForm"
                        :errors="pageErrors"
                        :workshop-options="workshopOptions"
                        :is-workshop-selectable="isGlobalWorkshopFilter"
                        :supplier-options="supplierOptions"
                        :warehouse-options="availableWarehouseOptionsForForm"
                        :spare-part-category-options="sparePartCategoryOptions"
                        :spare-part-unit-options="sparePartUnitOptions"
                        @close="closeSparePartModal"
                        @submit="submitSparePartForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>

