<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerSparePartUnitTableCard from './SparePartUnits/Components/OwnerSparePartUnitTableCard.vue';
import OwnerSparePartUnitFormCard from './SparePartUnits/Components/OwnerSparePartUnitFormCard.vue';
import {
    destroyOwnerSparePartUnit,
    fetchOwnerSparePartUnits,
    storeOwnerSparePartUnit,
    updateOwnerSparePartUnit,
} from './Services/ownerSparePartUnitService';

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
    menuItems: {
        type: Array,
        default: () => [],
    },
    sparePartUnits: {
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
    sparePartUnitFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    sparePartUnitSummary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
        }),
    },
});

const page = usePage();
const logoutForm = useForm({});
const unitForm = useForm({
    name: '',
    symbol: '',
    description: '',
    is_active: true,
});

const tableLoading = ref(false);
const tableFilters = ref({
    search: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});

const editingUnitId = ref(null);
const deletingUnitId = ref(null);
const isUnitModalOpen = ref(false);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const isEditMode = computed(() => String(editingUnitId.value || '').trim() !== '');
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const indexPath = computed(() => `${baseOwnerPath.value}/sparepart-units`);
const storePath = computed(() => `${baseOwnerPath.value}/sparepart-units`);
const updatePath = (unitId) => `${baseOwnerPath.value}/sparepart-units/${unitId}`;
const deletePath = (unitId) => `${baseOwnerPath.value}/sparepart-units/${unitId}`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const unitError = computed(() => String(
    unitForm.errors?.create_sparepart_unit
    || unitForm.errors?.update_sparepart_unit
    || unitForm.errors?.delete_sparepart_unit
    || pageErrors.value?.create_sparepart_unit
    || pageErrors.value?.update_sparepart_unit
    || pageErrors.value?.delete_sparepart_unit
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageUnits = computed(() => (
    permissionNames.value.includes('sparepart_units.manage')
    || permissionNames.value.includes('users.manage')
));

watch(
    () => props.sparePartUnitFilters,
    (filters) => {
        tableFilters.value = {
            search: String(filters?.search || ''),
            sort_by: String(filters?.sort_by || 'created_at'),
            sort_dir: String(filters?.sort_dir || 'desc'),
            per_page: Number(filters?.per_page) || 10,
            cursor: filters?.cursor ? String(filters.cursor) : null,
        };
    },
    {
        immediate: true,
        deep: true,
    },
);

const requestTable = (override = {}) => {
    const nextFilters = {
        ...tableFilters.value,
        ...override,
    };

    tableFilters.value = nextFilters;

    fetchOwnerSparePartUnits(indexPath.value, nextFilters, {
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

const resetForm = () => {
    editingUnitId.value = null;
    unitForm.clearErrors();
    unitForm.name = '';
    unitForm.symbol = '';
    unitForm.description = '';
    unitForm.is_active = true;
};

const openCreateModal = () => {
    if (!canManageUnits.value) {
        return;
    }

    resetForm();
    isUnitModalOpen.value = true;
};

const startEdit = (unit) => {
    if (!canManageUnits.value) {
        return;
    }

    const unitId = String(unit?.id || '').trim();
    if (unitId === '') {
        return;
    }

    editingUnitId.value = unitId;
    unitForm.clearErrors();
    unitForm.name = String(unit?.name || '');
    unitForm.symbol = String(unit?.symbol || '');
    unitForm.description = String(unit?.description || '');
    unitForm.is_active = Boolean(unit?.is_active);
    isUnitModalOpen.value = true;
};

const closeModal = () => {
    isUnitModalOpen.value = false;
    resetForm();
};

const submitForm = () => {
    const payload = (data) => ({
        ...data,
        name: String(data.name || '').trim(),
        symbol: String(data.symbol || '').trim(),
        description: String(data.description || '').trim(),
        is_active: Boolean(data.is_active),
    });

    if (isEditMode.value) {
        updateOwnerSparePartUnit(
            unitForm.transform(payload),
            updatePath(editingUnitId.value),
            {
                onSuccess: closeModal,
            },
        );

        return;
    }

    storeOwnerSparePartUnit(
        unitForm.transform(payload),
        storePath.value,
        {
            onSuccess: closeModal,
        },
    );
};

const deleteUnit = (unit) => {
    if (!canManageUnits.value) {
        return;
    }

    const unitId = String(unit?.id || '').trim();
    if (unitId === '') {
        return;
    }

    if (!window.confirm('Hapus satuan ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    destroyOwnerSparePartUnit(deletePath(unitId), {
        onStart: () => {
            deletingUnitId.value = unitId;
        },
        onFinish: () => {
            deletingUnitId.value = null;
        },
    });
};

const resolvePageScrollContainer = () => {
    if (!pageContentRef.value || typeof pageContentRef.value.closest !== 'function') {
        if (typeof document === 'undefined') {
            return null;
        }

        const fallbackContainer = document.querySelector('.dashboard-scroll');
        if (fallbackContainer instanceof HTMLElement) {
            return fallbackContainer;
        }

        return document.documentElement instanceof HTMLElement ? document.documentElement : null;
    }

    const container = pageContentRef.value.closest('.dashboard-scroll');
    if (container instanceof HTMLElement) {
        return container;
    }

    if (typeof document === 'undefined') {
        return null;
    }

    const fallbackContainer = document.querySelector('.dashboard-scroll');
    if (fallbackContainer instanceof HTMLElement) {
        return fallbackContainer;
    }

    return document.documentElement instanceof HTMLElement ? document.documentElement : null;
};

const setPageScrollLock = (isLocked) => {
    const container = lockedScrollContainer.value ?? resolvePageScrollContainer();
    if (!(container instanceof HTMLElement)) {
        return;
    }

    if (isLocked) {
        lockedScrollContainer.value = container;
        previousOverflow.value = container.style.overflow;
        previousOverflowY.value = container.style.overflowY;
        container.style.overflow = 'hidden';
        container.style.overflowY = 'hidden';
        return;
    }

    container.style.overflow = previousOverflow.value;
    container.style.overflowY = previousOverflowY.value;
    previousOverflow.value = '';
    previousOverflowY.value = '';
    lockedScrollContainer.value = null;
};

watch(
    isUnitModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isUnitModalOpen.value) {
        closeModal();
    }
};

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEscapeKey);
    setPageScrollLock(false);
});

onMounted(() => {
    window.addEventListener('keydown', handleEscapeKey);
});

const logout = () => {
    logoutForm.post('/logout');
};
</script>

<template>
    <Head title="Satuan Sparepart Owner" />

    <AppDashboardLayout
        title="Satuan Sparepart"
        subtitle="Kelola master satuan sparepart tenant Anda"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <OwnerSparePartUnitTableCard
                :units="sparePartUnits"
                :filters="tableFilters"
                :summary="sparePartUnitSummary"
                :flash-status="flashStatus"
                :error-message="unitError"
                :table-loading="tableLoading"
                :form-processing="unitForm.processing"
                :deleting-unit-id="deletingUnitId"
                :can-manage="canManageUnits"
                @create="openCreateModal"
                @edit="startEdit"
                @delete="deleteUnit"
                @search="handleSearch"
                @sort="handleSort"
                @per-page="handlePerPage"
                @page="handlePage"
            />
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isUnitModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeModal"
                />

                <div class="relative z-20 w-full max-w-xl">
                    <OwnerSparePartUnitFormCard
                        :is-edit-mode="isEditMode"
                        :form="unitForm"
                        :errors="pageErrors"
                        @close="closeModal"
                        @submit="submitForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>


