<script setup>
import { computed } from 'vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import { formatRupiah } from '../../../../Utils/formatCurrency';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const props = defineProps({
    tenants: {
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
            sort_by: 'created_at',
            sort_dir: 'desc',
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
    togglingTenantId: {
        type: String,
        default: null,
    },
    errorMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['create', 'search', 'sort', 'per-page', 'export', 'page', 'edit', 'toggle-status']);

const columns = computed(() => [
    { key: 'name', label: 'Tenant', sortable: true, headerClass: 'w-56' },
    { key: 'code', label: 'Kode', sortable: true, headerClass: 'w-32' },
    { key: 'package', label: 'Paket Aktif' },
    { key: 'usage', label: 'Penggunaan', align: 'center', headerClass: 'w-36' },
    { key: 'is_active', label: 'Status', sortable: true, align: 'center', headerClass: 'w-28' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-64' },
]);

const rows = computed(() => Array.isArray(props.tenants?.data) ? props.tenants.data : []);

const pagination = computed(() => ({
    mode: String(props.tenants?.mode || 'cursor'),
    current_page: Number(props.tenants?.current_page) || 1,
    last_page: Number(props.tenants?.last_page) || 1,
    per_page: Number(props.tenants?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.tenants?.total) || 0,
    from: Number(props.tenants?.from) || 0,
    to: Number(props.tenants?.to) || 0,
    current_cursor: props.tenants?.current_cursor ? String(props.tenants.current_cursor) : null,
    next_cursor: props.tenants?.next_cursor ? String(props.tenants.next_cursor) : null,
    prev_cursor: props.tenants?.prev_cursor ? String(props.tenants.prev_cursor) : null,
    has_more_pages: Boolean(props.tenants?.has_more_pages),
}));
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Daftar Tenant</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kelola tenant, paket langganan, dan status akses platform.</p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                    @click="emit('export')"
                >
                    Export Excel
                </button>

                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    @click="emit('create')"
                >
                    Tambah Tenant
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
                search-placeholder="Cari tenant..."
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #cell-name="{ row }">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.name }}</p>
                        <p
                            v-if="row.phone"
                            class="text-xs text-slate-500 dark:text-slate-400"
                        >
                            No. HP: {{ row.phone }}
                        </p>
                        <p
                            v-if="row.address"
                            class="text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ row.address }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Subdomain:
                            <a
                                v-if="row.subdomain_url"
                                :href="row.subdomain_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-emerald-700 underline decoration-dotted underline-offset-2 hover:text-emerald-600 dark:text-emerald-300 dark:hover:text-emerald-200"
                            >
                                {{ row.subdomain_url }}
                            </a>
                            <span v-else>-</span>
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">ID: {{ row.id }}</p>
                    </div>
                </template>

                <template #cell-code="{ row }">
                    <span class="inline-flex rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ row.code }}
                    </span>
                </template>

                <template #cell-package="{ row }">
                    <div v-if="row.package">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            {{ row.package.plan?.name || '-' }}
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">({{ row.package.price?.label || '-' }})</span>
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ formatRupiah(row.package.price?.amount || 0) }}
                            <span v-if="row.package.price?.duration_months"> / {{ row.package.price.duration_months }} bulan</span>
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Mulai: {{ row.package.started_at ? formatDateIndonesia(row.package.started_at) : '-' }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Berakhir: {{ row.package.expired_at ? formatDateIndonesia(row.package.expired_at) : '-' }}
                        </p>
                    </div>
                    <span
                        v-else
                        class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        Belum paket
                    </span>
                </template>

                <template #cell-usage="{ row }">
                    <div class="text-xs text-slate-600 dark:text-slate-300">
                        <p>{{ row.workshops_count }} bengkel</p>
                        <p>{{ row.users_count }} user</p>
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
                            :disabled="statusProcessing && togglingTenantId === String(row.id)"
                            @click="emit('toggle-status', row)"
                        >
                            {{ statusProcessing && togglingTenantId === String(row.id)
                                ? 'Menyimpan...'
                                : (row.is_active ? 'Nonaktifkan' : 'Aktifkan') }}
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </article>
</template>
