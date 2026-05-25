<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import DataTable from '../../Components/UI/DataTable.vue';
import { formatDateIndonesia } from '../../Utils/indonesiaDate';
import {
    fetchPlatformVehicleMasters,
    importPlatformVehicleMasters,
} from './Services/platformVehicleMasterService';

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    vehicleMasterSummary: {
        type: Object,
        default: () => ({
            brands_total: 0,
            brands_active: 0,
            models_total: 0,
            models_active: 0,
            last_synced_at: null,
        }),
    },
    brands: {
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
    brandFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'name',
            sort_dir: 'asc',
            per_page: 10,
            cursor: null,
        }),
    },
});

const page = usePage();
const logoutForm = useForm({});

const dashboardPath = '/platform/dashboard';
const tenantsPath = '/platform/tenants';
const permissionsPath = '/platform/settings/permissions';
const menusPath = '/platform/settings/menus';
const plansPath = '/platform/settings/plans';
const applicationPath = '/platform/settings/application';
const paymentsPath = '/platform/settings/payments';
const aiAgentPath = '/platform/settings/ai-agent';
const vehicleMastersPath = '/platform/settings/vehicle-masters';
const vehicleMasterImportPath = '/platform/settings/vehicle-masters/import';
const vehicleMasterTemplatePath = '/platform/settings/vehicle-masters/template';
const vehicleMasterExportPath = '/platform/settings/vehicle-masters/export';

const currentPath = computed(() => String(page.url || '').split('?')[0] || '');
const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});

const activeSettingsKey = computed(() => {
    const path = currentPath.value;
    if (path === permissionsPath) return 'permissions';
    if (path === menusPath) return 'menus';
    if (path === plansPath) return 'plans';
    if (path === applicationPath) return 'application';
    if (path === paymentsPath) return 'payments';
    if (path === aiAgentPath) return 'ai-agent';
    if (path === vehicleMastersPath) return 'vehicle-masters';

    return '';
});

const isSettingsActive = computed(() => [
    'permissions',
    'menus',
    'application',
    'payments',
    'ai-agent',
    'vehicle-masters',
].includes(activeSettingsKey.value));

const menuItems = computed(() => [
    { key: 'dashboard', label: 'Dasbor', icon: 'dashboard', href: dashboardPath, active: currentPath.value === dashboardPath },
    { key: 'tenants', label: 'Tenant', icon: 'users', href: tenantsPath, active: currentPath.value === tenantsPath },
    { key: 'plans', label: 'Plan', icon: 'billing', href: plansPath, active: activeSettingsKey.value === 'plans' },
    {
        key: 'settings',
        label: 'Pengaturan',
        icon: 'settings',
        active: isSettingsActive.value,
        children: [
            { key: 'permissions', label: 'Permission', href: permissionsPath, active: activeSettingsKey.value === 'permissions' },
            { key: 'menus', label: 'Management Menu', href: menusPath, active: activeSettingsKey.value === 'menus' },
            { key: 'application', label: 'Aplikasi', href: applicationPath, active: activeSettingsKey.value === 'application' },
            { key: 'payments', label: 'Pembayaran', href: paymentsPath, active: activeSettingsKey.value === 'payments' },
            { key: 'vehicle-masters', label: 'Master Kendaraan', href: vehicleMastersPath, active: activeSettingsKey.value === 'vehicle-masters' },
            { key: 'ai-agent', label: 'AI Agent', href: aiAgentPath, active: activeSettingsKey.value === 'ai-agent' },
        ],
    },
]);

const importForm = useForm({
    import_file: null,
    deactivate_missing: false,
});
const importFileInputRef = ref(null);

const importError = computed(() => String(
    importForm.errors?.import_file
    || pageErrors.value?.import_file
    || '',
));

const tableLoading = ref(false);
const tableFilters = ref({
    search: '',
    sort_by: 'name',
    sort_dir: 'asc',
    per_page: 10,
    cursor: null,
});

watch(
    () => props.brandFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            sort_by: String(filters?.sort_by || 'name'),
            sort_dir: String(filters?.sort_dir || 'asc'),
            per_page: Number(filters?.per_page) || 10,
            cursor: filters?.cursor ? String(filters.cursor) : null,
        };
    },
    {
        immediate: true,
        deep: true,
    },
);

