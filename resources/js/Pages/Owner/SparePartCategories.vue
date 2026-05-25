<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerSparePartCategoryTableCard from './SparePartCategories/Components/OwnerSparePartCategoryTableCard.vue';
import OwnerSparePartCategoryFormCard from './SparePartCategories/Components/OwnerSparePartCategoryFormCard.vue';
import {
    destroyOwnerSparePartCategory,
    fetchOwnerSparePartCategories,
    storeOwnerSparePartCategory,
    updateOwnerSparePartCategory,
} from './Services/ownerSparePartCategoryService';

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
    sparePartCategories: {
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
    sparePartCategoryFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    sparePartCategorySummary: {
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
const categoryForm = useForm({
    name: '',
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

const editingCategoryId = ref(null);
const deletingCategoryId = ref(null);
const isCategoryModalOpen = ref(false);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const isEditMode = computed(() => String(editingCategoryId.value || '').trim() !== '');
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const indexPath = computed(() => `${baseOwnerPath.value}/sparepart-categories`);
const storePath = computed(() => `${baseOwnerPath.value}/sparepart-categories`);
const updatePath = (categoryId) => `${baseOwnerPath.value}/sparepart-categories/${categoryId}`;
const deletePath = (categoryId) => `${baseOwnerPath.value}/sparepart-categories/${categoryId}`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const categoryError = computed(() => String(
    categoryForm.errors?.create_sparepart_category
    || categoryForm.errors?.update_sparepart_category
    || categoryForm.errors?.delete_sparepart_category
    || pageErrors.value?.create_sparepart_category
    || pageErrors.value?.update_sparepart_category
    || pageErrors.value?.delete_sparepart_category
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageCategories = computed(() => (
    permissionNames.value.includes('sparepart_categories.manage')
    || permissionNames.value.includes('users.manage')
));

watch(
    () => props.sparePartCategoryFilters,
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

    fetchOwnerSparePartCategories(indexPath.value, nextFilters, {
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
    editingCategoryId.value = null;
    categoryForm.clearErrors();
    categoryForm.name = '';
    categoryForm.description = '';
    categoryForm.is_active = true;
};

const openCreateModal = () => {
    if (!canManageCategories.value) {
        return;
    }

    resetForm();
    isCategoryModalOpen.value = true;
};

const startEdit = (category) => {
    if (!canManageCategories.value) {
        return;
    }

    const categoryId = String(category?.id || '').trim();
    if (categoryId === '') {
        return;
    }

    editingCategoryId.value = categoryId;
    categoryForm.clearErrors();
    categoryForm.name = String(category?.name || '');
    categoryForm.description = String(category?.description || '');
    categoryForm.is_active = Boolean(category?.is_active);
    isCategoryModalOpen.value = true;
};

const closeModal = () => {
    isCategoryModalOpen.value = false;
    resetForm();
};

const submitForm = () => {
    const payload = (data) => ({
        ...data,
        name: String(data.name || '').trim(),
        description: String(data.description || '').trim(),
        is_active: Boolean(data.is_active),
    });

    if (isEditMode.value) {
        updateOwnerSparePartCategory(
            categoryForm.transform(payload),
            updatePath(editingCategoryId.value),
            {
                onSuccess: closeModal,
            },
        );

        return;
    }

    storeOwnerSparePartCategory(
        categoryForm.transform(payload),
        storePath.value,
        {
            onSuccess: closeModal,
        },
    );
};

const deleteCategory = (category) => {
    if (!canManageCategories.value) {
        return;
    }

    const categoryId = String(category?.id || '').trim();
    if (categoryId === '') {
        return;
    }

    if (!window.confirm('Hapus kategori ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    destroyOwnerSparePartCategory(deletePath(categoryId), {
        onStart: () => {
            deletingCategoryId.value = categoryId;
        },
        onFinish: () => {
            deletingCategoryId.value = null;
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
    isCategoryModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isCategoryModalOpen.value) {
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
    <Head title="Kategori Sparepart Owner" />

    <AppDashboardLayout
        title="Kategori Sparepart"
        subtitle="Kelola master kategori sparepart tenant Anda"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <OwnerSparePartCategoryTableCard
                :categories="sparePartCategories"
                :filters="tableFilters"
                :summary="sparePartCategorySummary"
                :flash-status="flashStatus"
                :error-message="categoryError"
                :table-loading="tableLoading"
                :form-processing="categoryForm.processing"
                :deleting-category-id="deletingCategoryId"
                :can-manage="canManageCategories"
                @create="openCreateModal"
                @edit="startEdit"
                @delete="deleteCategory"
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
                v-if="isCategoryModalOpen"
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
                    <OwnerSparePartCategoryFormCard
                        :is-edit-mode="isEditMode"
                        :form="categoryForm"
                        :errors="pageErrors"
                        @close="closeModal"
                        @submit="submitForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>


