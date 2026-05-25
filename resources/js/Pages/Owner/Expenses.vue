<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerExpenseFormCard from './Expenses/Components/OwnerExpenseFormCard.vue';
import OwnerExpenseTableCard from './Expenses/Components/OwnerExpenseTableCard.vue';
import {
    destroyOwnerExpense,
    fetchOwnerExpenses,
    storeOwnerExpense,
    updateOwnerExpense,
} from './Services/ownerExpenseService';

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
    expenses: {
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
    expenseFilters: {
        type: Object,
        default: () => ({
            search: '',
            category: '',
            sort_by: 'expense_date',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
            period: 'all',
            date_from: '',
            date_to: '',
            workshop_id: '',
        }),
    },
    expenseSummary: {
        type: Object,
        default: () => ({
            total_entries: 0,
            total_amount: 0,
            this_month_entries: 0,
            this_month_amount: 0,
            period_label: 'Bulan Ini',
        }),
    },
    expenseRecapByWorkshop: {
        type: Array,
        default: () => [],
    },
    expenseCategoryOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const logoutForm = useForm({});
const expenseForm = useForm({
    workshop_id: '',
    expense_date: new Date(),
    category: '',
    description: '',
    reference_number: '',
    notes: '',
    amount: null,
});

const tableLoading = ref(false);
const tableFilters = ref({
    search: '',
    category: '',
    sort_by: 'expense_date',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
    period: 'all',
    date_from: '',
    date_to: '',
    workshop_id: '',
});

const editingExpenseId = ref(null);
const deletingExpenseId = ref(null);
const isExpenseModalOpen = ref(false);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const isEditMode = computed(() => String(editingExpenseId.value || '').trim() !== '');
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const expensesPath = computed(() => `${baseOwnerPath.value}/expenses`);
const expenseStorePath = computed(() => `${baseOwnerPath.value}/expenses`);
const expenseUpdatePath = (expenseId) => `${baseOwnerPath.value}/expenses/${expenseId}`;
const expenseDeletePath = (expenseId) => `${baseOwnerPath.value}/expenses/${expenseId}`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const expenseError = computed(() => String(
    expenseForm.errors?.create_expense
    || expenseForm.errors?.update_expense
    || expenseForm.errors?.delete_expense
    || pageErrors.value?.create_expense
    || pageErrors.value?.update_expense
    || pageErrors.value?.delete_expense
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageExpenses = computed(() => (
    permissionNames.value.includes('expenses.manage')
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

const parseDateValue = (value) => {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? new Date() : value;
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return new Date();
    }

    return parsed;
};

watch(
    () => props.expenseFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            category: String(filters?.category || ''),
            sort_by: String(filters?.sort_by || 'expense_date'),
            sort_dir: String(filters?.sort_dir || 'desc'),
            per_page: Number(filters?.per_page) || 10,
            cursor: filters?.cursor ? String(filters.cursor) : null,
            period: String(filters?.period || 'all'),
            date_from: String(filters?.date_from || ''),
            date_to: String(filters?.date_to || ''),
            workshop_id: String(filters?.workshop_id || ''),
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

    fetchOwnerExpenses(expensesPath.value, nextFilters, {
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

const handleCategory = (category) => {
    requestTable({
        category,
        cursor: null,
    });
};

const handlePeriod = (period) => {
    const normalizedPeriod = String(period || 'all').trim() || 'all';

    requestTable({
        period: normalizedPeriod,
        ...(normalizedPeriod === 'all'
            ? {
                date_from: '',
                date_to: '',
            }
            : {}),
        cursor: null,
    });
};

const handleDateRange = ({ date_from: dateFrom = '', date_to: dateTo = '' } = {}) => {
    const normalizedDateFrom = String(dateFrom || '').trim();
    const normalizedDateTo = String(dateTo || '').trim();

    requestTable({
        period: normalizedDateFrom !== '' || normalizedDateTo !== '' ? 'custom' : 'all',
        date_from: normalizedDateFrom,
        date_to: normalizedDateTo,
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

const resetExpenseForm = () => {
    editingExpenseId.value = null;
    expenseForm.clearErrors();
    expenseForm.workshop_id = resolveDefaultWorkshopId();
    expenseForm.expense_date = new Date();
    expenseForm.category = '';
    expenseForm.description = '';
    expenseForm.reference_number = '';
    expenseForm.notes = '';
    expenseForm.amount = null;
};

const openCreateExpenseModal = () => {
    if (!canManageExpenses.value) {
        return;
    }

    resetExpenseForm();
    isExpenseModalOpen.value = true;
};

const startEditExpense = (expense) => {
    if (!canManageExpenses.value) {
        return;
    }

    const expenseId = String(expense?.id || '').trim();
    if (expenseId === '') {
        return;
    }

    editingExpenseId.value = expenseId;
    expenseForm.clearErrors();
    expenseForm.workshop_id = String(expense?.workshop_id || '').trim() || resolveDefaultWorkshopId();
    expenseForm.expense_date = parseDateValue(expense?.expense_date);
    expenseForm.category = String(expense?.category || '');
    expenseForm.description = String(expense?.description || '');
    expenseForm.reference_number = String(expense?.reference_number || '');
    expenseForm.notes = String(expense?.notes || '');
    const normalizedAmount = Number(expense?.amount);
    expenseForm.amount = Number.isFinite(normalizedAmount) ? normalizedAmount : null;
    isExpenseModalOpen.value = true;
};

const closeExpenseModal = () => {
    isExpenseModalOpen.value = false;
    resetExpenseForm();
};

const submitExpenseForm = () => {
    const payload = (data) => ({
        ...data,
        workshop_id: String(data.workshop_id || '').trim(),
        expense_date: formatDateForBackend(data.expense_date),
        category: String(data.category || '').trim(),
        description: String(data.description || '').trim(),
        reference_number: String(data.reference_number || '').trim(),
        notes: String(data.notes || '').trim(),
        amount: data.amount === null || data.amount === undefined || data.amount === ''
            ? null
            : Number(data.amount),
    });

    if (isEditMode.value) {
        updateOwnerExpense(
            expenseForm.transform(payload),
            expenseUpdatePath(editingExpenseId.value),
            {
                onSuccess: closeExpenseModal,
            },
        );

        return;
    }

    storeOwnerExpense(
        expenseForm.transform(payload),
        expenseStorePath.value,
        {
            onSuccess: closeExpenseModal,
        },
    );
};

const deleteExpense = (expense) => {
    if (!canManageExpenses.value) {
        return;
    }

    const expenseId = String(expense?.id || '').trim();
    if (expenseId === '') {
        return;
    }

    if (!window.confirm('Hapus pengeluaran ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    destroyOwnerExpense(expenseDeletePath(expenseId), {
        onStart: () => {
            deletingExpenseId.value = expenseId;
        },
        onFinish: () => {
            deletingExpenseId.value = null;
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
    isExpenseModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isExpenseModalOpen.value) {
        closeExpenseModal();
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
    <Head title="Pengeluaran Owner" />

    <AppDashboardLayout
        title="Pengeluaran"
        subtitle="Rekap biaya operasional per cabang dengan filter bengkel aktif"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <OwnerExpenseTableCard
                :expenses="expenses"
                :filters="tableFilters"
                :expense-summary="expenseSummary"
                :expense-recap-by-workshop="expenseRecapByWorkshop"
                :expense-category-options="expenseCategoryOptions"
                :active-workshop="activeWorkshop"
                :workshop-options="workshopOptions"
                :is-global-workshop-filter="isGlobalWorkshopFilter"
                :flash-status="flashStatus"
                :error-message="expenseError"
                :table-loading="tableLoading"
                :form-processing="expenseForm.processing"
                :deleting-expense-id="deletingExpenseId"
                :can-manage="canManageExpenses"
                @create="openCreateExpenseModal"
                @edit="startEditExpense"
                @delete="deleteExpense"
                @search="handleSearch"
                @category="handleCategory"
                @period="handlePeriod"
                @date-range="handleDateRange"
                @workshop="handleWorkshopFilter"
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
                v-if="isExpenseModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeExpenseModal"
                />

                <div class="relative z-20 w-full max-w-2xl">
                    <OwnerExpenseFormCard
                        :is-edit-mode="isEditMode"
                        :form="expenseForm"
                        :errors="pageErrors"
                        :workshop-options="workshopOptions"
                        :is-workshop-selectable="isGlobalWorkshopFilter"
                        :category-options="expenseCategoryOptions"
                        @close="closeExpenseModal"
                        @submit="submitExpenseForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>

