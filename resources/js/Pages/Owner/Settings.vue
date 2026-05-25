<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import { formatDateIndonesia } from '../../Utils/indonesiaDate';
import OwnerSettingsHeader from './Settings/Components/OwnerSettingsHeader.vue';
import OwnerPermissionTable from './Settings/Components/OwnerPermissionTable.vue';
import OwnerPrintSettingCard from './Settings/Components/OwnerPrintSettingCard.vue';
import { useRolePermissionMatrix } from '../../Composables/useRolePermissionMatrix';
import { fetchOwnerPermissions, syncOwnerRolePermissions, updateOwnerPrintSetting } from './Services/ownerSettingsService';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    tenantId: {
        type: String,
        default: '',
    },
    package: {
        type: Object,
        default: null,
    },
    activeTab: {
        type: String,
        default: 'permissions',
    },
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
    permissionFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'menu_label',
            sort_dir: 'asc',
            per_page: 50,
            cursor: null,
        }),
    },
    canManagePermissions: {
        type: Boolean,
        default: false,
    },
    ownerPermissionCount: {
        type: Number,
        default: 0,
    },
    printSetting: {
        type: Object,
        default: () => ({
            printer_name: 'Printer Utama',
            print_type: 'thermal',
            paper_size: '80mm',
        }),
    },
    printPaperSizeOptions: {
        type: Array,
        default: () => [],
    },
    menus: {
        type: Array,
        default: () => [],
    },
    menuItems: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const logoutForm = useForm({});
const rolePermissionForm = useForm({ role_permissions: {} });
const printSettingForm = useForm({
    printer_name: String(props.printSetting?.printer_name || 'Printer Utama'),
    print_type: String(props.printSetting?.print_type || 'thermal'),
    paper_size: String(props.printSetting?.paper_size || '80mm'),
});
const tableLoading = ref(false);
const autoSaveTimer = ref(null);
const hasPendingAutoSave = ref(false);

const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const settingsPath = computed(() => `${baseOwnerPath.value}/settings`);
const permissionSyncPath = computed(() => `${baseOwnerPath.value}/settings/permissions/sync`);
const printSettingSyncPath = computed(() => `${baseOwnerPath.value}/settings/print`);
const printInstallerDownloadPath = computed(() => `${baseOwnerPath.value}/settings/print/kiosk-installer`);
const flashStatus = computed(() => String(page.props?.flash?.status || ''));

const tabItems = computed(() => [
    {
        key: 'permissions',
        label: 'Permission',
        description: 'Atur role dan hak akses',
        href: `${settingsPath.value}?tab=permissions`,
    },
    {
        key: 'nota',
        label: 'Nota',
        description: 'Atur ukuran cetak thermal',
        href: `${settingsPath.value}?tab=nota`,
    },
]);

const currentTab = computed(() => {
    const activeTab = String(props.activeTab || '').trim().toLowerCase();

    return activeTab === 'nota' ? 'nota' : 'permissions';
});
const tableFilters = ref({
    search: '',
    sort_by: 'menu_label',
    sort_dir: 'asc',
    per_page: 50,
    cursor: null,
});

watch(
    () => props.permissionFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            sort_by: String(filters?.sort_by || 'menu_label'),
            sort_dir: String(filters?.sort_dir || 'asc'),
            per_page: Number(filters?.per_page) || 50,
            cursor: filters?.cursor ? String(filters.cursor) : null,
        };
    },
    {
        immediate: true,
        deep: true,
    },
);

const { hasRolePermission, toggleRolePermission, selectedPermissionCount } = useRolePermissionMatrix(
    computed(() => props.roles),
    rolePermissionForm,
);

const rolePermissionError = computed(() => String(rolePermissionForm.errors?.role_permissions || ''));
const requestTable = (override = {}) => {
    const nextFilters = {
        ...tableFilters.value,
        ...override,
    };

    tableFilters.value = nextFilters;
    fetchOwnerPermissions(settingsPath.value, {
        ...nextFilters,
        tab: 'permissions',
    }, {
        onStart: () => {
            tableLoading.value = true;
        },
        onFinish: () => {
            tableLoading.value = false;
        },
    });
};

