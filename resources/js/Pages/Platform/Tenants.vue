<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import TenantTableCard from './Tenants/Components/TenantTableCard.vue';
import TenantFormCard from './Tenants/Components/TenantFormCard.vue';
import { usePlatformTenantPage } from './Composables/usePlatformTenantPage';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    tenants: {
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
    tenantsCount: {
        type: Number,
        default: 0,
    },
    tenantFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    tenantRootDomain: {
        type: String,
        default: '',
    },
    planOptions: {
        type: Array,
        default: () => [],
    },
});

const {
    dashboardPath,
    flashStatus,
    pageErrors,
    tenantError,
    tableFilters,
    tableLoading,
    menuItems,
    tenantForm,
    statusForm,
    isTenantModalOpen,
    isEditMode,
    tenantRootDomain,
    togglingTenantId,
    handleSearch,
    handleSort,
    handlePerPage,
    handleExport,
    handlePage,
    openCreateTenantModal,
    closeTenantModal,
    startEditTenant,
    submitTenantForm,
    toggleTenantStatus,
    logout,
} = usePlatformTenantPage(props);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflowY = ref('');

const resolvePageScrollContainer = () => {
    if (!pageContentRef.value || typeof pageContentRef.value.closest !== 'function') {
        return null;
    }

    const container = pageContentRef.value.closest('.dashboard-scroll');
    return container instanceof HTMLElement ? container : null;
};

const setPageScrollLock = (isLocked) => {
    const container = lockedScrollContainer.value ?? resolvePageScrollContainer();
    if (!(container instanceof HTMLElement)) {
        return;
    }

    if (isLocked) {
        lockedScrollContainer.value = container;
        previousOverflowY.value = container.style.overflowY;
        container.style.overflowY = 'hidden';
        return;
    }

    container.style.overflowY = previousOverflowY.value;
    previousOverflowY.value = '';
    lockedScrollContainer.value = null;
};

watch(
    isTenantModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

onBeforeUnmount(() => {
    setPageScrollLock(false);
});
</script>

<template>
    <Head title="Tenant Superadmin" />

    <AppDashboardLayout
        title="Tenant"
        subtitle="Kelola tenant dan paket langganan"
        role-label="Superadmin"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <TenantTableCard
                :tenants="tenants"
                :filters="tableFilters"
                :flash-status="flashStatus"
                :table-loading="tableLoading"
                :status-processing="statusForm.processing"
                :toggling-tenant-id="togglingTenantId"
                :error-message="tenantError"
                @create="openCreateTenantModal"
                @search="handleSearch"
                @sort="handleSort"
                @per-page="handlePerPage"
                @export="handleExport"
                @page="handlePage"
                @edit="startEditTenant"
                @toggle-status="toggleTenantStatus"
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
                v-if="isTenantModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeTenantModal"
                />

                <div class="relative z-20 w-full max-w-2xl">
                    <TenantFormCard
                        :is-edit-mode="isEditMode"
                        :form="tenantForm"
                        :tenant-root-domain="tenantRootDomain"
                        :plan-options="planOptions"
                        :errors="pageErrors"
                        @close="closeTenantModal"
                        @submit="submitTenantForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>
