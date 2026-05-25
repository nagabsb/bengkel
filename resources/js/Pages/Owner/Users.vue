<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppDashboardLayout from '../../Layouts/AppDashboardLayout.vue';
import OwnerUserTableCard from './Users/Components/OwnerUserTableCard.vue';
import OwnerUserFormCard from './Users/Components/OwnerUserFormCard.vue';
import {
    destroyOwnerUser,
    fetchOwnerUsers,
    storeOwnerUser,
    updateOwnerUser,
} from './Services/ownerUserService';

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
    users: {
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
    userFilters: {
        type: Object,
        default: () => ({
            search: '',
            sort_by: 'created_at',
            sort_dir: 'desc',
            per_page: 10,
            cursor: null,
        }),
    },
    userSummary: {
        type: Object,
        default: () => ({
            total: 0,
            admin: 0,
            mekanik: 0,
        }),
    },
});

const page = usePage();
const logoutForm = useForm({});
const userForm = useForm({
    workshop_id: '',
    name: '',
    email: '',
    password: '',
    role: 'admin',
});

const tableLoading = ref(false);
const tableFilters = ref({
    search: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
    per_page: 10,
    cursor: null,
});

const editingUserId = ref(null);
const deletingUserId = ref(null);
const isUserModalOpen = ref(false);

const pageContentRef = ref(null);
const lockedScrollContainer = ref(null);
const previousOverflow = ref('');
const previousOverflowY = ref('');

const isEditMode = computed(() => String(editingUserId.value || '').trim() !== '');
const baseOwnerPath = computed(() => `/owner/${props.tenantId}`);
const dashboardPath = computed(() => `${baseOwnerPath.value}/dashboard`);
const usersPath = computed(() => `${baseOwnerPath.value}/users`);
const userStorePath = computed(() => `${baseOwnerPath.value}/users`);
const userUpdatePath = (userId) => `${baseOwnerPath.value}/users/${userId}`;
const userDeletePath = (userId) => `${baseOwnerPath.value}/users/${userId}`;

const flashStatus = computed(() => String(page.props?.flash?.status || ''));
const pageErrors = computed(() => page.props?.errors || {});
const userError = computed(() => String(
    userForm.errors?.create_user
    || userForm.errors?.update_user
    || userForm.errors?.delete_user
    || pageErrors.value?.create_user
    || pageErrors.value?.update_user
    || pageErrors.value?.delete_user
    || '',
));
const permissionNames = computed(() => (
    Array.isArray(page.props?.permissions)
        ? page.props.permissions.map((permission) => String(permission || '').trim())
        : []
));
const canManageUsers = computed(() => permissionNames.value.includes('users.manage'));
const workshopSwitcher = computed(() => {
    const payload = page.props?.ownerWorkshopSwitcher;
    return payload && typeof payload === 'object' ? payload : null;
});
const activeWorkshopId = computed(() => String(workshopSwitcher.value?.active_workshop_id || '').trim());
const isGlobalWorkshopFilter = computed(() => activeWorkshopId.value === '__all__');
const workshopOptions = computed(() => (
    Array.isArray(workshopSwitcher.value?.workshops)
        ? workshopSwitcher.value.workshops
            .map((workshop) => ({
                value: String(workshop?.id || '').trim(),
                label: String(workshop?.name || '').trim(),
                subtitle: String(workshop?.code || '').trim(),
                is_all: Boolean(workshop?.is_all),
            }))
            .filter((workshop) => workshop.value !== '' && workshop.label !== '' && !workshop.is_all)
        : []
));

const resolveDefaultWorkshopId = () => {
    if (activeWorkshopId.value !== '' && activeWorkshopId.value !== '__all__') {
        return activeWorkshopId.value;
    }

    return String(workshopOptions.value[0]?.value || '').trim();
};