const columns = computed(() => [
    { key: 'name', label: 'Merek', sortable: true, headerClass: 'w-56' },
    { key: 'vehicle_type', label: 'Tipe', sortable: true, align: 'center', headerClass: 'w-32' },
    { key: 'model_preview', label: 'Model Terdaftar', headerClass: 'w-[32rem]' },
    { key: 'models_total', label: 'Total Model', sortable: true, align: 'right', headerClass: 'w-28' },
    { key: 'models_active', label: 'Model Aktif', align: 'right', headerClass: 'w-28' },
    { key: 'source', label: 'Sumber', headerClass: 'w-36' },
    { key: 'synced_at', label: 'Sinkron Terakhir', sortable: true, headerClass: 'w-40' },
    { key: 'is_active', label: 'Status', align: 'center', headerClass: 'w-28' },
]);

const rows = computed(() => (Array.isArray(props.brands?.data) ? props.brands.data : []));
const pagination = computed(() => ({
    mode: String(props.brands?.mode || 'cursor'),
    current_page: Number(props.brands?.current_page) || 1,
    last_page: Number(props.brands?.last_page) || 1,
    per_page: Number(props.brands?.per_page) || Number(tableFilters.value.per_page) || 10,
    total: Number(props.brands?.total) || 0,
    from: Number(props.brands?.from) || 0,
    to: Number(props.brands?.to) || 0,
    current_cursor: props.brands?.current_cursor ? String(props.brands.current_cursor) : null,
    next_cursor: props.brands?.next_cursor ? String(props.brands.next_cursor) : null,
    prev_cursor: props.brands?.prev_cursor ? String(props.brands.prev_cursor) : null,
    has_more_pages: Boolean(props.brands?.has_more_pages),
}));

const summaryCards = computed(() => [
    {
        key: 'brands-total',
        label: 'Total Merek',
        value: Number(props.vehicleMasterSummary?.brands_total) || 0,
    },
    {
        key: 'brands-active',
        label: 'Merek Aktif',
        value: Number(props.vehicleMasterSummary?.brands_active) || 0,
    },
    {
        key: 'models-total',
        label: 'Total Model',
        value: Number(props.vehicleMasterSummary?.models_total) || 0,
    },
    {
        key: 'models-active',
        label: 'Model Aktif',
        value: Number(props.vehicleMasterSummary?.models_active) || 0,
    },
]);

