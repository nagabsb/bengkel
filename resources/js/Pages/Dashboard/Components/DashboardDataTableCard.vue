<script setup>
import { computed } from 'vue';

const props = defineProps({
    table: {
        type: Object,
        default: () => ({}),
    },
    revealStyle: {
        type: Object,
        default: () => ({}),
    },
});

const tableMeta = computed(() => ({
    title: props.table?.title || 'Pesanan Terbaru',
    subtitle: props.table?.subtitle || '6 data terbaru',
    actionLabel: props.table?.actionLabel || 'Lihat Semua',
    columns: props.table?.columns || [],
    rows: props.table?.rows || [],
}));

const statusClass = (status) => {
    if (status === 'aktif' || status === 'selesai') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
    }

    if (status === 'proses' || status === 'trial') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
    }

    if (status === 'pending') {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300';
    }

    if (status === 'nonaktif' || status === 'gagal') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300';
    }

    return 'bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-300';
};
</script>

<template>
    <article
        class="dashboard-reveal rounded-2xl border border-emerald-100 bg-white shadow-sm transition-colors motion-safe:transition-transform motion-safe:duration-200 motion-safe:ease-out motion-safe:hover:-translate-y-0.5 dark:border-emerald-400/30 dark:bg-slate-800 xl:col-span-3"
        :style="revealStyle"
    >
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h2 class="text-2xl font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ tableMeta.title }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ tableMeta.subtitle }}</p>
            </div>

            <button
                type="button"
                class="inline-flex w-full cursor-pointer items-center justify-center gap-1 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25 sm:w-auto"
            >
                {{ tableMeta.actionLabel }}
                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                    <path d="M7 17L17 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                    <path d="M9 7H17V15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </header>

        <div class="space-y-3 p-4 md:hidden">
            <article
                v-for="(row, rowIndex) in tableMeta.rows"
                :key="`mobile-row-${row.id || rowIndex}`"
                class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-600 dark:bg-slate-700/80"
            >
                <div class="space-y-2">
                    <div
                        v-for="column in tableMeta.columns"
                        :key="`mobile-${rowIndex}-${column.key}`"
                        class="flex items-start justify-between gap-3"
                    >
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ column.label }}</span>
                        <span class="text-right text-sm font-medium text-slate-700 dark:text-slate-200">
                            <span
                                v-if="column.key === 'status'"
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                :class="statusClass(String(row[column.key] || '').toLowerCase())"
                            >
                                {{ row[column.key] }}
                            </span>
                            <span v-else>{{ row[column.key] }}</span>
                        </span>
                    </div>
                </div>
            </article>

            <p v-if="tableMeta.rows.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-600 dark:bg-slate-700/80 dark:text-slate-300">
                Belum ada data.
            </p>
        </div>

        <div class="dashboard-scroll hidden overflow-x-auto md:block">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th
                            v-for="column in tableMeta.columns"
                            :key="column.key"
                            class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, rowIndex) in tableMeta.rows"
                        :key="`row-${rowIndex}`"
                        class="border-t border-slate-100 dark:border-slate-700"
                    >
                        <td
                            v-for="column in tableMeta.columns"
                            :key="`${rowIndex}-${column.key}`"
                            class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600 dark:text-slate-300"
                        >
                            <span
                                v-if="column.key === 'status'"
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                :class="statusClass(String(row[column.key] || '').toLowerCase())"
                            >
                                {{ row[column.key] }}
                            </span>
                            <span v-else class="font-medium text-slate-700 dark:text-slate-200">{{ row[column.key] }}</span>
                        </td>
                    </tr>
                    <tr v-if="tableMeta.rows.length === 0">
                        <td :colspan="tableMeta.columns.length || 1" class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            Belum ada data.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </article>
</template>
