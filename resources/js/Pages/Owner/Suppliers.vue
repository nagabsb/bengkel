<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerSupplierTableCard from './Suppliers/Components/OwnerSupplierTableCard.vue';
import OwnerSupplierFormCard from './Suppliers/Components/OwnerSupplierFormCard.vue';
import {
    destroyOwnerSupplier,
    fetchOwnerSuppliers,
    storeOwnerSupplier,
    updateOwnerSupplier,
} from './Services/ownerSupplierService';

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
    suppliers: {
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
    supplierFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    supplierSummary: {
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
const supplierForm = useForm({
    name: '',
    phone: '',
    email: '',
    address: '',
    pic_name: '',
    pic_phone: '',
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

const editingSupplierId = ref(null);
const deletingSupplierId = ref(null);
const isSupplierModalOpen = ref(false);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const isEditMode = computed(() => String(editingSupplierId.value || '').trim() !== '');
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const suppliersPath = computed(() => `${baseOwnerPath.value}/suppliers`);
const supplierStorePath = computed(() => `${baseOwnerPath.value}/suppliers`);
const supplierUpdatePath = (supplierId) => `${baseOwnerPath.value}/suppliers/${supplierId}`;
const supplierDeletePath = (supplierId) => `${baseOwnerPath.value}/suppliers/${supplierId}`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const supplierError = computed(() => String(
    supplierForm.errors?.create_supplier
    || supplierForm.errors?.update_supplier
    || supplierForm.errors?.delete_supplier
    || pageErrors.value?.create_supplier
    || pageErrors.value?.update_supplier
    || pageErrors.value?.delete_supplier
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageSuppliers = computed(() => (
    permissionNames.value.includes('suppliers.manage')
    || permissionNames.value.includes('users.manage')
));

watch(
    () => props.supplierFilters,
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

    fetchOwnerSuppliers(suppliersPath.value, nextFilters, {
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

const resetSupplierForm = () => {
    editingSupplierId.value = null;
    supplierForm.clearErrors();
    supplierForm.name = '';
    supplierForm.phone = '';
    supplierForm.email = '';
    supplierForm.address = '';
    supplierForm.pic_name = '';
    supplierForm.pic_phone = '';
    supplierForm.notes = '';
    supplierForm.is_active = true;
};

const openCreateSupplierModal = () => {
    if (!canManageSuppliers.value) {
        return;
    }

    resetSupplierForm();
    isSupplierModalOpen.value = true;
};

const startEditSupplier = (supplier) => {
    if (!canManageSuppliers.value) {
        return;
    }

    const supplierId = String(supplier?.id || '').trim();
    if (supplierId === '') {
        return;
    }

    editingSupplierId.value = supplierId;
    supplierForm.clearErrors();
    supplierForm.name = String(supplier?.name || '');
    supplierForm.phone = String(supplier?.phone || '');
    supplierForm.email = String(supplier?.email || '');
    supplierForm.address = String(supplier?.address || '');
    supplierForm.pic_name = String(supplier?.pic_name || '');
    supplierForm.pic_phone = String(supplier?.pic_phone || '');
    supplierForm.notes = String(supplier?.notes || '');
    supplierForm.is_active = Boolean(supplier?.is_active);
    isSupplierModalOpen.value = true;
};

const closeSupplierModal = () => {
    isSupplierModalOpen.value = false;
    resetSupplierForm();
};

const submitSupplierForm = () => {
    const payload = (data) => ({
        ...data,
        name: String(data.name || '').trim(),
        phone: String(data.phone || '').trim(),
        email: String(data.email || '').trim(),
        address: String(data.address || '').trim(),
        pic_name: String(data.pic_name || '').trim(),
        pic_phone: String(data.pic_phone || '').trim(),
        notes: String(data.notes || '').trim(),
        is_active: Boolean(data.is_active),
    });

    if (isEditMode.value) {
        updateOwnerSupplier(
            supplierForm.transform(payload),
            supplierUpdatePath(editingSupplierId.value),
            {
                onSuccess: closeSupplierModal,
            },
        );

        return;
    }

    storeOwnerSupplier(
        supplierForm.transform(payload),
        supplierStorePath.value,
        {
            onSuccess: closeSupplierModal,
        },
    );
};

const deleteSupplier = (supplier) => {
    if (!canManageSuppliers.value) {
        return;
    }

    const supplierId = String(supplier?.id || '').trim();
    if (supplierId === '') {
        return;
    }

    if (!window.confirm('Hapus supplier ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    destroyOwnerSupplier(supplierDeletePath(supplierId), {
        onStart: () => {
            deletingSupplierId.value = supplierId;
        },
        onFinish: () => {
            deletingSupplierId.value = null;
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
    isSupplierModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isSupplierModalOpen.value) {
        closeSupplierModal();
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
    <Head title="Supplier Owner" />

    <AppDashboardLayout
        title="Supplier"
        subtitle="Kelola data supplier sparepart tenant Anda"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <OwnerSupplierTableCard
                :suppliers="suppliers"
                :filters="tableFilters"
                :supplier-summary="supplierSummary"
                :flash-status="flashStatus"
                :error-message="supplierError"
                :table-loading="tableLoading"
                :form-processing="supplierForm.processing"
                :deleting-supplier-id="deletingSupplierId"
                :can-manage="canManageSuppliers"
                @create="openCreateSupplierModal"
                @edit="startEditSupplier"
                @delete="deleteSupplier"
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
                v-if="isSupplierModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeSupplierModal"
                />

                <div class="relative z-20 w-full max-w-xl">
                    <OwnerSupplierFormCard
                        :is-edit-mode="isEditMode"
                        :form="supplierForm"
                        :errors="pageErrors"
                        @close="closeSupplierModal"
                        @submit="submitSupplierForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>

