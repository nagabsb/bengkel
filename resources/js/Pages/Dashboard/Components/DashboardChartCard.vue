<script setup>
import { computed } from 'vue';
import { useThemeMode } from '../../../Composables/useThemeMode';

const props = defineProps({
    chart: {
        type: Object,
        default: () => ({}),
    },
    revealStyle: {
        type: Object,
        default: () => ({}),
    },
});

const { isDark } = useThemeMode();

const defaultMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const defaultValues = [36, 48, 42, 62, 58, 75, 68, 81, 72, 95, 88, 108];

const chartMonths = computed(() => {
    if (Array.isArray(props.chart?.months) && props.chart.months.length > 0) {
        return props.chart.months;
    }

    return defaultMonths;
});

const chartValues = computed(() => {
    if (Array.isArray(props.chart?.values) && props.chart.values.length > 0) {
        return props.chart.values.map((value) => Number(value) || 0);
    }

    return defaultValues;
});

const chartMeta = computed(() => ({
    title: props.chart?.title || 'Grafik Pendapatan',
    subtitle: props.chart?.subtitle || 'Pendapatan vs pengeluaran',
    filters: props.chart?.filters || ['3 Bulan', '6 Bulan', '12 Bulan'],
    activeFilter: props.chart?.activeFilter || '12 Bulan',
    types: props.chart?.types || ['Area', 'Batang'],
    activeType: props.chart?.activeType || 'Area',
}));

const chartWidth = 920;
const chartHeight = 260;
const chartPaddingX = 20;
const chartPaddingY = 20;

const chartPoints = computed(() => {
    const values = chartValues.value;
    if (values.length === 0) {
        return [];
    }

    const min = Math.min(...values);
    const max = Math.max(...values);
    const xStep = values.length > 1 ? (chartWidth - chartPaddingX * 2) / (values.length - 1) : 0;
    const heightSpan = Math.max(max - min, 1);

    return values.map((value, index) => {
        const x = chartPaddingX + xStep * index;
        const normalized = (value - min) / heightSpan;
        const y = chartHeight - chartPaddingY - normalized * (chartHeight - chartPaddingY * 2);
        return { x, y, value };
    });
});

const linePath = computed(() => {
    if (chartPoints.value.length === 0) {
        return '';
    }

    return chartPoints.value
        .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
        .join(' ');
});

const areaPath = computed(() => {
    if (chartPoints.value.length === 0) {
        return '';
    }

    const firstPoint = chartPoints.value[0];
    const lastPoint = chartPoints.value[chartPoints.value.length - 1];

    return `${linePath.value} L ${lastPoint.x.toFixed(2)} ${(chartHeight - chartPaddingY).toFixed(2)} L ${firstPoint.x.toFixed(2)} ${(chartHeight - chartPaddingY).toFixed(2)} Z`;
});

const yAxisTicks = computed(() => {
    const values = chartValues.value;
    if (values.length === 0) {
        return [];
    }

    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = Math.max(max - min, 1);

    return [0, 1, 2, 3, 4].map((index) => {
        const ratio = index / 4;
        const y = chartHeight - chartPaddingY - ratio * (chartHeight - chartPaddingY * 2);
        return {
            y,
            label: Math.round(min + ratio * range),
        };
    });
});
</script>

<template>
    <article
        class="dashboard-reveal rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm transition-colors motion-safe:transition-transform motion-safe:duration-200 motion-safe:ease-out motion-safe:hover:-translate-y-0.5 dark:border-emerald-400/30 dark:bg-slate-800 xl:col-span-3"
        :style="revealStyle"
    >
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ chartMeta.title }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ chartMeta.subtitle }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-600 dark:bg-slate-700/80">
                    <button
                        v-for="filter in chartMeta.filters"
                        :key="filter"
                        type="button"
                        class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold transition active:scale-95"
                        :class="filter === chartMeta.activeFilter
                            ? 'bg-white text-emerald-700 shadow-sm dark:bg-slate-600 dark:text-emerald-200'
                            : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                    >
                        {{ filter }}
                    </button>
                </div>

                <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-600 dark:bg-slate-700/80">
                    <button
                        v-for="type in chartMeta.types"
                        :key="type"
                        type="button"
                        class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold transition active:scale-95"
                        :class="type === chartMeta.activeType
                            ? 'bg-white text-emerald-700 shadow-sm dark:bg-slate-600 dark:text-emerald-200'
                            : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                    >
                        {{ type }}
                    </button>
                </div>
            </div>
        </header>

        <div class="mt-5">
            <div class="dashboard-scroll relative overflow-x-auto">
                <svg :viewBox="`0 0 ${chartWidth} ${chartHeight}`" class="h-64 w-full" aria-hidden="true">
                    <line
                        v-for="tick in yAxisTicks"
                        :key="`tick-${tick.y}`"
                        x1="0"
                        :y1="tick.y"
                        :x2="chartWidth"
                        :y2="tick.y"
                        :stroke="isDark ? 'rgb(51 65 85)' : 'rgb(226 232 240)'"
                        stroke-dasharray="4 6"
                    />
                    <path :d="areaPath" :fill="isDark ? 'rgb(30 58 138)' : 'rgb(219 234 254)'" opacity="0.55" />
                    <path
                        :d="linePath"
                        fill="none"
                        :stroke="isDark ? 'rgb(96 165 250)' : 'rgb(37 99 235)'"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </div>

            <div class="mt-3 grid grid-cols-6 gap-2 text-xs font-medium text-slate-400 dark:text-slate-500 sm:grid-cols-12">
                <span v-for="month in chartMonths" :key="month">{{ month }}</span>
            </div>
        </div>
    </article>
</template>
