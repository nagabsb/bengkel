<script setup>
import { computed } from 'vue';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import DashboardMetricCard from '../../Components/UI/DashboardMetricCard.vue';
import DashboardActivityCard from './Components/DashboardActivityCard.vue';
import DashboardCategoryCard from './Components/DashboardCategoryCard.vue';
import DashboardChartCard from './Components/DashboardChartCard.vue';
import DashboardDataTableCard from './Components/DashboardDataTableCard.vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Dasbor',
    },
    subtitle: {
        type: String,
        default: 'Ringkasan dan statistik utama',
    },
    roleLabel: {
        type: String,
        default: 'Admin',
    },
    user: {
        type: Object,
        default: () => ({}),
    },
    menuItems: {
        type: Array,
        default: () => [],
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

const emit = defineEmits(['logout']);

const revealStyle = (delay = 0) => ({
    '--reveal-delay': `${delay}ms`,
});

const showStats = computed(() => {
    if (typeof props.visibility?.showStats === 'boolean') {
        return props.visibility.showStats;
    }

    return Array.isArray(props.stats) && props.stats.length > 0;
});

const showChart = computed(() => {
    if (typeof props.visibility?.showChart === 'boolean') {
        return props.visibility.showChart;
    }

    return true;
});

const showCategories = computed(() => {
    if (typeof props.visibility?.showCategories === 'boolean') {
        return props.visibility.showCategories;
    }

    return true;
});

const showTable = computed(() => {
    if (typeof props.visibility?.showTable === 'boolean') {
        return props.visibility.showTable;
    }

    return true;
});

const showActivities = computed(() => {
    if (typeof props.visibility?.showActivities === 'boolean') {
        return props.visibility.showActivities;
    }

    return true;
});
</script>

<template>
    <AppDashboardLayout
        :title="title"
        :subtitle="subtitle"
        :role-label="roleLabel"
        :user="user"
        :menu-items="menuItems"
        @logout="emit('logout')"
    >
        <div class="space-y-5">
            <section v-if="showStats" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <DashboardMetricCard
                    v-for="(stat, statIndex) in stats"
                    :key="`${stat.title}-${statIndex}`"
                    class="dashboard-reveal"
                    :style="revealStyle(50 + statIndex * 45)"
                    :title="stat.title"
                    :value="stat.value"
                    :hint="stat.hint"
                    :trend="stat.trend"
                    :trend-direction="stat.trendDirection"
                    :color="stat.color"
                    :icon="stat.icon"
                    :bars="stat.bars"
                />
            </section>

            <section v-if="showChart || showCategories" class="grid gap-4 xl:grid-cols-4">
                <DashboardChartCard v-if="showChart" :chart="chart" :reveal-style="revealStyle(250)" />
                <DashboardCategoryCard v-if="showCategories" :categories="categories" :reveal-style="revealStyle(300)" />
            </section>

            <section v-if="showTable || showActivities" class="grid gap-4 xl:grid-cols-4">
                <DashboardDataTableCard v-if="showTable" :table="table" :reveal-style="revealStyle(360)" />
                <DashboardActivityCard v-if="showActivities" :activities="activities" :reveal-style="revealStyle(420)" />
            </section>
        </div>
    </AppDashboardLayout>
</template>
