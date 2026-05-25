<script setup>
import { computed } from 'vue';
import DataTable from '../../../../Components/UI/DataTable.vue';
import { formatNumber } from '../../../../Utils/formatCurrency';
import { formatDateIndonesia } from '../../../../Utils/indonesiaDate';

const props = defineProps({
    agents: {
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
            sort_by: 'priority_order',
            sort_dir: 'asc',
            per_page: 10,
            cursor: null,
        }),
    },
    summary: {
        type: Object,
        default: () => ({
            total_agents: 0,
            active_agents: 0,
            default_agent_id: null,
        }),
    },
    failoverOrder: {
        type: Array,
        default: () => [],
    },
    flashStatus: {
        type: String,
        default: '',
    },
    flashStatusLevel: {
        type: String,
        default: 'success',
    },
    tableLoading: {
        type: Boolean,
        default: false,
    },
    statusProcessing: {
        type: Boolean,
        default: false,
    },
    defaultProcessing: {
        type: Boolean,
        default: false,
    },
    deleteProcessing: {
        type: Boolean,
        default: false,
    },
    testProcessing: {
        type: Boolean,
        default: false,
    },
    togglingAgentId: {
        type: Number,
        default: null,
    },
    defaultingAgentId: {
        type: Number,
        default: null,
    },
    deletingAgentId: {
        type: Number,
        default: null,
    },
    testingAgentId: {
        type: Number,
        default: null,
    },
    errorMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'create',
    'search',
    'sort',
    'per-page',
    'page',
    'edit',
    'toggle-status',
    'set-default',
    'test',
    'delete',
]);

const columns = computed(() => [
    { key: 'priority_order', label: 'Urutan', sortable: true, headerClass: 'w-24', align: 'center' },
    { key: 'name', label: 'Agent', sortable: true, headerClass: 'w-64' },
    { key: 'quota_label', label: 'Kuota', headerClass: 'w-28' },
    { key: 'monthly_token_limit', label: 'Limit Token', headerClass: 'w-28' },
    { key: 'last_tested_at', label: 'Terakhir Test', sortable: true, headerClass: 'w-48' },
    { key: 'status', label: 'Status', sortable: false, headerClass: 'w-56' },
    { key: 'actions', label: 'Aksi', align: 'right', headerClass: 'w-60' },
]);

const rows = computed(() => Array.isArray(props.agents?.data) ? props.agents.data : []);

const pagination = computed(() => ({
    mode: String(props.agents?.mode || 'cursor'),
    current_page: Number(props.agents?.current_page) || 1,
    last_page: Number(props.agents?.last_page) || 1,
    per_page: Number(props.agents?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.agents?.total) || 0,
    from: Number(props.agents?.from) || 0,
    to: Number(props.agents?.to) || 0,
    current_cursor: props.agents?.current_cursor ? String(props.agents.current_cursor) : null,
    next_cursor: props.agents?.next_cursor ? String(props.agents.next_cursor) : null,
    prev_cursor: props.agents?.prev_cursor ? String(props.agents.prev_cursor) : null,
    has_more_pages: Boolean(props.agents?.has_more_pages),
}));

const failoverLabel = computed(() => {
    if (!Array.isArray(props.failoverOrder) || props.failoverOrder.length === 0) {
        return 'Belum ada urutan failover aktif.';
    }

    return props.failoverOrder
        .map((agent, index) => `#${index + 1} ${String(agent?.name || '-')}`)
        .join(' -> ');
});

const flashStatusClasses = computed(() => {
    return props.flashStatusLevel === 'error'
        ? 'border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm font-medium text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300'
        : 'border-b border-emerald-100 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
});

const formatMaskedApiKey = (value, maxLength = 20) => {
    const normalized = String(value || '').trim();

    if (normalized === '') {
        return 'Belum diset';
    }

    if (normalized.length <= maxLength) {
        return normalized;
    }

    return `${normalized.slice(0, Math.max(3, maxLength - 3))}...`;
};

