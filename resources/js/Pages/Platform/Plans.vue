<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import PlanTableCard from './Plans/Components/PlanTableCard.vue';
import PlanFormCard from './Plans/Components/PlanFormCard.vue';
import { usePlatformPlanPage } from './Composables/usePlatformPlanPage';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    plans: {
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
    planFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'price',
            sort_dir: 'asc',
            per_page: 10,
            cursor: null,
        }),
    },
    menuOptions: {
        type: Array,
        default: () => [],
    },
    tenantsCount: {
        type: Number,
        default: 0,
    },
});

const {
    dashboardPath,
    flashStatus,
    pageErrors,
    planError,
    tableFilters,
    tableLoading,
    menuItems,
    planForm,
    statusForm,
    isPlanModalOpen,
    isEditMode,
    togglingPlanId,
    handleSearch,
    handleSort,
    handlePerPage,
    handlePage,
    openCreatePlanModal,
    closePlanModal,
    startEditPlan,
    submitPlanForm,
    togglePlanStatus,
    logout,
} = usePlatformPlanPage(props);

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
    isPlanModalOpen,
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
    <Head title="Plan Superadmin" />

    <AppDashboardLayout
        title="Plan"
        subtitle="Kelola paket dan harga langganan"
        role-label="Superadmin"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <PlanTableCard
                :plans="plans"
                :filters="tableFilters"
                :flash-status="flashStatus"
                :table-loading="tableLoading"
                :status-processing="statusForm.processing"
                :toggling-plan-id="togglingPlanId"
                :error-message="planError"
                @create="openCreatePlanModal"
                @search="handleSearch"
                @sort="handleSort"
                @per-page="handlePerPage"
                @page="handlePage"
                @edit="startEditPlan"
                @toggle-status="togglePlanStatus"
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
                v-if="isPlanModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closePlanModal"
                />

                <div class="relative z-20 w-full max-w-3xl">

                    <PlanFormCard
                        :is-edit-mode="isEditMode"
                        :form="planForm"
                        :menu-options="menuOptions"
                        :errors="pageErrors"
                        @close="closePlanModal"
                        @submit="submitPlanForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>







