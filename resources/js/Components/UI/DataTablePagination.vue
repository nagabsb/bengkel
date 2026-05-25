<script setup>
import { computed } from 'vue';

const props = defineProps({
    pagination: {
        type: Object,
        default: () => ({
            current_page: 1,
            last_page: 1,
            total: 0,
            from: 0,
            to: 0,
        }),
    },
});

const emit = defineEmits(['page']);

const isCursorMode = computed(() => String(props.pagination?.mode || 'offset') === 'cursor');
const canPrevious = computed(() => isCursorMode.value
    ? Boolean(props.pagination?.prev_cursor)
    : Number(props.pagination?.current_page) > 1);
const canNext = computed(() => isCursorMode.value
    ? Boolean(props.pagination?.next_cursor)
    : Number(props.pagination?.current_page) < Number(props.pagination?.last_page));

const summaryText = computed(() => {
    if (isCursorMode.value) {
        const visibleCount = Number(props.pagination?.to) || 0;
        const totalCount = Number(props.pagination?.total) || 0;
        if (totalCount > 0) {
            return `Menampilkan ${visibleCount} data dari total ${totalCount}`;
        }

        return `Menampilkan ${visibleCount} data`;
    }

    const from = Number(props.pagination?.from) || 0;
    const to = Number(props.pagination?.to) || 0;
    const total = Number(props.pagination?.total) || 0;

    return `Menampilkan ${from}-${to} dari ${total}`;
});

const pageNumbers = computed(() => {
    const currentPage = Number(props.pagination?.current_page) || 1;
    const lastPage = Number(props.pagination?.last_page) || 1;
    const spread = 2;
    const start = Math.max(1, currentPage - spread);
    const end = Math.min(lastPage, currentPage + spread);

    const pages = [];
    for (let pageNumber = start; pageNumber <= end; pageNumber += 1) {
        pages.push(pageNumber);
    }

    return pages;
});

const goToPage = (pageNumber) => {
    if (isCursorMode.value) {
        return;
    }

    const targetPage = Number(pageNumber) || 1;
    const lastPage = Number(props.pagination?.last_page) || 1;
    if (targetPage < 1 || targetPage > lastPage) {
        return;
    }

    if (targetPage === Number(props.pagination?.current_page)) {
        return;
    }

    emit('page', targetPage);
};

const goCursor = (direction) => {
    const cursor = direction === 'prev'
        ? props.pagination?.prev_cursor
        : props.pagination?.next_cursor;

    if (!cursor) {
        return;
    }

    emit('page', {
        type: 'cursor',
        direction,
        cursor: String(cursor),
    });
};
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ summaryText }}
        </p>

        <div v-if="isCursorMode" class="inline-flex items-center gap-1">
            <button
                type="button"
                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                :disabled="!canPrevious"
                @click="goCursor('prev')"
            >
                Prev
            </button>
            <button
                type="button"
                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                :disabled="!canNext"
                @click="goCursor('next')"
            >
                Next
            </button>
        </div>

        <div v-else class="inline-flex items-center gap-1">
            <button
                type="button"
                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                :disabled="!canPrevious"
                @click="goToPage(1)"
            >
                Awal
            </button>
            <button
                type="button"
                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                :disabled="!canPrevious"
                @click="goToPage((Number(pagination.current_page) || 1) - 1)"
            >
                Prev
            </button>

            <button
                v-for="pageNumber in pageNumbers"
                :key="`datatable-page-${pageNumber}`"
                type="button"
                class="inline-flex h-8 min-w-8 cursor-pointer items-center justify-center rounded-lg border px-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
                :class="pageNumber === Number(pagination.current_page)
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/20 dark:text-emerald-300'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300'"
                @click="goToPage(pageNumber)"
            >
                {{ pageNumber }}
            </button>

            <button
                type="button"
                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                :disabled="!canNext"
                @click="goToPage((Number(pagination.current_page) || 1) + 1)"
            >
                Next
            </button>
            <button
                type="button"
                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                :disabled="!canNext"
                @click="goToPage(Number(pagination.last_page) || 1)"
            >
                Akhir
            </button>
        </div>
    </div>
</template>
