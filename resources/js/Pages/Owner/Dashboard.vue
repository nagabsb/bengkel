<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardTemplate from '../Dashboard/Template.vue';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    roleLabel: {
        type: String,
        default: 'Owner',
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
    visibility: {
        type: Object,
        default: () => ({}),
    },
});

const logoutForm = useForm({});

const subtitle = computed(() => {
    const value = String(props.dashboardSubtitle || '').trim();
    return value !== '' ? value : 'Ringkasan dan statistik utama';
});

const dashboardStats = computed(() => (Array.isArray(props.stats) ? props.stats : []));
const dashboardChart = computed(() => (props.chart && typeof props.chart === 'object' ? props.chart : {}));
const dashboardCategories = computed(() => (Array.isArray(props.categories) ? props.categories : []));
const dashboardTable = computed(() => (props.table && typeof props.table === 'object' ? props.table : {}));
const dashboardActivities = computed(() => (props.activities && typeof props.activities === 'object' ? props.activities : {}));
const dashboardVisibility = computed(() => (props.visibility && typeof props.visibility === 'object' ? props.visibility : {}));

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Dasbor Owner" />

    <DashboardTemplate
        title="Dasbor"
        :subtitle="subtitle"
        :role-label="roleLabel"
        :user="user"
        :menu-items="menuItems"
        :stats="dashboardStats"
        :chart="dashboardChart"
        :categories="dashboardCategories"
        :table="dashboardTable"
        :activities="dashboardActivities"
        :visibility="dashboardVisibility"
        @logout="logout"
    />
</template>
