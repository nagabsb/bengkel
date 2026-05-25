<script setup>
import { computed } from 'vue';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    revealStyle: {
        type: Object,
        default: () => ({}),
    },
});

const donutItems = computed(() => {
    if (Array.isArray(props.categories) && props.categories.length > 0) {
        return props.categories;
    }

    return [
        { label: 'Elektronik', percent: 35, color: 'rgb(16 185 129)', dotClass: 'bg-emerald-500' },
        { label: 'Fashion', percent: 25, color: 'rgb(99 102 241)', dotClass: 'bg-indigo-500' },
        { label: 'Makanan', percent: 20, color: 'rgb(245 158 11)', dotClass: 'bg-amber-500' },
        { label: 'Kesehatan', percent: 12, color: 'rgb(6 182 212)', dotClass: 'bg-cyan-500' },
        { label: 'Lainnya', percent: 8, color: 'rgb(148 163 184)', dotClass: 'bg-slate-400' },
    ];
});

const donutTotal = computed(() => donutItems.value.reduce((total, item) => total + (Number(item.percent) || 0), 0));

const donutStyle = computed(() => {
    let cursor = 0;
    const segments = donutItems.value.map((item) => {
        const percent = Number(item.percent) || 0;
        const start = cursor;
        const end = cursor + percent;
        cursor = end;
        return `${item.color} ${start}% ${end}%`;
    });

    return {
        background: `conic-gradient(${segments.join(', ')})`,
    };
});
</script>

<template>
    <article
        class="dashboard-reveal rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm transition-colors motion-safe:transition-transform motion-safe:duration-200 motion-safe:ease-out motion-safe:hover:-translate-y-0.5 dark:border-emerald-400/30 dark:bg-slate-800"
        :style="revealStyle"
    >
        <h2 class="text-2xl font-semibold leading-tight text-slate-900 dark:text-slate-100">Kategori Penjualan</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Distribusi per kategori</p>

        <div class="mt-5">
            <div class="relative mx-auto h-36 w-36">
                <div class="h-full w-full rounded-full" :style="donutStyle" />
                <div class="absolute inset-5 grid place-items-center rounded-full bg-white text-center shadow-inner shadow-slate-200 dark:bg-slate-700 dark:shadow-slate-900">
                    <p class="text-3xl font-bold text-slate-900 dark:text-slate-100">{{ donutTotal }}%</p>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Terjual</p>
                </div>
            </div>
        </div>

        <div class="mt-5 space-y-2">
            <div
                v-for="item in donutItems"
                :key="item.label"
                class="flex items-center justify-between gap-2 text-sm"
            >
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full" :class="item.dotClass" />
                    <span class="text-slate-600 dark:text-slate-300">{{ item.label }}</span>
                </div>
                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ item.percent }}%</span>
            </div>
        </div>
    </article>
</template>
