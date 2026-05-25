<script setup>
import { computed, ref, watch } from 'vue';
import { RecycleScroller } from 'vue-virtual-scroller';
import { useDebounce } from '../../Composables/useDebounce';
import DataTablePagination from './DataTablePagination.vue';

const props = defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
    rows: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    emptyText: {
        type: String,
        default: 'Tidak ada data',
    },
    pagination: {
        type: Object,
        default: () => ({
            mode: 'offset',
            current_page: 1,
            last_page: 1,
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
    filters: {
        type: Object,
        default: () => ({
            search: '',
            per_page: 10,
            sort_by: '',
            sort_dir: 'asc',
        }),
    },
    searchPlaceholder: {
        type: String,
        default: 'Cari data...',
    },
    searchContainerClass: {
        type: String,
        default: 'w-full min-w-56 sm:w-72',
    },
    perPageOptions: {
        type: Array,
        default: () => [10, 20, 50],
    },
    rowKey: {
        type: [String, Function],
        default: 'id',
    },
    showSearch: {
        type: Boolean,
        default: true,
    },
    showPerPage: {
        type: Boolean,
        default: true,
    },
    fixedLayout: {
        type: Boolean,
        default: false,
    },
    enableVirtualScroll: {
        type: Boolean,
        default: true,
    },
    virtualThreshold: {
        type: Number,
        default: 100,
    },
    virtualItemSize: {
        type: Number,
        default: 52,
    },
    virtualListClass: {
        type: String,
        default: 'max-h-[560px]',
    },
});

const emit = defineEmits(['update:search', 'update:perPage', 'sort', 'page']);

const localSearch = ref(String(props.filters?.search || ''));
const debouncedSearch = useDebounce(localSearch, 350);

watch(
    () => props.filters?.search,
    (nextSearch) => {
        const normalized = String(nextSearch || '');
        if (normalized === localSearch.value) {
            return;
        }

        localSearch.value = normalized;
    },
);

watch(debouncedSearch, (value) => {
    emit('update:search', String(value || ''));
});

const normalizedPagination = computed(() => ({
    mode: String(props.pagination?.mode || 'offset'),
    current_page: Number(props.pagination?.current_page) || 1,
    last_page: Number(props.pagination?.last_page) || 1,
    per_page: Number(props.pagination?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.pagination?.total) || 0,
    from: Number(props.pagination?.from) || 0,
    to: Number(props.pagination?.to) || 0,
    current_cursor: props.pagination?.current_cursor ? String(props.pagination.current_cursor) : null,
    next_cursor: props.pagination?.next_cursor ? String(props.pagination.next_cursor) : null,
    prev_cursor: props.pagination?.prev_cursor ? String(props.pagination.prev_cursor) : null,
    has_more_pages: Boolean(props.pagination?.has_more_pages),
}));

const isSortableColumn = (column) => Boolean(column?.sortable && column?.key);

const isActiveSort = (columnKey) => String(props.filters?.sort_by || '') === String(columnKey || '');

const sortDirection = computed(() => String(props.filters?.sort_dir || 'asc').toLowerCase() === 'desc' ? 'desc' : 'asc');

const toggleSort = (column) => {
    if (!isSortableColumn(column)) {
        return;
    }

    const currentKey = String(props.filters?.sort_by || '');
    const targetKey = String(column.key);
    const nextDirection = currentKey === targetKey && sortDirection.value === 'asc'
        ? 'desc'
        : 'asc';

    emit('sort', {
        key: targetKey,
        direction: nextDirection,
    });
};

const changePerPage = (event) => {
    const nextPerPage = Number(event?.target?.value) || Number(props.filters?.per_page) || 10;
    emit('update:perPage', nextPerPage);
};

const resolveRowKey = (row, index) => {
    if (typeof props.rowKey === 'function') {
        return props.rowKey(row, index);
    }

    const key = String(props.rowKey || 'id');
    const value = row?.[key];
    if (value === null || value === undefined || value === '') {
        return `row-${index}`;
    }

    return value;
};

const normalizedRows = computed(() => (Array.isArray(props.rows) ? props.rows : []));

const shouldUseVirtualScroll = computed(() => (
    props.enableVirtualScroll
    && !props.loading
    && normalizedRows.value.length >= Math.max(Number(props.virtualThreshold) || 100, 1)
));

const virtualRows = computed(() => normalizedRows.value.map((row, index) => ({
    __virtual_key: String(resolveRowKey(row, index)),
    __virtual_row: row,
    __virtual_index: index,
})));

const virtualGridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${Math.max(props.columns?.length || 0, 1)}, minmax(0, 1fr))`,
}));
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <div
                    v-if="showSearch"
                    class="relative"
                    :class="searchContainerClass"
                >
                    <input
                        v-model="localSearch"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 pr-9 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:border-emerald-400/40"
                    >
                    <span
                        v-if="loading"
                        class="pointer-events-none absolute inset-y-0 right-3 inline-flex items-center text-emerald-500 dark:text-emerald-300"
                        aria-hidden="true"
                    >
                        <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4 animate-spin">
                            <path d="M12 3V6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M12 18V21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M4.93 4.93L7.05 7.05" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M16.95 16.95L19.07 19.07" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M3 12H6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M18 12H21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M4.93 19.07L7.05 16.95" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M16.95 7.05L19.07 4.93" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                    </span>
                </div>

                <slot name="toolbar-filters" />
            </div>

            <label
                v-if="showPerPage"
                class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300"
            >
                <span>Per halaman</span>
                <select
                    class="cursor-pointer rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-700 outline-none transition hover:border-emerald-300 focus:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:focus:border-emerald-400/40"
                    :value="normalizedPagination.per_page"
                    @change="changePerPage"
                >
                    <option
                        v-for="option in perPageOptions"
                        :key="`datatable-per-page-${option}`"
                        :value="option"
                    >
                        {{ option }}
                    </option>
                </select>
            </label>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
            <table class="min-w-full" :class="props.fixedLayout ? 'table-fixed' : ''">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="`datatable-header-${column.key}`"
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                            :class="[column?.headerClass || '', column?.align === 'center' ? 'text-center' : '', column?.align === 'right' ? 'text-right' : '']"
                        >
                            <button
                                v-if="isSortableColumn(column)"
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1 transition hover:text-emerald-700 dark:hover:text-emerald-300"
                                :class="[
                                    column?.align === 'center' ? 'w-full justify-center' : '',
                                    column?.align === 'right' ? 'w-full justify-end' : '',
                                ]"
                                @click="toggleSort(column)"
                            >
                                <slot :name="`header-${column.key}`" :column="column">
                                    <span>{{ column.label }}</span>
                                </slot>
                                <svg viewBox="0 0 20 20" fill="none" class="h-3.5 w-3.5">
                                    <path
                                        d="M6 8L10 4L14 8M14 12L10 16L6 12"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        :class="isActiveSort(column.key) ? 'opacity-100' : 'opacity-35'"
                                    />
                                </svg>
                            </button>
                            <slot
                                v-else
                                :name="`header-${column.key}`"
                                :column="column"
                            >
                                <span>{{ column.label }}</span>
                            </slot>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="loading">
                        <td
                            :colspan="columns.length || 1"
                            class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                        >
                            Memuat data...
                        </td>
                    </tr>
                    <tr v-else-if="normalizedRows.length === 0">
                        <td
                            :colspan="columns.length || 1"
                            class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                        >
                            <slot name="empty">
                                {{ emptyText }}
                            </slot>
                        </td>
                    </tr>
                    <tr v-else-if="shouldUseVirtualScroll">
                        <td
                            :colspan="columns.length || 1"
                            class="p-0"
                        >
                            <RecycleScroller
                                :items="virtualRows"
                                key-field="__virtual_key"
                                :item-size="virtualItemSize"
                                class="w-full"
                                :class="virtualListClass"
                            >
                                <template #default="{ item }">
                                    <div
                                        class="grid border-t border-slate-200 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-200"
                                        :style="virtualGridStyle"
                                    >
                                        <div
                                            v-for="column in columns"
                                            :key="`datatable-virtual-cell-${item.__virtual_key}-${column.key}`"
                                            class="px-4 py-3"
                                            :class="[column?.cellClass || '', column?.align === 'center' ? 'text-center' : '', column?.align === 'right' ? 'text-right' : '']"
                                        >
                                            <slot
                                                :name="`cell-${column.key}`"
                                                :row="item.__virtual_row"
                                                :value="item.__virtual_row?.[column.key]"
                                                :column="column"
                                                :row-index="item.__virtual_index"
                                            >
                                                {{ item.__virtual_row?.[column.key] ?? '-' }}
                                            </slot>
                                        </div>
                                    </div>
                                </template>
                            </RecycleScroller>
                        </td>
                    </tr>
                    <template v-else>
                        <tr
                            v-for="(row, rowIndex) in normalizedRows"
                            :key="resolveRowKey(row, rowIndex)"
                            class="border-t border-slate-200 dark:border-slate-700"
                        >
                            <td
                                v-for="column in columns"
                                :key="`datatable-cell-${resolveRowKey(row, rowIndex)}-${column.key}`"
                                class="align-middle px-4 py-3 text-sm text-slate-700 dark:text-slate-200"
                                :class="[column?.cellClass || '', column?.align === 'center' ? 'text-center' : '', column?.align === 'right' ? 'text-right' : '']"
                            >
                                <slot
                                    :name="`cell-${column.key}`"
                                    :row="row"
                                    :value="row?.[column.key]"
                                    :column="column"
                                    :row-index="rowIndex"
                                >
                                    {{ row?.[column.key] ?? '-' }}
                                </slot>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <DataTablePagination
            :pagination="normalizedPagination"
            @page="emit('page', $event)"
        />
    </div>
</template>

