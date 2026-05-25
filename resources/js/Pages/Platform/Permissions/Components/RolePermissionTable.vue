<script setup>
import { computed } from 'vue';
import DataTable from '../../../../Components/UI/DataTable.vue';

const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
    permissions: {
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
            sort_by: 'menu_order',
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
    errorMessage: {
        type: String,
        default: '',
    },
    selectedPermissionCount: {
        type: Function,
        required: true,
    },
    hasRolePermission: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['search', 'sort', 'per-page', 'page', 'toggle-role-permission']);

const columns = computed(() => [
    {
        key: 'name',
        label: 'Permission',
        sortable: true,
        headerClass: 'w-80',
    },
    ...props.roles.map((role) => ({
        key: `role:${role.key}`,
        label: role.name,
        align: 'center',
    })),
]);

const rows = computed(() => Array.isArray(props.permissions?.data) ? props.permissions.data : []);

const pagination = computed(() => ({
    mode: String(props.permissions?.mode || 'cursor'),
    current_page: Number(props.permissions?.current_page) || 1,
    last_page: Number(props.permissions?.last_page) || 1,
    per_page: Number(props.permissions?.per_page) || Number(props.filters?.per_page) || 10,
    total: Number(props.permissions?.total) || 0,
    from: Number(props.permissions?.from) || 0,
    to: Number(props.permissions?.to) || 0,
    current_cursor: props.permissions?.current_cursor ? String(props.permissions.current_cursor) : null,
    next_cursor: props.permissions?.next_cursor ? String(props.permissions.next_cursor) : null,
    prev_cursor: props.permissions?.prev_cursor ? String(props.permissions.prev_cursor) : null,
    has_more_pages: Boolean(props.permissions?.has_more_pages),
}));

const emptyText = computed(() => {
    if (props.roles.length === 0) {
        return 'Belum ada role yang tersedia.';
    }

    return 'Belum ada permission yang bisa diatur.';
});

const roleColumnKey = (roleKey) => `role:${roleKey}`;
</script>

<template>
    <article class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Atur Permission Role</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Atur akses role menggunakan matrix permission, pencarian, sorting, dan pagination.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
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
                search-placeholder="Cari permission..."
                :empty-text="emptyText"
                :row-key="(row) => row.id"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #cell-name="{ row }">
                    <div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.display_name || row.name }}</p>
                        <p class="text-xs capitalize text-slate-500 dark:text-slate-400">
                            {{ row.action || 'access' }} · {{ row.name }}
                        </p>
                    </div>
                </template>

                <template
                    v-for="role in roles"
                    :key="`permission-header-${role.key}`"
                    #[`header-${roleColumnKey(role.key)}`]
                >
                    <div class="space-y-1 text-center normal-case">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ role.name }}</p>
                        <p class="text-[11px] font-medium text-slate-400 dark:text-slate-500">{{ role.scope_label || 'Platform' }}</p>
                        <p class="text-[11px] font-medium text-slate-400 dark:text-slate-500">{{ role.tenant_count || 0 }} tenant</p>
                        <p class="text-[11px] font-medium text-slate-400 dark:text-slate-500">{{ selectedPermissionCount(role.key) }} akses</p>
                    </div>
                </template>

                <template
                    v-for="role in roles"
                    :key="`permission-cell-${role.key}`"
                    #[`cell-${roleColumnKey(role.key)}`]="{ row }"
                >
                    <label class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white p-2 transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-400/40">
                        <input
                            type="checkbox"
                            class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-emerald-600 text-emerald-600 checked:border-emerald-600 checked:bg-emerald-600 focus:ring-emerald-500 dark:accent-emerald-400 dark:border-slate-600 dark:bg-slate-800 dark:checked:border-emerald-400 dark:checked:bg-emerald-400"
                            :checked="hasRolePermission(role.key, row.id)"
                            @change="emit('toggle-role-permission', {
                                roleKey: role.key,
                                permissionId: row.id,
                                checked: $event.target.checked,
                            })"
                        >
                    </label>
                </template>
            </DataTable>
        </div>
    </article>
</template>