watch(
    () => props.userFilters,
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

    fetchOwnerUsers(usersPath.value, nextFilters, {
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

const resetUserForm = () => {
    editingUserId.value = null;
    userForm.clearErrors();
    userForm.workshop_id = resolveDefaultWorkshopId();
    userForm.name = '';
    userForm.email = '';
    userForm.password = '';
    userForm.role = 'admin';
};

const openCreateUserModal = () => {
    if (!canManageUsers.value) {
        return;
    }

    resetUserForm();
    isUserModalOpen.value = true;
};

const startEditUser = (managedUser) => {
    if (!canManageUsers.value) {
        return;
    }

    const userId = String(managedUser?.id || '').trim();
    if (userId === '') {
        return;
    }

    editingUserId.value = userId;
    userForm.clearErrors();
    userForm.workshop_id = String(managedUser?.workshop_id || '').trim() || resolveDefaultWorkshopId();
    userForm.name = String(managedUser?.name || '');
    userForm.email = String(managedUser?.email || '');
    userForm.password = '';
    userForm.role = String(managedUser?.role || 'admin');
    isUserModalOpen.value = true;
};

const closeUserModal = () => {
    isUserModalOpen.value = false;
    resetUserForm();
};

const submitUserForm = () => {
    const payload = (data) => ({
        ...data,
        workshop_id: String(data.workshop_id || '').trim(),
        name: String(data.name || '').trim(),
        email: String(data.email || '').trim().toLowerCase(),
        password: String(data.password || ''),
        role: String(data.role || '').trim().toLowerCase(),
    });

    if (isEditMode.value) {
        updateOwnerUser(
            userForm.transform(payload),
            userUpdatePath(editingUserId.value),
            {
                onSuccess: closeUserModal,
            },
        );

        return;
    }

    storeOwnerUser(
        userForm.transform(payload),
        userStorePath.value,
        {
            onSuccess: closeUserModal,
        },
    );
};

const deleteUser = (managedUser) => {
    if (!canManageUsers.value) {
        return;
    }

    const userId = String(managedUser?.id || '').trim();
    if (userId === '') {
        return;
    }

    if (!window.confirm('Hapus user ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    destroyOwnerUser(userDeletePath(userId), {
        onStart: () => {
            deletingUserId.value = userId;
        },
        onFinish: () => {
            deletingUserId.value = null;
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
    isUserModalOpen,
    (isOpen) => {
        setPageScrollLock(Boolean(isOpen));
    },
    { flush: 'post' },
);

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isUserModalOpen.value) {
        closeUserModal();
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
    <Head title="Tim Owner" />

    <AppDashboardLayout
        title="Tim"
        subtitle="Kelola data Admin dan Mekanik tenant Anda"
        role-label="Owner"
        :home-href="dashboardPath"
        :user="user"
        :menu-items="menuItems"
        @logout="logout"
    >
        <div ref="pageContentRef" class="space-y-5">
            <OwnerUserTableCard
                :users="users"
                :filters="tableFilters"
                :user-summary="userSummary"
                :flash-status="flashStatus"
                :error-message="userError"
                :table-loading="tableLoading"
                :form-processing="userForm.processing"
                :deleting-user-id="deletingUserId"
                :can-manage="canManageUsers"
                @create="openCreateUserModal"
                @edit="startEditUser"
                @delete="deleteUser"
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
                v-if="isUserModalOpen"
                class="modal-overlay-scroll-dark fixed inset-0 z-50 flex items-start justify-center overflow-x-hidden overflow-y-auto bg-slate-900/45 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 cursor-pointer"
                    aria-label="Tutup modal"
                    @click="closeUserModal"
                />

                <div class="relative z-20 w-full max-w-xl">
                    <OwnerUserFormCard
                        :is-edit-mode="isEditMode"
                        :form="userForm"
                        :errors="pageErrors"
                        :workshop-options="workshopOptions"
                        :is-workshop-selectable="isGlobalWorkshopFilter"
                        @close="closeUserModal"
                        @submit="submitUserForm"
                    />
                </div>
            </div>
        </Transition>
    </AppDashboardLayout>
</template>

