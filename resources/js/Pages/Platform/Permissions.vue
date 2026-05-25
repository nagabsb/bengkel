<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import PermissionSummaryCards from './Permissions/Components/PermissionSummaryCards.vue';
import PermissionCatalogCard from './Permissions/Components/PermissionCatalogCard.vue';
import RolePermissionTable from './Permissions/Components/RolePermissionTable.vue';
import { useRolePermissionMatrix } from '../../Composables/useRolePermissionMatrix';
import { fetchPlatformPermissions, syncPlatformRolePermissions } from './Services/platformPermissionService';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    roles: {
        type: Array,
        default: () => [],
    },
    permissions: {
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
    permissionFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'menu_order',
            sort_dir: 'asc',
            per_page: 10,
            cursor: null,
        }),
    },
    permissionScopeTotal: {
        type: Number,
        default: 0,
    },
    permissionModuleCount: {
        type: Number,
        default: 0,
    },
    tenantsCount: {
        type: Number,
        default: 0,
    },
});

const page = usePage();
const logoutForm = useForm({});
const rolePermissionForm = useForm({
    role_permissions: {},
});

const dashboardPath = '/platform/dashboard';
const tenantsPath = '/platform/tenants';
const permissionsPath = '/platform/settings/permissions';
const menusPath = '/platform/settings/menus';
const plansPath = '/platform/settings/plans';
const applicationPath = '/platform/settings/application';
const paymentsPath = '/platform/settings/payments';
const vehicleMastersPath = '/platform/settings/vehicle-masters';
const aiAgentPath = '/platform/settings/ai-agent';
const permissionSyncPath = '/platform/settings/permissions/sync';
const tableLoading = ref(false);
let autoSyncTimer = null;
const autoSyncDelay = 450;

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
    sort_by: 'menu_order',
    sort_dir: 'asc',
    per_page: 10,
    cursor: null,
});

watch(
    () => props.permissionFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            sort_by: String(filters?.sort_by || 'menu_order'),
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

const { hasRolePermission, toggleRolePermission, selectedPermissionCount } = useRolePermissionMatrix(
    computed(() => props.roles),
    rolePermissionForm,
);

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const rolePermissionError = computed(() => String(rolePermissionForm.errors?.role_permissions || ''));

const summary = computed(() => ({
    totalRoles: props.roles.length,
    totalPermissions: Number(props.permissionScopeTotal) || Number(props.permissions?.total) || 0,
    totalModules: Number(props.permissionModuleCount) || 0,
    totalRolePermissionLinks: Object.values(rolePermissionForm.role_permissions || {})
        .reduce((total, assignedPermissions) => total + (Array.isArray(assignedPermissions) ? assignedPermissions.length : 0), 0),
}));

const requestTable = (override = {}) => {
    const nextFilters = {
        ...tableFilters.value,
        ...override,
    };

    tableFilters.value = nextFilters;
    fetchPlatformPermissions(permissionsPath, nextFilters, {
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

const syncRolePermissions = (options = {}) => {
    syncPlatformRolePermissions(rolePermissionForm, permissionSyncPath, options);
};

const scheduleRolePermissionSync = () => {
    if (autoSyncTimer) {
        clearTimeout(autoSyncTimer);
    }

    autoSyncTimer = setTimeout(() => {
        syncRolePermissions({
            preserveState: true,
        });
    }, autoSyncDelay);
};

const handleRolePermissionToggle = ({ roleKey, permissionId, checked }) => {
    toggleRolePermission(roleKey, permissionId, checked);
    scheduleRolePermissionSync();
};

onBeforeUnmount(() => {
    if (autoSyncTimer) {
        clearTimeout(autoSyncTimer);
    }
});

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Permission Superadmin" />

    <AppDashboardLayout
        title="Permission"
        subtitle="Kelola role dan hak akses level platform"
        role-label="Superadmin"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div class="space-y-5">
            <PermissionSummaryCards
                :total-roles="summary.totalRoles"
                :total-permissions="summary.totalPermissions"
                :total-modules="summary.totalModules"
                :total-role-permission-links="summary.totalRolePermissionLinks"
            />

            <section class="grid items-start gap-4 lg:grid-cols-4">
                <RolePermissionTable
                    class="lg:col-span-3"
                    :roles="roles"
                    :permissions="permissions"
                    :filters="tableFilters"
                    :flash-status="flashStatus"
                    :table-loading="tableLoading"
                    :error-message="rolePermissionError"
                    :selected-permission-count="selectedPermissionCount"
                    :has-role-permission="hasRolePermission"
                    @search="handleSearch"
                    @sort="handleSort"
                    @per-page="handlePerPage"
                    @page="handlePage"
                    @toggle-role-permission="handleRolePermissionToggle"
                />

                <PermissionCatalogCard
                    class="lg:col-span-1"
                    :permissions="permissions"
                />
            </section>
        </div>
    </AppDashboardLayout>
</template>