const requestTable = (override = {}) => {
    const nextFilters = {
        ...tableFilters.value,
        ...override,
    };

    tableFilters.value = nextFilters;

    fetchPlatformVehicleMasters(vehicleMastersPath, nextFilters, {
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

const handleImportFileChange = (event) => {
    const input = event?.target;
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const [selectedFile] = input.files || [];
    importForm.import_file = selectedFile || null;
};

const submitImport = () => {
    importPlatformVehicleMasters(importForm, vehicleMasterImportPath, {
        onSuccess: () => {
            importForm.clearErrors();
            importForm.reset('import_file');
            if (importFileInputRef.value instanceof HTMLInputElement) {
                importFileInputRef.value.value = '';
            }
        },
    });
};

const resolveVehicleTypeLabel = (vehicleType) => {
    const normalized = String(vehicleType || '').toLowerCase();
    if (normalized === 'motor') {
        return 'Motor';
    }
    if (normalized === 'mobil') {
        return 'Mobil';
    }

    return 'Universal';
};

const resolveVehicleTypeClass = (vehicleType) => {
    const normalized = String(vehicleType || '').toLowerCase();
    if (normalized === 'motor') {
        return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/15 dark:text-sky-300';
    }
    if (normalized === 'mobil') {
        return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-400/30 dark:bg-violet-500/15 dark:text-violet-300';
    }

    return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300';
};

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Master Kendaraan" />

    <AppDashboardLayout
        title="Master Kendaraan"
        subtitle="Kelola sinkronisasi merek dan model kendaraan dari JSON pusat"
        role-label="Superadmin"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <section class="space-y-4">
            <p
                v-if="flashStatus"
                class="rounded-xl border border-emerald-300/70 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300"
            >
                {{ flashStatus }}
            </p>

            <article class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
                <div class="grid gap-3 md:grid-cols-4">
                    <div
                        v-for="card in summaryCards"
                        :key="card.key"
                        class="rounded-xl border border-slate-200/80 bg-slate-50/70 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ card.label }}
                        </p>
                        <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                            {{ card.value }}
                        </p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    Sinkron terakhir:
                    {{ vehicleMasterSummary?.last_synced_at ? formatDateIndonesia(vehicleMasterSummary.last_synced_at) : '-' }}
                </p>
            </article>

            <article class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Template, Import, dan Export</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Upload JSON dilakukan di Super Admin. Owner cukup klik sinkron pada halaman servis.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a
                        :href="vehicleMasterTemplatePath"
                        class="inline-flex h-10 cursor-pointer items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-emerald-500/15"
                    >
                        Download Template
                    </a>
                    <a
                        :href="`${vehicleMasterExportPath}?active_only=1`"
                        class="inline-flex h-10 cursor-pointer items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-emerald-500/15"
                    >
                        Export JSON Aktif
                    </a>
                    <a
                        :href="`${vehicleMasterExportPath}?active_only=0`"
                        class="inline-flex h-10 cursor-pointer items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-400/40 dark:hover:bg-emerald-500/15"
                    >
                        Export Semua Data
                    </a>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitImport">
                    <div class="grid gap-4 md:grid-cols-[1fr_auto]">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200" for="vehicle-master-import-file">
                                Upload File JSON
                            </label>
                            <input
                                id="vehicle-master-import-file"
                                ref="importFileInputRef"
                                type="file"
                                accept=".json,application/json,text/plain"
                                class="block h-11 w-full cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-emerald-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:border-emerald-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:file:bg-emerald-500/20 dark:file:text-emerald-300"
                                @change="handleImportFileChange"
                            >
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Format mengikuti template. Maksimal ukuran file 10MB.
                            </p>
                            <p v-if="importError" class="text-xs text-rose-600 dark:text-rose-300">
                                {{ importError }}
                            </p>
                        </div>

                        <div class="flex items-end">
                            <button
                                type="submit"
                                class="inline-flex h-11 w-full cursor-pointer items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25 md:w-auto"
                                :disabled="importForm.processing"
                            >
                                {{ importForm.processing ? 'Mengimpor...' : 'Import JSON' }}
                            </button>
                        </div>
                    </div>

                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                        <input
                            v-model="importForm.deactivate_missing"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 accent-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:accent-emerald-400"
                        >
                        Nonaktifkan merek/model yang tidak ada di file import terbaru
                    </label>
                </form>
            </article>

            <article class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm dark:border-emerald-500/20 dark:bg-slate-900">
                <h3 class="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">Daftar Merek Master</h3>

                <DataTable
                    :columns="columns"
                    :rows="rows"
                    :pagination="pagination"
                    :filters="tableFilters"
                    :loading="tableLoading"
                    :fixed-layout="true"
                    search-placeholder="Cari merek, sumber, atau model..."
                    empty-text="Tidak ada data"
                    @update:search="handleSearch"
                    @sort="handleSort"
                    @update:per-page="handlePerPage"
                    @page="handlePage"
                >
                    <template #cell-vehicle_type="{ row }">
                        <span
                            class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold"
                            :class="resolveVehicleTypeClass(row.vehicle_type)"
                        >
                            {{ resolveVehicleTypeLabel(row.vehicle_type) }}
                        </span>
                    </template>

                    <template #cell-models_total="{ row }">
                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ row.models_total }}</span>
                    </template>

                    <template #cell-models_active="{ row }">
                        <span class="text-slate-600 dark:text-slate-300">{{ row.models_active }}</span>
                    </template>

                    <template #cell-model_preview="{ row }">
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="modelName in Array.isArray(row.model_preview) ? row.model_preview : []"
                                :key="`${row.id}-${modelName}`"
                                class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ modelName }}
                            </span>
                            <span
                                v-if="Number(row.models_active || 0) > (Array.isArray(row.model_preview) ? row.model_preview.length : 0)"
                                class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                            >
                                +{{ Number(row.models_active || 0) - (Array.isArray(row.model_preview) ? row.model_preview.length : 0) }} model
                            </span>
                        </div>
                    </template>

                    <template #cell-synced_at="{ row }">
                        <span class="text-slate-600 dark:text-slate-300">
                            {{ row.synced_at ? formatDateIndonesia(row.synced_at) : '-' }}
                        </span>
                    </template>

                    <template #cell-is_active="{ row }">
                        <span
                            class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold"
                            :class="row.is_active
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : 'border-slate-300 bg-slate-100 text-slate-600 dark:border-slate-600 dark:bg-slate-700/70 dark:text-slate-300'"
                        >
                            {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </template>
                </DataTable>
            </article>
        </section>
    </AppDashboardLayout>
</template>
