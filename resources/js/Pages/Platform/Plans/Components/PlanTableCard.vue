<script setup>
import { computed } from 'vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import { formatRupiah } from '../../../../Utils/formatCurrency';

const props = defineProps({
    plans: {
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
            sort_by: 'price',
            sort_dir: 'asc',
            per_page: 10,
            cursor: null,
        }),
    },
    flashStatus: {
        type: String,
        default: '',
    },
    tableLoading: {
        type: Boolean,
        default: false,
    },
    statusProcessing: {
        type: Boolean,
        default: false,
    },
    togglingPlanId: {
        type: Number,
        default: null,
    },
    errorMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['create', 'search', 'sort', 'per-page', 'page', 'edit', 'toggle-status']);

const columns = computed(() => [
    { key: 'name', label: 'Plan', sortable: true, headerClass: 'w-52' },
    { key: 'max_workshops', label: 'Bengkel', headerClass: 'w-28' },
    { key: 'user_capacity', label: 'Kapasitas User', headerClass: 'w-40' },
    { key: 'trial', label: 'Trial', headerClass: 'w-28' },
    { key: 'price', label: 'Harga', sortable: true, headerClass: 'w-36' },
    { key: 'menu_count', label: 'Menu', sortable: false, align: 'center', headerClass: 'w-24 px-0', cellClass: 'px-0 align-middle' },
    { key: 'is_active', label: 'Status', sortable: true, align: 'center', headerClass: 'w-32' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-52' },
]);

const rows = computed(() => Array.isArray(props.plans?.data) ? props.plans.data : []);

const pagination = computed(() => ({
    mode: String(props.plans?.mode || 'cursor'),
    current_page: Number(props.plans?.current_page) || 1,
    last_page: Number(props.plans?.last_page) || 1,
    per_page: Number(props.plans?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.plans?.total) || 0,
    from: Number(props.plans?.from) || 0,
    to: Number(props.plans?.to) || 0,
    current_cursor: props.plans?.current_cursor ? String(props.plans.current_cursor) : null,
    next_cursor: props.plans?.next_cursor ? String(props.plans.next_cursor) : null,
    prev_cursor: props.plans?.prev_cursor ? String(props.plans.prev_cursor) : null,
    has_more_pages: Boolean(props.plans?.has_more_pages),
}));
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Daftar Plan</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kelola paket, limit, trial, harga, dan status plan untuk tenant.</p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    @click="emit('create')"
                >
                    Tambah Plan
                </button>

                <span
                    v-if="flashStatus"
                    class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                >
                    {{ flashStatus }}
                </span>
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
                search-placeholder="Cari plan..."
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #cell-name="{ row }">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.slug }}</p>
                    </div>
                </template>

                <template #cell-max_workshops="{ row }">
                    <div class="text-sm text-slate-700 dark:text-slate-200">
                        <p class="font-semibold">{{ row.max_workshops }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">bengkel</p>
                    </div>
                </template>

                <template #cell-user_capacity="{ row }">
                    <div class="text-sm text-slate-700 dark:text-slate-200">
                        <p class="font-semibold">{{ Number(row.max_workshops || 0) * Number(row.max_users_per_ws || 0) }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ row.max_users_per_ws }} user per bengkel
                        </p>
                    </div>
                </template>

                <template #cell-trial="{ row }">
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="row.has_trial
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                    >
                        {{ row.has_trial ? `${row.trial_duration_days} hari` : 'Tidak ada trial' }}
                    </span>
                </template>

                <template #cell-price="{ row }">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ formatRupiah(row.price?.amount ?? 0) }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ row.price?.duration_months ? `${row.price.duration_months} bulan` : '-' }}
                            <span v-if="Number(row.price?.discount_pct) > 0"> - Diskon {{ row.price.discount_pct }}%</span>
                        </p>
                    </div>
                </template>

                <template #cell-menu_count="{ row }">
                    <div class="flex w-full items-center justify-center">
                        <span class="inline-flex h-7 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-xs font-semibold leading-none text-slate-700 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
                            {{ row.menu_count }}
                        </span>
                    </div>
                </template>

                <template #cell-is_active="{ row }">
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="row.is_active
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                            : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                    >
                        {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                            @click="emit('edit', row)"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center rounded-lg border px-2.5 py-1.5 text-sm font-semibold transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="row.is_active
                                ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20'"
                            :disabled="statusProcessing && togglingPlanId === Number(row.id)"
                            @click="emit('toggle-status', row)"
                        >
                            {{ statusProcessing && togglingPlanId === Number(row.id)
                                ? 'Menyimpan...'
                                : (row.is_active ? 'Nonaktifkan' : 'Aktifkan') }}
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </article>
</template>