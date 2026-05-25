<script setup>
import { computed, ref, watch } from 'vue';
import AsyncSelect from '../../../../Components/UI/AsyncSelect.vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import DatePicker from '../../../../Components/UI/DatePicker.vue';
import { formatNumber, formatRupiah } from '../../../../Utils/formatCurrency';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const props = defineProps({
    expenses: {
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
    filters: {
        type: Object,
        default: () => ({
            search: '',
            category: '',
            sort_by: 'expense_date',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
            period: 'all',
            date_from: '',
            date_to: '',
            workshop_id: '',
        }),
    },
    expenseSummary: {
        type: Object,
        default: () => ({
            total_entries: 0,
            total_amount: 0,
            this_month_entries: 0,
            this_month_amount: 0,
            period_label: 'Bulan Ini',
        }),
    },
    expenseRecapByWorkshop: {
        type: Array,
        default: () => [],
    },
    expenseCategoryOptions: {
        type: Array,
        default: () => [],
    },
    workshopOptions: {
        type: Array,
        default: () => [],
    },
    isGlobalWorkshopFilter: {
        type: Boolean,
        default: false,
    },
    activeWorkshop: {
        type: Object,
        default: () => ({
            id: '',
            name: '',
            code: '',
        }),
    },
    flashStatus: {
        type: String,
        default: '',
    },
    errorMessage: {
        type: String,
        default: '',
    },
    tableLoading: {
        type: Boolean,
        default: false,
    },
    formProcessing: {
        type: Boolean,
        default: false,
    },
    deletingExpenseId: {
        type: String,
        default: null,
    },
    canManage: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    'create',
    'edit',
    'delete',
    'search',
    'category',
    'period',
    'date-range',
    'workshop',
    'sort',
    'per-page',
    'page',
]);

const columns = computed(() => [
    { key: 'expense_date', label: 'Tanggal', sortable: true, headerClass: 'w-40' },
    { key: 'description', label: 'Deskripsi', sortable: true, headerClass: 'w-96' },
    { key: 'category', label: 'Kategori', sortable: true, headerClass: 'w-44' },
    { key: 'workshop_name', label: 'Cabang', headerClass: 'w-52' },
    { key: 'amount', label: 'Nominal', sortable: true, align: 'right', headerClass: 'w-40' },
    { key: 'created_at', label: 'Dicatat', sortable: true, headerClass: 'w-40' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-40' },
]);

const rows = computed(() => Array.isArray(props.expenses?.data) ? props.expenses.data : []);

const pagination = computed(() => ({
    mode: String(props.expenses?.mode || 'cursor'),
    current_page: Number(props.expenses?.current_page) || 1,
    last_page: Number(props.expenses?.last_page) || 1,
    per_page: Number(props.expenses?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.expenses?.total) || 0,
    from: Number(props.expenses?.from) || 0,
    to: Number(props.expenses?.to) || 0,
    current_cursor: props.expenses?.current_cursor ? String(props.expenses.current_cursor) : null,
    next_cursor: props.expenses?.next_cursor ? String(props.expenses.next_cursor) : null,
    prev_cursor: props.expenses?.prev_cursor ? String(props.expenses.prev_cursor) : null,
    has_more_pages: Boolean(props.expenses?.has_more_pages),
}));

const categoryFilterOptions = computed(() => {
    const options = (Array.isArray(props.expenseCategoryOptions)
        ? props.expenseCategoryOptions
            .map((category) => String(category || '').trim())
            .filter((category, index, categories) => category !== '' && categories.indexOf(category) === index)
        : []
    ).map((category) => ({
        value: category,
        label: category,
    }));

    return [
        { value: '', label: 'Semua kategori' },
        ...options,
    ];
});

const workshopFilterOptions = computed(() => {
    const sourceOptions = Array.isArray(props.workshopOptions) ? props.workshopOptions : [];
    const deduplicatedOptions = [];
    const seen = new Set();

    sourceOptions.forEach((workshopOption) => {
        const value = String(workshopOption?.value || '').trim();
        const label = String(workshopOption?.label || '').trim();
        const subtitle = String(workshopOption?.subtitle || '').trim();
        if (value === '' || label === '') {
            return;
        }

        const key = value.toLowerCase();
        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        deduplicatedOptions.push({
            value,
            label,
            subtitle,
        });
    });

    return deduplicatedOptions;
});

const workshopAsyncOptions = computed(() => ([
    { value: '', label: 'Semua cabang' },
    ...workshopFilterOptions.value.map((workshopOption) => ({
        value: workshopOption.value,
        label: workshopOption.subtitle
            ? `${workshopOption.label} (${workshopOption.subtitle})`
            : workshopOption.label,
    })),
]));

const canUseWorkshopFilter = computed(() => Boolean(props.isGlobalWorkshopFilter) && workshopFilterOptions.value.length > 0);
const selectedWorkshopFilter = computed(() => String(props.filters?.workshop_id || '').trim());

const periodOptions = [
    { value: 'all', label: 'Semua' },
    { value: 'daily', label: 'Harian' },
    { value: 'weekly', label: 'Mingguan' },
    { value: 'monthly', label: 'Bulanan' },
];

const activePeriod = computed(() => {
    const normalizedPeriod = String(props.filters?.period || 'all').trim().toLowerCase();
    return periodOptions.some((periodOption) => periodOption.value === normalizedPeriod)
        ? normalizedPeriod
        : 'all';
});

const periodLabel = computed(() => {
    const label = String(props.expenseSummary?.period_label || '').trim();
    return label !== '' ? label : 'Bulan Ini';
});

const recapRows = computed(() => (
    Array.isArray(props.expenseRecapByWorkshop)
        ? props.expenseRecapByWorkshop.map((row) => ({
            workshop_name: String(row?.workshop_name || 'Cabang').trim() || 'Cabang',
            workshop_code: String(row?.workshop_code || '-').trim() || '-',
            total_entries: Number(row?.total_entries || 0),
            total_amount: Number(row?.total_amount || 0),
        }))
        : []
));

const activeWorkshopLabel = computed(() => {
    const name = String(props.activeWorkshop?.name || '').trim();
    const code = String(props.activeWorkshop?.code || '').trim();
    if (name === '' && code === '') {
        return '-';
    }

    return [name, code !== '' ? `(${code})` : ''].filter((segment) => segment !== '').join(' ');
});

const kpiCards = computed(() => [
    {
        key: 'total-transactions',
        title: 'Total Transaksi',
        value: formatNumber(Number(props.expenseSummary?.total_entries) || 0),
        hint: 'Seluruh periode',
        dotClass: 'bg-slate-500 dark:bg-slate-300',
        cardClass: 'border-slate-200 bg-gradient-to-br from-slate-50 to-white dark:border-slate-700 dark:from-slate-800 dark:to-slate-900',
        valueClass: 'text-slate-900 dark:text-slate-100',
    },
    {
        key: 'total-expense',
        title: 'Total Pengeluaran',
        value: formatRupiah(Number(props.expenseSummary?.total_amount) || 0),
        hint: 'Akumulasi nominal',
        dotClass: 'bg-emerald-500 dark:bg-emerald-300',
        cardClass: 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-white dark:border-emerald-400/30 dark:from-emerald-500/10 dark:to-slate-900',
        valueClass: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        key: 'period-transactions',
        title: `Transaksi ${periodLabel.value}`,
        value: formatNumber(Number(props.expenseSummary?.this_month_entries) || 0),
        hint: 'Filter periode aktif',
        dotClass: 'bg-blue-500 dark:bg-blue-300',
        cardClass: 'border-blue-200 bg-gradient-to-br from-blue-50 to-white dark:border-blue-400/30 dark:from-blue-500/10 dark:to-slate-900',
        valueClass: 'text-blue-700 dark:text-blue-300',
    },
    {
        key: 'period-expense',
        title: `Pengeluaran ${periodLabel.value}`,
        value: formatRupiah(Number(props.expenseSummary?.this_month_amount) || 0),
        hint: 'Nominal periode terpilih',
        dotClass: 'bg-amber-500 dark:bg-amber-300',
        cardClass: 'border-amber-200 bg-gradient-to-br from-amber-50 to-white dark:border-amber-400/30 dark:from-amber-500/10 dark:to-slate-900',
        valueClass: 'text-amber-700 dark:text-amber-300',
    },
]);

const dateFromValue = ref(null);
const dateToValue = ref(null);

const parseFilterDate = (value) => {
    const normalized = String(value || '').trim();
    if (normalized === '') {
        return null;
    }

    const parsedDate = new Date(`${normalized}T00:00:00`);
    return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
};

const formatFilterDate = (value) => {
    if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
        return '';
    }

    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

watch(
    () => [String(props.filters?.date_from || ''), String(props.filters?.date_to || '')],
    ([nextDateFrom, nextDateTo]) => {
        dateFromValue.value = parseFilterDate(nextDateFrom);
        dateToValue.value = parseFilterDate(nextDateTo);
    },
    {
        immediate: true,
    },
);

const applyDateRangeFilter = () => {
    let normalizedDateFrom = dateFromValue.value;
    let normalizedDateTo = dateToValue.value;

    if (
        normalizedDateFrom instanceof Date
        && normalizedDateTo instanceof Date
        && normalizedDateFrom.getTime() > normalizedDateTo.getTime()
    ) {
        [normalizedDateFrom, normalizedDateTo] = [normalizedDateTo, normalizedDateFrom];
        dateFromValue.value = normalizedDateFrom;
        dateToValue.value = normalizedDateTo;
    }

    emit('date-range', {
        date_from: formatFilterDate(normalizedDateFrom),
        date_to: formatFilterDate(normalizedDateTo),
    });
};

const handleDateFromUpdate = (value) => {
    dateFromValue.value = value instanceof Date && !Number.isNaN(value.getTime()) ? value : null;
    applyDateRangeFilter();
};

const handleDateToUpdate = (value) => {
    dateToValue.value = value instanceof Date && !Number.isNaN(value.getTime()) ? value : null;
    applyDateRangeFilter();
};

const handlePeriodChange = (period) => {
    emit('period', String(period || 'all').trim() || 'all');
};

const handleCategoryFilter = (value) => {
    emit('category', String(value || '').trim());
};

const handleWorkshopFilter = (value) => {
    emit('workshop', String(value || '').trim());
};
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="space-y-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Rekap Pengeluaran</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Catat pengeluaran operasional dan pantau total biaya berdasarkan cabang aktif.
                    </p>
                </div>

                <div class="flex flex-col items-end gap-2">
                    <span class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:border-blue-400/30 dark:bg-blue-500/15 dark:text-blue-300">
                        Filter bengkel: {{ activeWorkshopLabel }}
                    </span>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button
                            v-if="canManage"
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                            @click="emit('create')"
                        >
                            Tambah Pengeluaran
                        </button>

                        <span
                            v-if="flashStatus"
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                        >
                            {{ flashStatus }}
                        </span>
                    </div>
                </div>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in kpiCards"
                    :key="card.key"
                    class="relative overflow-hidden rounded-xl border px-3.5 py-3 shadow-sm"
                    :class="card.cardClass"
                >
                    <span
                        class="absolute right-3.5 top-3 inline-flex h-2.5 w-2.5 rounded-full"
                        :class="card.dotClass"
                        aria-hidden="true"
                    />
                    <p class="pr-4 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {{ card.title }}
                    </p>
                    <p class="mt-1 truncate text-lg font-bold leading-tight sm:text-xl" :class="card.valueClass">
                        {{ card.value }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                        {{ card.hint }}
                    </p>
                </article>
            </section>

            <div v-if="recapRows.length > 0" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="(recap, recapIndex) in recapRows"
                    :key="`expense-recap-${recap.workshop_code}-${recapIndex}`"
                    class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 dark:border-slate-700 dark:bg-slate-800/40"
                >
                    <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">
                        {{ recap.workshop_name }}
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">({{ recap.workshop_code }})</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ recap.total_entries }} transaksi
                    </p>
                    <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                        {{ formatRupiah(recap.total_amount) }}
                    </p>
                </article>
            </div>
        </header>

        <div
            v-if="errorMessage"
            class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm font-medium text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300"
        >
            {{ errorMessage }}
        </div>

        <div class="p-4">
            <DataTable
                :columns="columns"
                :rows="rows"
                :pagination="pagination"
                :filters="filters"
                :loading="tableLoading"
                :fixed-layout="true"
                search-placeholder="Cari deskripsi, kategori, referensi..."
                search-container-class="w-full lg:w-60 xl:w-64 2xl:w-72"
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #toolbar-filters>
                    <div class="w-32 shrink-0 xl:w-36">
                        <AsyncSelect
                            :model-value="activePeriod"
                            :options="periodOptions"
                            placeholder="Periode"
                            search-placeholder="Cari periode..."
                            empty-text="Periode tidak ditemukan."
                            :clearable="false"
                            :trigger-class="'h-10 border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-white focus-visible:ring-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-slate-900 dark:focus-visible:ring-emerald-400/30'"
                            @update:model-value="handlePeriodChange"
                        />
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <div class="w-36 xl:w-40">
                            <DatePicker
                                :model-value="dateFromValue"
                                placeholder="Dari tanggal"
                                :hide-input-icon="true"
                                :clearable="true"
                                appearance="field"
                                @update:model-value="handleDateFromUpdate"
                            />
                        </div>
                        <div class="w-36 xl:w-40">
                            <DatePicker
                                :model-value="dateToValue"
                                placeholder="Sampai tanggal"
                                :hide-input-icon="true"
                                :clearable="true"
                                appearance="field"
                                @update:model-value="handleDateToUpdate"
                            />
                        </div>
                    </div>

                    <div class="w-36 shrink-0 xl:w-44">
                        <AsyncSelect
                            :model-value="String(filters?.category || '')"
                            :options="categoryFilterOptions"
                            placeholder="Semua kategori"
                            search-placeholder="Cari kategori..."
                            empty-text="Kategori tidak ditemukan."
                            :clearable="false"
                            :trigger-class="'h-10 border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-white focus-visible:ring-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-slate-900 dark:focus-visible:ring-emerald-400/30'"
                            @update:model-value="handleCategoryFilter"
                        />
                    </div>

                    <div
                        v-if="canUseWorkshopFilter"
                        class="w-40 shrink-0 xl:w-52"
                    >
                        <AsyncSelect
                            :model-value="selectedWorkshopFilter"
                            :options="workshopAsyncOptions"
                            placeholder="Semua cabang"
                            search-placeholder="Cari cabang..."
                            empty-text="Cabang tidak ditemukan."
                            :clearable="false"
                            :trigger-class="'h-10 border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-white focus-visible:ring-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-slate-900 dark:focus-visible:ring-emerald-400/30'"
                            @update:model-value="handleWorkshopFilter"
                        />
                    </div>
                </template>

                <template #cell-expense_date="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ row.expense_date ? formatDateIndonesia(row.expense_date) : '-' }}
                    </span>
                </template>

                <template #cell-description="{ row }">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            {{ row.description || '-' }}
                        </p>
                        <p v-if="row.reference_number" class="text-xs text-slate-500 dark:text-slate-400">
                            Ref: {{ row.reference_number }}
                        </p>
                        <p v-if="row.notes" class="line-clamp-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ row.notes }}
                        </p>
                    </div>
                </template>

                <template #cell-category="{ row }">
                    <span class="inline-flex h-6 max-w-full items-center rounded-full bg-blue-50 px-2.5 text-xs font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                        <span class="truncate">{{ row.category || '-' }}</span>
                    </span>
                </template>

                <template #cell-workshop_name="{ row }">
                    <div class="space-y-0.5">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ row.workshop_name || '-' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.workshop_code || '-' }}</p>
                    </div>
                </template>

                <template #cell-amount="{ row }">
                    <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                        {{ formatRupiah(row.amount) }}
                    </span>
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-sm text-slate-600 dark:text-slate-300">
                        {{ row.created_at ? formatDateIndonesia(row.created_at) : '-' }}
                    </span>
                </template>

                <template #cell-actions="{ row }">
                    <div v-if="canManage" class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                            :disabled="formProcessing"
                            @click="emit('edit', row)"
                        >
                            Edit
                        </button>

                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                            :disabled="formProcessing || deletingExpenseId === String(row.id)"
                            @click="emit('delete', row)"
                        >
                            {{ deletingExpenseId === String(row.id) ? 'Menghapus...' : 'Hapus' }}
                        </button>
                    </div>
                    <span v-else class="text-sm text-slate-400 dark:text-slate-500">-</span>
                </template>
            </DataTable>
        </div>
    </article>
</template>