const handleSearch = (search) => {
    requestTable({
        search,
        cursor: null,
    });
};

const handleSort = ({ key, direction }) => {
    requestTable({
        sort_by: key,
        sort_dir: direction,
        cursor: null,
    });
};

const handlePerPage = (perPage) => {
    requestTable({
        per_page: perPage,
        cursor: null,
    });
};

const handlePage = (payload) => {
    if (payload && typeof payload === 'object' && payload.type === 'cursor') {
        requestTable({
            cursor: String(payload.cursor || ''),
        });
    }
};

const runAutoSave = () => {
    if (!props.canManagePermissions) {
        return;
    }

    if (rolePermissionForm.processing) {
        hasPendingAutoSave.value = true;
        return;
    }

    syncOwnerRolePermissions(rolePermissionForm, permissionSyncPath.value, {
        onFinish: () => {
            if (!hasPendingAutoSave.value) {
                return;
            }

            hasPendingAutoSave.value = false;
            runAutoSave();
        },
    });
};

const scheduleAutoSave = () => {
    hasPendingAutoSave.value = true;

    if (autoSaveTimer.value) {
        clearTimeout(autoSaveTimer.value);
    }

    autoSaveTimer.value = setTimeout(() => {
        autoSaveTimer.value = null;
        if (!hasPendingAutoSave.value) {
            return;
        }

        hasPendingAutoSave.value = false;
        runAutoSave();
    }, 450);
};

const handleRolePermissionToggle = ({ roleKey, permissionId, checked }) => {
    toggleRolePermission(roleKey, permissionId, checked);
    scheduleAutoSave();
};

const handlePrintSettingSubmit = () => {
    updateOwnerPrintSetting(printSettingForm, printSettingSyncPath.value, {
        preserveScroll: true,
    });
};

onBeforeUnmount(() => {
    if (!autoSaveTimer.value) {
        return;
    }

    clearTimeout(autoSaveTimer.value);
    autoSaveTimer.value = null;
});

const packageBadgeText = computed(() => {
    if (!props.package?.plan) {
        return 'Belum ada paket aktif';
    }

    const planName = String(props.package.plan.name || '-');
    const status = String(props.package.status || '-');

    if (status === 'trial' && props.package.trial_ends_at) {
        return `${planName} · Trial s.d. ${formatDateIndonesia(props.package.trial_ends_at)}`;
    }

    if (props.package.expired_at) {
        return `${planName} · Aktif s.d. ${formatDateIndonesia(props.package.expired_at)}`;
    }

    return `${planName} · ${status}`;
});

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Pengaturan Tenant" />

    <AppDashboardLayout
        title="Pengaturan"
        subtitle="Kelola permission tim dan pengaturan nota tenant"
        role-label="Owner"
        :home-href="`${baseOwnerPath}/dashboard`"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div class="space-y-5">
            <OwnerSettingsHeader
                :package-badge-text="packageBadgeText"
                :tab-items="tabItems"
                :current-tab="currentTab"
            />

            <OwnerPermissionTable
                v-if="currentTab === 'permissions'"
                :roles="roles"
                :permissions="permissions"
                :filters="tableFilters"
                :flash-status="flashStatus"
                :can-manage-permissions="canManagePermissions"
                :owner-permission-count="ownerPermissionCount"
                :processing="rolePermissionForm.processing"
                :table-loading="tableLoading"
                :error-message="rolePermissionError"
                :selected-permission-count="selectedPermissionCount"
                :has-role-permission="hasRolePermission"
                @search="handleSearch"
                @sort="handleSort"
                @per-page="handlePerPage"
                @page="handlePage"
                @toggle-role-permission="handleRolePermissionToggle"
            />

            <OwnerPrintSettingCard
                v-else
                :form="printSettingForm"
                :paper-size-options="printPaperSizeOptions"
                :installer-download-path="printInstallerDownloadPath"
                :can-manage="canManagePermissions"
                :flash-status="flashStatus"
                @submit="handlePrintSettingSubmit"
            />
        </div>
    </AppDashboardLayout>
</template>


