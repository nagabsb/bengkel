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
            per_page: 50,
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
            sort_by: 'menu_label',
            sort_dir: 'asc',
            per_page: 50,
            cursor: null,
        }),
    },
    flashStatus: {
        type: String,
        default: '',
    },
    canManagePermissions: {
        type: Boolean,
        default: false,
    },
    ownerPermissionCount: {
        type: Number,
        default: 0,
    },
    processing: {
        type: Boolean,
        default: false,
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

const roleLabelMap = {
    admin: 'Admin',
    kasir: 'Kasir',
    mekanik: 'Mekanik',
};

const actionLabelMap = {
    view: 'Lihat',
    manage: 'Kelola',
    create: 'Tambah',
    edit: 'Ubah',
    update: 'Ubah',
    delete: 'Hapus',
    access: 'Akses',
};

const resolveRoleLabel = (roleName) => {
    const normalized = String(roleName || '').trim().toLowerCase();
    if (normalized === '') {
        return '-';
    }

    return roleLabelMap[normalized] || `${normalized.charAt(0).toUpperCase()}${normalized.slice(1)}`;
};

const resolveActionLabel = (action) => {
    const normalized = String(action || '').trim().toLowerCase();
    return actionLabelMap[normalized] || actionLabelMap.access;
};

const resolveActionBadgeClass = (action) => {
    const normalized = String(action || '').trim().toLowerCase();
    if (normalized === 'view') {
        return 'border-sky-200 bg-sky-100 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/15 dark:text-sky-300';
    }

    if (normalized === 'manage' || normalized === 'create' || normalized === 'edit' || normalized === 'update' || normalized === 'delete') {
        return 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    return 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200';
};

const columns = computed(() => [
    {
        key: 'menu_label',
        label: 'Menu',
        sortable: true,
        headerClass: 'w-52',
    },
    {
        key: 'submenu_label',
        label: 'Sub Menu',
        sortable: true,
        headerClass: 'w-64',
    },
    {
        key: 'name',
        label: 'Hak Akses',
        sortable: true,
        headerClass: 'w-72',
    },
    ...props.roles.map((role) => ({
        key: `role:${role.key}`,
        label: resolveRoleLabel(role.name),
        align: 'center',
    })),
]);

const rows = computed(() => {
    const sourceRows = Array.isArray(props.permissions?.data) ? props.permissions.data : [];
    let previousMenu = '';
    let previousSubMenu = '';

    return sourceRows.map((row, index) => {
        const menuLabel = String(row?.menu_label || '-');
        const subMenuLabel = String(row?.submenu_label || menuLabel || '-');
        const shouldShowMenu = index === 0 || menuLabel !== previousMenu;
        const shouldShowSubMenu = index === 0 || menuLabel !== previousMenu || subMenuLabel !== previousSubMenu;

        previousMenu = menuLabel;
        previousSubMenu = subMenuLabel;

        return {
            ...row,
            menu_label_display: shouldShowMenu ? menuLabel : '',
            submenu_label_display: shouldShowSubMenu ? subMenuLabel : '',
        };
    });
});

const pagination = computed(() => ({
    mode: String(props.permissions?.mode || 'cursor'),
    current_page: Number(props.permissions?.current_page) || 1,
    last_page: Number(props.permissions?.last_page) || 1,
    per_page: Number(props.permissions?.per_page) || Number(props.filters?.per_page) || 50,
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
        return 'Role admin/kasir/mekanik belum tersedia di tenant ini.';
    }

    return 'Belum ada permission dalam scope owner.';
});

const roleColumnKey = (roleKey) => `role:${roleKey}`;
</script>

<template>
    <section class="rounded-2xl border border-emerald-100 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Atur Permission Tim</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Owner mengatur role admin/kasir/mekanik sesuai scope menu aktif dan plan tenant.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                >
                    Cakupan owner: {{ ownerPermissionCount }} hak akses
                </span>
                <span
                    class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                    :class="processing
                        ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-300'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300'"
                >
                    {{ processing ? 'Menyimpan otomatis...' : 'Simpan otomatis aktif' }}
                </span>
                <span
                    v-if="flashStatus"
                    class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                >
                    {{ flashStatus }}
                </span>
            </div>
        </header>

        <div
            v-if="!canManagePermissions"
            class="border-b border-amber-100 bg-amber-50 px-5 py-3 text-sm font-medium text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300"
        >
            Mode hanya-baca. Hanya owner yang bisa mengubah hak akses tim.
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
                :per-page-options="[50]"
                search-placeholder="Cari menu, sub menu, atau permission..."
                :empty-text="emptyText"
                :row-key="(row, index) => row?.row_key || `${row?.id || 'permission'}-${index}`"
                @update:search="emit('search', $event)"
                @sort="emit('sort', $event)"
                @update:per-page="emit('per-page', $event)"
                @page="emit('page', $event)"
            >
                <template #cell-menu_label="{ row }">
                    <div class="min-h-6">
                        <p
                            class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                            :class="row.menu_label_display === '' ? 'opacity-0' : ''"
                        >
                            {{ row.menu_label_display || '-' }}
                        </p>
                    </div>
                </template>

                <template #cell-submenu_label="{ row }">
                    <div class="min-h-6">
                        <p
                            class="text-sm text-slate-700 dark:text-slate-200"
                            :class="row.submenu_label_display === '' ? 'opacity-0' : ''"
                        >
                            {{ row.submenu_label_display || '-' }}
                        </p>
                        <p
                            v-if="row.menu_path && row.submenu_label_display !== ''"
                            class="text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ row.menu_path }}
                        </p>
                    </div>
                </template>

                <template #cell-name="{ row }">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.name }}</p>
                            <span
                                class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                :class="resolveActionBadgeClass(row.action)"
                            >
                                {{ resolveActionLabel(row.action) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Kode: {{ row.name }}</p>
                    </div>
                </template>

                <template
                    v-for="role in roles"
                    :key="`owner-permission-header-${role.key}`"
                    #[`header-${roleColumnKey(role.key)}`]
                >
                    <div class="space-y-1 text-center normal-case">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ resolveRoleLabel(role.name) }}</p>
                        <p class="text-[11px] font-medium text-slate-400 dark:text-slate-500">{{ selectedPermissionCount(role.key) }} akses</p>
                    </div>
                </template>

                <template
                    v-for="role in roles"
                    :key="`owner-permission-cell-${role.key}`"
                    #[`cell-${roleColumnKey(role.key)}`]="{ row }"
                >
                    <label class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white p-2 transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-400/40">
                        <input
                            type="checkbox"
                            class="h-5 w-5 cursor-pointer rounded border-slate-300 text-emerald-600 accent-emerald-600 focus:ring-2 focus:ring-emerald-400/50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:accent-emerald-400"
                            :disabled="!canManagePermissions || processing"
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
    </section>
</template>
