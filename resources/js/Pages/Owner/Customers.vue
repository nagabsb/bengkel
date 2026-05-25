<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerCustomerTableCard from './Customers/Components/OwnerCustomerTableCard.vue';
import OwnerCustomerFormCard from './Customers/Components/OwnerCustomerFormCard.vue';
import {
    destroyOwnerCustomer,
    fetchOwnerCustomers,
    storeOwnerCustomer,
    updateOwnerCustomer,
} from './Services/ownerCustomerService';

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
    customers: {
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
    customerFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    customerSummary: {
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
const customerForm = useForm({
    workshop_id: '',
    name: '',
    phone: '',
    email: '',
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

const editingCustomerId = ref(null);
const deletingCustomerId = ref(null);
const isCustomerModalOpen = ref(false);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const isEditMode = computed(() => String(editingCustomerId.value || '').trim() !== '');
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const customersPath = computed(() => `${baseOwnerPath.value}/customers`);
const customerStorePath = computed(() => `${baseOwnerPath.value}/customers`);
const customerUpdatePath = (customerId) => `${baseOwnerPath.value}/customers/${customerId}`;
const customerDeletePath = (customerId) => `${baseOwnerPath.value}/customers/${customerId}`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const customerError = computed(() => String(
    customerForm.errors?.create_customer
    || customerForm.errors?.update_customer
    || customerForm.errors?.delete_customer
    || pageErrors.value?.create_customer
    || pageErrors.value?.update_customer
    || pageErrors.value?.delete_customer
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageCustomers = computed(() => (
    permissionNames.value.includes('customers.manage')
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
    () => props.customerFilters,
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

    fetchOwnerCustomers(customersPath.value, nextFilters, {
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

const resetCustomerForm = () => {
    editingCustomerId.value = null;
    customerForm.clearErrors();
    customerForm.workshop_id = resolveDefaultWorkshopId();
    customerForm.name = '';
    customerForm.phone = '';
    customerForm.email = '';
    customerForm.address = '';
    customerForm.notes = '';
    customerForm.is_active = true;
};

const openCreateCustomerModal = () => {
    if (!canManageCustomers.value) {
        return;
    }

    resetCustomerForm();
    isCustomerModalOpen.value = true;
};

const startEditCustomer = (customer) => {
    if (!canManageCustomers.value) {
        return;
    }

    const customerId = String(customer?.id || '').trim();
    if (customerId === '') {
        return;
    }

    editingCustomerId.value = customerId;
    customerForm.clearErrors();
    customerForm.workshop_id = String(customer?.workshop_id || '').trim() || resolveDefaultWorkshopId();
    customerForm.name = String(customer?.name || '');
    customerForm.phone = String(customer?.phone || '');
    customerForm.email = String(customer?.email || '');
    customerForm.address = String(customer?.address || '');
    customerForm.notes = String(customer?.notes || '');
    customerForm.is_active = Boolean(customer?.is_active);
    isCustomerModalOpen.value = true;
};

const closeCustomerModal = () => {
    isCustomerModalOpen.value = false;
    resetCustomerForm();
};

const submitCustomerForm = () => {
    const payload = (data) => ({
        ...data,
        workshop_id: String(data.workshop_id || '').trim(),
        name: String(data.name || '').trim(),
        phone: String(data.phone || '').trim(),
        email: String(data.email || '').trim(),
        address: String(data.address || '').trim(),
        notes: String(data.notes || '').trim(),
        is_active: Boolean(data.is_active),
    });

    if (isEditMode.value) {
        updateOwnerCustomer(
            customerForm.transform(payload),
            customerUpdatePath(editingCustomerId.value),
            {
                onSuccess: closeCustomerModal,
            },
        );

        return;
    }

    storeOwnerCustomer(
        customerForm.transform(payload),
        customerStorePath.value,
        {
            onSuccess: closeCustomerModal,
        },
    );
};

const deleteCustomer = (customer) => {
    if (!canManageCustomers.value) {
        return;
    }

    const customerId = String(customer?.id || '').trim();
    if (customerId === '') {
        return;
    }

    if (!window.confirm('Hapus customer ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    destroyOwnerCustomer(customerDeletePath(customerId), {
        onStart: () => {
            deletingCustomerId.value = customerId;
        },
        onFinish: () => {
            deletingCustomerId.value = null;
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
    isCustomerModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isCustomerModalOpen.value) {
        closeCustomerModal();
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
    <Head title="Customer Owner" />

    <AppDashboardLayout
        title="Customer"
        subtitle="Kelola data pelanggan tenant Anda"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <OwnerCustomerTableCard
                :customers="customers"
                :filters="tableFilters"
                :customer-summary="customerSummary"
                :flash-status="flashStatus"
                :error-message="customerError"
                :table-loading="tableLoading"
                :form-processing="customerForm.processing"
                :deleting-customer-id="deletingCustomerId"
                :can-manage="canManageCustomers"
                @create="openCreateCustomerModal"
                @edit="startEditCustomer"
                @delete="deleteCustomer"
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
                v-if="isCustomerModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeCustomerModal"
                />

                <div class="relative z-20 w-full max-w-xl">
                    <OwnerCustomerFormCard
                        :is-edit-mode="isEditMode"
                        :form="customerForm"
                        :errors="pageErrors"
                        :workshop-options="workshopOptions"
                        :is-workshop-selectable="isGlobalWorkshopFilter"
                        @close="closeCustomerModal"
                        @submit="submitCustomerForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>