const formatLastTestedAt = (value) => {
    if (!value) {
        return '-';
    }

    return formatDateIndonesia(value);
};
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div class="space-y-1">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Daftar Agent AI</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Kelola default agent, prioritas failover, kuota, dan status koneksi.</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Urutan failover: {{ failoverLabel }}</p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:border-slate-600 dark:bg-slate-700/70 dark:text-slate-200">
                    {{ summary.active_agents }} / {{ summary.total_agents }} aktif
                </span>

                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25"
                    @click="emit('create')"
                >
                    Tambah Agent
                </button>
            </div>
        </header>

        <div
            v-if="flashStatus"
            :class="flashStatusClasses"
        >
            {{ flashStatus }}
        </div>

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
                search-placeholder="Cari agent..."
                empty-text="Tidak ada data"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #cell-priority_order="{ row }">
                    <span class="inline-flex min-w-10 items-center justify-center rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
                        {{ row.priority_order }}
                    </span>
                </template>

                <template #cell-name="{ row }">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.provider_label }} • {{ row.agent_model }}</p>
                        <p
                            class="text-xs text-slate-500 dark:text-slate-400"
                            :title="row.has_api_key ? row.masked_api_key : 'Belum diset'"
                        >
                            API Key: {{ formatMaskedApiKey(row.has_api_key ? row.masked_api_key : '') }}
                        </p>
                    </div>
                </template>

                <template #cell-quota_label="{ row }">
                    <div class="max-w-28">
                        <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200" :title="row.quota_label">{{ row.quota_label }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400" :title="`Terpakai: ${formatNumber(row.used_token_count)} token`">
                            Pakai: {{ formatNumber(row.used_token_count) }}
                        </p>
                    </div>
                </template>

                <template #cell-monthly_token_limit="{ row }">
                    <div class="max-w-28">
                        <p class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.monthly_token_limit !== null ? formatNumber(row.monthly_token_limit) : '-' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Token/bln</p>
                    </div>
                </template>

                <template #cell-last_tested_at="{ row }">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ formatLastTestedAt(row.last_tested_at) }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Sukses {{ row.test_success_count }} • Gagal {{ row.test_failed_count }}</p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="row.is_active
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                        >
                            {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>

                        <span
                            v-if="row.is_default"
                            class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-500/20 dark:text-blue-300"
                        >
                            Default
                        </span>

                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="row.is_failover_enabled
                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'
                                : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                        >
                            {{ row.is_failover_enabled ? 'Failover On' : 'Failover Off' }}
                        </span>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex w-full min-w-[220px] flex-nowrap items-center justify-end gap-1">
                        <button
                            type="button"
                            class="grid h-8 w-8 cursor-pointer place-items-center rounded-lg border transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="row.is_default
                                ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-300'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-blue-400/40 dark:hover:text-blue-300'"
                            :disabled="row.is_default || (defaultProcessing && defaultingAgentId === Number(row.id))"
                            :title="defaultProcessing && defaultingAgentId === Number(row.id) ? 'Memproses default...' : (row.is_default ? 'Agent default aktif' : 'Jadikan default')"
                            :aria-label="row.is_default ? 'Agent default aktif' : 'Jadikan default'"
                            @click="emit('set-default', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M12 3L14.8 8.7L21 9.6L16.5 14L17.6 20.2L12 17.2L6.4 20.2L7.5 14L3 9.6L9.2 8.7L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            class="grid h-8 w-8 cursor-pointer place-items-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                            :disabled="testProcessing && testingAgentId === Number(row.id)"
                            :title="testProcessing && testingAgentId === Number(row.id) ? 'Sedang test koneksi...' : 'Test API key'"
                            aria-label="Test API key"
                            @click="emit('test', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M10 3V8L5.5 15.5C4.8 16.8 5.7 18.5 7.2 18.5H16.8C18.3 18.5 19.2 16.8 18.5 15.5L14 8V3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M8 12.5H16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            class="grid h-8 w-8 cursor-pointer place-items-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 active:scale-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:text-emerald-300"
                            title="Edit agent"
                            aria-label="Edit agent"
                            @click="emit('edit', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M4 20H8L18 10L14 6L4 16V20Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                                <path d="M12.5 7.5L16.5 11.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            class="grid h-8 w-8 cursor-pointer place-items-center rounded-lg border transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="row.is_active
                                ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20'"
                            :disabled="statusProcessing && togglingAgentId === Number(row.id)"
                            :title="statusProcessing && togglingAgentId === Number(row.id)
                                ? 'Menyimpan status...'
                                : (row.is_active ? 'Nonaktifkan agent' : 'Aktifkan agent')"
                            :aria-label="row.is_active ? 'Nonaktifkan agent' : 'Aktifkan agent'"
                            @click="emit('toggle-status', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M12 3V12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                <path d="M6 6.5C4.5 7.9 3.5 10 3.5 12.2C3.5 16.8 7.2 20.5 11.8 20.5C16.4 20.5 20.1 16.8 20.1 12.2C20.1 10 19.1 7.9 17.6 6.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            class="grid h-8 w-8 cursor-pointer place-items-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20"
                            :disabled="deleteProcessing && deletingAgentId === Number(row.id)"
                            :title="deleteProcessing && deletingAgentId === Number(row.id) ? 'Menghapus agent...' : 'Hapus agent'"
                            aria-label="Hapus agent"
                            @click="emit('delete', row)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M5 7H19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                <path d="M9 7V5C9 4.4 9.4 4 10 4H14C14.6 4 15 4.4 15 5V7" stroke="currentColor" stroke-width="1.6" />
                                <path d="M7 7L8 19C8.1 19.6 8.5 20 9.1 20H14.9C15.5 20 15.9 19.6 16 19L17 7" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                                <path d="M10 11V16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                <path d="M14 11V16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </article>
</template>



