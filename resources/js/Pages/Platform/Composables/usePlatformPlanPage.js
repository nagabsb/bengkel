import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { fetchPlatformPlans } from '../Services/platformPlanService';

export const usePlatformPlanPage = (props) => {
    const page = usePage();
    const logoutForm = useForm({});
    const planForm = useForm({
        name: '',
        slug: '',
        max_workshops: 1,
        max_users_per_ws: 5,
        has_ai_feature: false,
        has_notification: true,
        has_loyalty: false,
        has_trial: true,
        trial_duration_days: 14,
        duration_months: 1,
        price: 0,
        discount_pct: 0,
        is_active: true,
        menu_ids: [],
    });
    const statusForm = useForm({
        is_active: false,
    });

    const editingPlanId = ref(null);
    const togglingPlanId = ref(null);
    const tableLoading = ref(false);
    const isPlanModalOpen = ref(false);

    const dashboardPath = '/platform/dashboard';
    const tenantsPath = '/platform/tenants';
    const permissionsPath = '/platform/settings/permissions';
    const menusPath = '/platform/settings/menus';
    const plansPath = '/platform/settings/plans';
    const applicationPath = '/platform/settings/application';
    const paymentsPath = '/platform/settings/payments';
    const vehicleMastersPath = '/platform/settings/vehicle-masters';
    const aiAgentPath = '/platform/settings/ai-agent';
    const planStorePath = '/platform/settings/plans';
    const planUpdatePath = (planId) => `/platform/settings/plans/${planId}`;
    const planStatusPath = (planId) => `/platform/settings/plans/${planId}/status`;

    const currentPath = computed(() => String(page.url || '').split('?')[0] || '');

    const activeSettingsKey = computed(() => {
        const path = currentPath.value;
        if (path === permissionsPath) return 'permissions';
        if (path === menusPath) return 'menus';
        if (path === plansPath) return 'plans';
        if (path === applicationPath) return 'application';
        if (path === paymentsPath) return 'payments';
        if (path === vehicleMastersPath) return 'vehicle-masters';
        if (path === aiAgentPath) return 'ai-agent';

        return '';
    });

    const isSettingsActive = computed(() => ['permissions', 'menus', 'application', 'payments', 'vehicle-masters', 'ai-agent'].includes(activeSettingsKey.value));

    const menuItems = computed(() => [
        { key: 'dashboard', label: 'Dasbor', icon: 'dashboard', href: dashboardPath, active: currentPath.value === dashboardPath },
        { key: 'tenants', label: 'Tenant', icon: 'users', href: tenantsPath, active: currentPath.value === tenantsPath },
        { key: 'plans', label: 'Plan', icon: 'billing', href: plansPath, active: activeSettingsKey.value === 'plans' },
        {
            key: 'settings',
            label: 'Pengaturan',
            icon: 'settings',
            active: isSettingsActive.value,
            children: [
                { key: 'permissions', label: 'Permission', href: permissionsPath, active: activeSettingsKey.value === 'permissions' },
                { key: 'menus', label: 'Management Menu', href: menusPath, active: activeSettingsKey.value === 'menus' },
                { key: 'application', label: 'Aplikasi', href: applicationPath, active: activeSettingsKey.value === 'application' },
                { key: 'payments', label: 'Pembayaran', href: paymentsPath, active: activeSettingsKey.value === 'payments' },
                { key: 'vehicle-masters', label: 'Master Kendaraan', href: vehicleMastersPath, active: activeSettingsKey.value === 'vehicle-masters' },
                { key: 'ai-agent', label: 'AI Agent', href: aiAgentPath, active: activeSettingsKey.value === 'ai-agent' },
            ],
        },
    ]);

    const tableFilters = ref({
        search: '',
        sort_by: 'price',
        sort_dir: 'asc',
        per_page: 10,
        cursor: null,
    });

    watch(
        () => props.planFilters,
        (filters) => {
            tableFilters.value = {
                search: String(filters?.search || ''),
                sort_by: String(filters?.sort_by || 'price'),
                sort_dir: String(filters?.sort_dir || 'asc'),
                per_page: Number(filters?.per_page) || 10,
                cursor: filters?.cursor ? String(filters.cursor) : null,
            };
        },
        {
            immediate: true,
            deep: true,
        },
    );

    const flashStatus = computed(() => String(page.props?.flash?.status || ''));
    const pageErrors = computed(() => page.props?.errors || {});
    const planError = computed(() => {
        return String(
            planForm.errors?.create_plan
            || planForm.errors?.update_plan
            || statusForm.errors?.status_plan
            || pageErrors.value?.create_plan
            || pageErrors.value?.update_plan
            || pageErrors.value?.status_plan
            || '',
        );
    });

    const isEditMode = computed(() => Number(editingPlanId.value) > 0);

    const requestTable = (override = {}) => {
        const nextFilters = {
            ...tableFilters.value,
            ...override,
        };

        tableFilters.value = nextFilters;
        fetchPlatformPlans(plansPath, nextFilters, {
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

    const resetPlanForm = () => {
        editingPlanId.value = null;
        planForm.clearErrors();
        planForm.name = '';
        planForm.slug = '';
        planForm.max_workshops = 1;
        planForm.max_users_per_ws = 5;
        planForm.has_ai_feature = false;
        planForm.has_notification = true;
        planForm.has_loyalty = false;
        planForm.has_trial = true;
        planForm.trial_duration_days = 14;
        planForm.duration_months = 1;
        planForm.price = 0;
        planForm.discount_pct = 0;
        planForm.is_active = true;
        planForm.menu_ids = [];
    };

    const openCreatePlanModal = () => {
        resetPlanForm();
        isPlanModalOpen.value = true;
    };

    const closePlanModal = () => {
        isPlanModalOpen.value = false;
        resetPlanForm();
    };

    const startEditPlan = (plan) => {
        const planId = Number(plan?.id) || 0;
        if (planId <= 0) return;

        editingPlanId.value = planId;
        planForm.clearErrors();

        planForm.name = String(plan?.name || '');
        planForm.slug = String(plan?.slug || '');
        planForm.max_workshops = Number(plan?.max_workshops) || 1;
        planForm.max_users_per_ws = Number(plan?.max_users_per_ws) || 5;
        planForm.has_ai_feature = Boolean(plan?.has_ai_feature);
        planForm.has_notification = Boolean(plan?.has_notification);
        planForm.has_loyalty = Boolean(plan?.has_loyalty);
        planForm.has_trial = Boolean(plan?.has_trial);
        planForm.trial_duration_days = Number(plan?.trial_duration_days) || 14;
        planForm.duration_months = Number(plan?.price?.duration_months) || 1;
        planForm.price = Number(plan?.price?.amount) || 0;
        planForm.discount_pct = Number(plan?.price?.discount_pct) || 0;
        planForm.is_active = Boolean(plan?.is_active);
        planForm.menu_ids = Array.isArray(plan?.menu_ids)
            ? plan.menu_ids.map((menuId) => Number(menuId)).filter((menuId) => menuId > 0)
            : [];
        isPlanModalOpen.value = true;
    };

    const submitPlanForm = () => {
        const payload = (data) => ({
            ...data,
            name: String(data.name || '').trim(),
            slug: String(data.slug || '').trim().toLowerCase(),
            max_workshops: Number(data.max_workshops) || 1,
            max_users_per_ws: Number(data.max_users_per_ws) || 1,
            has_ai_feature: Boolean(data.has_ai_feature),
            has_notification: Boolean(data.has_notification),
            has_loyalty: Boolean(data.has_loyalty),
            has_trial: Boolean(data.has_trial),
            trial_duration_days: Boolean(data.has_trial) ? Math.max(1, Number(data.trial_duration_days) || 1) : null,
            duration_months: Number(data.duration_months) || 1,
            price: Number(data.price) || 0,
            discount_pct: Number(data.discount_pct) || 0,
            is_active: Boolean(data.is_active),
            menu_ids: Array.isArray(data.menu_ids)
                ? data.menu_ids.map((menuId) => Number(menuId)).filter((menuId) => Number.isInteger(menuId) && menuId > 0)
                : [],
        });

        if (isEditMode.value) {
            planForm
                .transform(payload)
                .patch(planUpdatePath(editingPlanId.value), {
                    preserveScroll: true,
                    onSuccess: closePlanModal,
                });
            return;
        }

        planForm
            .transform(payload)
            .post(planStorePath, {
                preserveScroll: true,
                onSuccess: closePlanModal,
            });
    };

    const togglePlanStatus = (plan) => {
        const planId = Number(plan?.id) || 0;
        if (planId <= 0 || statusForm.processing) return;

        const nextStatus = !Boolean(plan?.is_active);
        statusForm.clearErrors();

        statusForm
            .transform(() => ({
                is_active: nextStatus,
            }))
            .patch(planStatusPath(planId), {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    togglingPlanId.value = planId;
                },
                onFinish: () => {
                    togglingPlanId.value = null;
                },
            });
    };

    const logout = () => {
        logoutForm.post('/logout');
    };

    return {
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
        resetPlanForm,
        startEditPlan,
        submitPlanForm,
        togglePlanStatus,
        logout,
    };
};


