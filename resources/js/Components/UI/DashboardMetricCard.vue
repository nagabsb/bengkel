<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: String,
        required: true,
    },
    hint: {
        type: String,
        default: '',
    },
    trend: {
        type: String,
        default: '',
    },
    trendDirection: {
        type: String,
        default: 'up',
    },
    color: {
        type: String,
        default: 'emerald',
    },
    icon: {
        type: String,
        default: 'dashboard',
    },
    bars: {
        type: Array,
        default: () => [35, 55, 42, 63, 48, 70, 54, 76, 58, 82, 65, 90],
    },
});

const paletteMap = {
    emerald: {
        cardBorder: 'border-emerald-100 dark:border-emerald-400/30',
        iconWrap: 'bg-emerald-50 text-emerald-600 border-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300',
        trendWrap: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
        bar: 'bg-emerald-300 dark:bg-emerald-500/35',
        barActive: 'bg-emerald-500 dark:bg-emerald-400',
    },
    indigo: {
        cardBorder: 'border-indigo-100 dark:border-indigo-400/30',
        iconWrap: 'bg-indigo-50 text-indigo-600 border-indigo-100 dark:border-indigo-400/30 dark:bg-indigo-500/10 dark:text-indigo-300',
        trendWrap: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
        bar: 'bg-indigo-300 dark:bg-indigo-500/35',
        barActive: 'bg-indigo-500 dark:bg-indigo-400',
    },
    amber: {
        cardBorder: 'border-amber-100 dark:border-amber-400/30',
        iconWrap: 'bg-amber-50 text-amber-600 border-amber-100 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300',
        trendWrap: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
        bar: 'bg-amber-300 dark:bg-amber-500/35',
        barActive: 'bg-amber-500 dark:bg-amber-400',
    },
    rose: {
        cardBorder: 'border-rose-100 dark:border-rose-400/30',
        iconWrap: 'bg-rose-50 text-rose-600 border-rose-100 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300',
        trendWrap: 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',
        bar: 'bg-rose-300 dark:bg-rose-500/35',
        barActive: 'bg-rose-500 dark:bg-rose-400',
    },
};

const iconMap = {
    currency: [
        'M12 3V21',
        'M16.5 7.5C16.5 6.1 14.7 5 12.5 5C10.3 5 8.5 6.1 8.5 7.5C8.5 8.9 10.3 10 12.5 10C14.7 10 16.5 11.1 16.5 12.5C16.5 13.9 14.7 15 12.5 15C10.3 15 8.5 13.9 8.5 12.5',
    ],
    users: [
        'M16 19V17.5C16 15.6 14.4 14 12.5 14H8.5C6.6 14 5 15.6 5 17.5V19',
        'M10.5 10C12.4 10 14 8.4 14 6.5C14 4.6 12.4 3 10.5 3C8.6 3 7 4.6 7 6.5C7 8.4 8.6 10 10.5 10Z',
        'M18 8H21',
        'M19.5 6.5V9.5',
    ],
    orders: [
        'M4 5H6L8 15H18L20 8H7.5',
        'M9 19C9 19.6 8.6 20 8 20C7.4 20 7 19.6 7 19C7 18.4 7.4 18 8 18C8.6 18 9 18.4 9 19Z',
        'M18 19C18 19.6 17.6 20 17 20C16.4 20 16 19.6 16 19C16 18.4 16.4 18 17 18C17.6 18 18 18.4 18 19Z',
    ],
    conversion: [
        'M4 16L10 10L13 13L20 6',
        'M14 6H20V12',
    ],
    dashboard: [
        'M4 4H10V10H4V4Z',
        'M14 4H20V10H14V4Z',
        'M4 14H10V20H4V14Z',
        'M14 14H20V20H14V14Z',
    ],
};

const palette = computed(() => paletteMap[props.color] || paletteMap.emerald);

const normalizedBars = computed(() => {
    if (!Array.isArray(props.bars) || props.bars.length === 0) {
        return [35, 55, 42, 63, 48, 70, 54, 76, 58, 82, 65, 90];
    }

    return props.bars.map((value) => {
        const numericValue = Number(value);
        if (Number.isNaN(numericValue)) {
            return 40;
        }

        return Math.min(100, Math.max(15, numericValue));
    });
});

const iconPaths = computed(() => iconMap[props.icon] || iconMap.dashboard);
</script>

<template>
    <article
        class="rounded-2xl border bg-white p-5 shadow-sm transition-colors motion-safe:transition-transform motion-safe:duration-200 motion-safe:ease-out motion-safe:hover:-translate-y-0.5 dark:bg-slate-800 dark:shadow-none"
        :class="palette.cardBorder"
    >
        <div class="mb-4 flex items-start justify-between gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-xl border" :class="palette.iconWrap">
                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                    <path
                        v-for="(pathValue, pathIndex) in iconPaths"
                        :key="`${pathValue}-${pathIndex}`"
                        :d="pathValue"
                        stroke="currentColor"
                        stroke-width="1.7"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </span>

            <span v-if="trend" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold" :class="palette.trendWrap">
                <svg viewBox="0 0 24 24" fill="none" class="h-3.5 w-3.5" aria-hidden="true">
                    <path
                        :d="trendDirection === 'down' ? 'M6 9L12 15L18 9' : 'M6 15L12 9L18 15'"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                {{ trend }}
            </span>
        </div>

        <p class="text-[2rem] font-bold leading-none tracking-tight text-slate-900 dark:text-slate-100">{{ value }}</p>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ title }}</p>

        <div class="mt-4 flex h-11 items-end gap-1.5">
            <span
                v-for="(barValue, barIndex) in normalizedBars"
                :key="`${title}-${barIndex}`"
                class="w-full rounded-md transition"
                :class="barIndex === normalizedBars.length - 1 ? palette.barActive : palette.bar"
                :style="{ height: `${barValue}%` }"
            />
        </div>

        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">{{ hint }}</p>
    </article>
</template>
