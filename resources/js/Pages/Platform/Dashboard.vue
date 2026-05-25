<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import DashboardTemplate from '../Dashboard/Template.vue';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    tenants: {
        type: Array,
        default: () => [],
    },
    dashboardSubtitle: {
        type: String,
        default: '',
    },
    stats: {
        type: Array,
        default: () => [],
    },
    chart: {
        type: Object,
        default: () => ({}),
    },
    categories: {
        type: Array,
        default: () => [],
    },
    table: {
        type: Object,
        default: () => ({}),
    },
    activities: {
        type: Object,
        default: () => ({}),
    },
});

const logoutForm = useForm({});
const page = usePage();
const dashboardPath = '/platform/dashboard';
const tenantsPath = '/platform/tenants';
const permissionsPath = '/platform/settings/permissions';
const menusPath = '/platform/settings/menus';
const plansPath = '/platform/settings/plans';
const applicationPath = '/platform/settings/application';
const paymentsPath = '/platform/settings/payments';
const vehicleMastersPath = '/platform/settings/vehicle-masters';
const aiAgentPath = '/platform/settings/ai-agent';

const currentPath = computed(() => String(page.url || '').split('?')[0] || '');

const activeSettingsKey = computed(() => {
    const path = currentPath.value;
    if (path === permissionsPath) {
        return 'permissions';
    }
    if (path === menusPath) {
        return 'menus';
    }
    if (path === plansPath) {
        return 'plans';
    }
    if (path === applicationPath) {
        return 'application';
    }
    if (path === paymentsPath) {
        return 'payments';
    }
    if (path === vehicleMastersPath) {
        return 'vehicle-masters';
    }
    if (path === aiAgentPath) {
        return 'ai-agent';
    }

    const queryString = String(page.url || '').split('?')[1] || '';
    const query = new URLSearchParams(queryString);
    const settingsKey = query.get('settings');

    if (settingsKey === 'permissions' || settingsKey === 'menus' || settingsKey === 'plans' || settingsKey === 'application' || settingsKey === 'payments' || settingsKey === 'vehicle-masters' || settingsKey === 'ai-agent') {
        return settingsKey;
    }

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
            {
                key: 'permissions',
                label: 'Permission',
                href: permissionsPath,
                active: activeSettingsKey.value === 'permissions',
            },
            {
                key: 'menus',
                label: 'Management Menu',
                href: menusPath,
                active: activeSettingsKey.value === 'menus',
            },
            {
                key: 'application',
                label: 'Aplikasi',
                href: applicationPath,
                active: activeSettingsKey.value === 'application',
            },
            {
                key: 'payments',
                label: 'Pembayaran',
                href: paymentsPath,
                active: activeSettingsKey.value === 'payments',
            },
            {
                key: 'vehicle-masters',
                label: 'Master Kendaraan',
                href: vehicleMastersPath,
                active: activeSettingsKey.value === 'vehicle-masters',
            },
            {
                key: 'ai-agent',
                label: 'AI Agent',
                href: aiAgentPath,
                active: activeSettingsKey.value === 'ai-agent',
            },
        ],
    },
]);

const subtitle = computed(() => {
    const value = String(props.dashboardSubtitle || '').trim();
    return value !== '' ? value : 'Ringkasan dan statistik utama';
});

const dashboardStats = computed(() => (Array.isArray(props.stats) ? props.stats : []));
const dashboardChart = computed(() => (props.chart && typeof props.chart === 'object' ? props.chart : {}));
const dashboardCategories = computed(() => (Array.isArray(props.categories) ? props.categories : []));
const dashboardTable = computed(() => (props.table && typeof props.table === 'object' ? props.table : {}));
const dashboardActivities = computed(() => (props.activities && typeof props.activities === 'object' ? props.activities : {}));

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Dasbor Superadmin" />

    <DashboardTemplate
        title="Dasbor"
        :subtitle="subtitle"
        role-label="Superadmin"
        :user="user"
        :menu-items="menuItems"
        :stats="dashboardStats"
        :chart="dashboardChart"
        :categories="dashboardCategories"
        :table="dashboardTable"
        :activities="dashboardActivities"
        @logout="logout"
    />
</template>




