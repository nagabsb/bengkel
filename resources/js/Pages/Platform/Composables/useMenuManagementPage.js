import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { menuIconOptions } from '../../../Utils/menuIcons';

export const useMenuManagementPage = (props) => {
    const page = usePage();
    const logoutForm = useForm({});
    const createMenuForm = useForm({
        parent_id: null,
        label: '',
        route: '',
        icon: 'dashboard',
        sort_order: 100,
        is_active: true,
    });
    const deleteMenuForm = useForm({});
    const reorderMenuForm = useForm({
        parent_id: null,
        source_id: null,
        target_id: null,
    });
    const toggleMenuStatusForm = useForm({
        is_active: false,
    });
    const editingMenuId = ref(null);
    const draggedMenuId = ref(null);
    const dragOverMenuId = ref(null);
    const togglingMenuId = ref(null);

    const dashboardPath = '/platform/dashboard';
    const tenantsPath = '/platform/tenants';
    const permissionsPath = '/platform/settings/permissions';
    const menusPath = '/platform/settings/menus';
    const plansPath = '/platform/settings/plans';
    const applicationPath = '/platform/settings/application';
    const paymentsPath = '/platform/settings/payments';
    const vehicleMastersPath = '/platform/settings/vehicle-masters';
    const aiAgentPath = '/platform/settings/ai-agent';
    const storePath = '/platform/settings/menus';
    const reorderPath = '/platform/settings/menus/reorder';
    const statusPath = (menuId) => `/platform/settings/menus/${menuId}/status`;
    const updatePath = (menuId) => `/platform/settings/menus/${menuId}`;
    const destroyPath = (menuId) => `/platform/settings/menus/${menuId}`;
    const flashStatus = computed(() => String(page.props?.flash?.status || ''));
    const pageErrors = computed(() => page.props?.errors || {});
    const isEditMode = computed(() => Number(editingMenuId.value) > 0);

    const currentPath = computed(() => String(page.url || '').split('?')[0] || '');

    const activeSettingsKey = computed(() => {
        const path = currentPath.value;
        if (path === permissionsPath) return 'permissions';
        if (path === menusPath) return 'menus';
        if (path === plansPath) return 'plans';
        if (path === applicationPath) return 'application';
        if (path === paymentsPath) return 'payments';
        if (path === vehicleMastersPath) return 'vehicle-masters';
        if (path === aiAgentPath) return 'ai-agent';
        return '';
    });

    const isSettingsActive = computed(() => ['permissions', 'menus', 'application', 'payments', 'vehicle-masters', 'ai-agent'].includes(activeSettingsKey.value));

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

    const iconOptions = menuIconOptions;

    const flattenMenus = (items, depth = 0) => {
        if (!Array.isArray(items)) return [];

        const rows = [];
        for (const item of items) {
            rows.push({
                id: Number(item?.id) || 0,
                parent_id: item?.parent_id === null || item?.parent_id === undefined ? null : Number(item.parent_id) || null,
                label: String(item?.label || '-'),
                route: item?.route ? String(item.route) : null,
                icon: String(item?.icon || 'dashboard'),
                menu_type: String(item?.menu_type || 'system'),
                sort_order: Number(item?.sort_order) || 0,
                is_active: Boolean(item?.is_active),
                depth,
            });

            if (Array.isArray(item?.children) && item.children.length > 0) {
                rows.push(...flattenMenus(item.children, depth + 1));
            }
        }

        return rows;
    };

    const flatMenus = ref([]);

    watch(
        () => props.menus,
        (menus) => {
            flatMenus.value = flattenMenus(menus);
        },
        {
            immediate: true,
            deep: true,
        },
    );

    const normalizeParentId = (parentId) => {
        if (parentId === null || parentId === undefined || parentId === '') {
            return null;
        }

        const normalized = Number(parentId);
        return Number.isFinite(normalized) ? normalized : null;
    };

    const sameParent = (leftParentId, rightParentId) => normalizeParentId(leftParentId) === normalizeParentId(rightParentId);

    const findFlatMenuById = (menuId) => flatMenus.value.find((menu) => Number(menu.id) === Number(menuId)) || null;

    const collectFlatDescendantIds = (menuId) => {
        const rootId = Number(menuId) || 0;
        if (rootId <= 0) return [];

        const descendants = [];
        const queue = [rootId];
        while (queue.length > 0) {
            const currentParentId = queue.shift();
            const children = flatMenus.value.filter((menu) => Number(menu.parent_id) === Number(currentParentId));

            children.forEach((child) => {
                const childId = Number(child.id) || 0;
                if (childId <= 0) return;
                descendants.push(childId);
                queue.push(childId);
            });
        }

        return descendants;
    };

    const canReorderPair = (sourceMenu, targetMenu) => {
        if (!sourceMenu || !targetMenu) return false;
        if (Number(sourceMenu.id) === Number(targetMenu.id)) return false;
        return sameParent(sourceMenu.parent_id, targetMenu.parent_id);
    };

    const resetDragState = () => {
        draggedMenuId.value = null;
        dragOverMenuId.value = null;
    };

    const handleRowDragStart = (menu, event) => {
        if (reorderMenuForm.processing) {
            event.preventDefault();
            return;
        }

        const menuId = Number(menu?.id) || 0;
        if (menuId <= 0) {
            event.preventDefault();
            return;
        }

        draggedMenuId.value = menuId;

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(menuId));
        }
    };

    const handleRowDragOver = (menu, event) => {
        const sourceMenu = findFlatMenuById(draggedMenuId.value);
        if (!canReorderPair(sourceMenu, menu)) {
            return;
        }

        event.preventDefault();
        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }
        dragOverMenuId.value = Number(menu.id);
    };

    const persistSortSwap = (sourceMenu, targetMenu, previousRows) => {
        reorderMenuForm
            .transform(() => ({
                parent_id: normalizeParentId(sourceMenu.parent_id),
                source_id: Number(sourceMenu.id),
                target_id: Number(targetMenu.id),
            }))
            .post(reorderPath, {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    flatMenus.value = previousRows;
                },
                onFinish: () => {
                    resetDragState();
                },
            });
    };

    const handleRowDrop = (menu, event) => {
        event.preventDefault();

        const sourceMenu = findFlatMenuById(draggedMenuId.value);
        const targetMenu = findFlatMenuById(menu?.id);
        if (!canReorderPair(sourceMenu, targetMenu)) {
            resetDragState();
            return;
        }

        const currentRows = [...flatMenus.value];
        const sourceIndex = currentRows.findIndex((row) => Number(row.id) === Number(sourceMenu.id));
        const targetIndex = currentRows.findIndex((row) => Number(row.id) === Number(targetMenu.id));

        if (sourceIndex < 0 || targetIndex < 0 || sourceIndex === targetIndex) {
            resetDragState();
            return;
        }

        const previousRows = [...currentRows];
        const sourceRow = { ...currentRows[sourceIndex] };
        const targetRow = { ...currentRows[targetIndex] };

        currentRows[sourceIndex] = {
            ...targetRow,
            sort_order: sourceRow.sort_order,
        };
        currentRows[targetIndex] = {
            ...sourceRow,
            sort_order: targetRow.sort_order,
        };

        flatMenus.value = currentRows;
        persistSortSwap(sourceMenu, targetMenu, previousRows);
    };

    const handleRowDragEnd = () => {
        resetDragState();
    };

    const findMenuNodeById = (items, targetId) => {
        if (!Array.isArray(items) || targetId <= 0) return null;

        for (const item of items) {
            const id = Number(item?.id) || 0;
            if (id === targetId) {
                return item;
            }

            if (Array.isArray(item?.children) && item.children.length > 0) {
                const nested = findMenuNodeById(item.children, targetId);
                if (nested) return nested;
            }
        }

        return null;
    };

    const collectDescendantIds = (node) => {
        const ids = new Set();
        if (!node || !Array.isArray(node.children)) return ids;

        const stack = [...node.children];
        while (stack.length > 0) {
            const current = stack.pop();
            const currentId = Number(current?.id) || 0;
            if (currentId > 0) {
                ids.add(currentId);
            }

            if (Array.isArray(current?.children) && current.children.length > 0) {
                stack.push(...current.children);
            }
        }

        return ids;
    };

    const disallowedParentIds = computed(() => {
        const menuId = Number(editingMenuId.value) || 0;
        if (menuId <= 0) return new Set();

        const disallowedIds = new Set([menuId]);
        const menuNode = findMenuNodeById(props.menus, menuId);
        const descendants = collectDescendantIds(menuNode);
        descendants.forEach((id) => disallowedIds.add(id));

        return disallowedIds;
    });

    const depthPaddingClass = (depth) => {
        if (depth <= 0) return 'pl-0';
        if (depth === 1) return 'pl-5';
        if (depth === 2) return 'pl-10';
        return 'pl-14';
    };

    const parentOptions = computed(() => flatMenus.value
        .filter((menu) => !disallowedParentIds.value.has(menu.id))
        .map((menu) => ({
            value: menu.id,
            label: `${menu.depth > 0 ? `${'-- '.repeat(Math.min(menu.depth, 3))} ` : ''}${menu.label}`,
        })));

    const resetCreateMenuForm = () => {
        editingMenuId.value = null;
        createMenuForm.clearErrors();
        createMenuForm.reset('label', 'route');
        createMenuForm.parent_id = null;
        createMenuForm.icon = 'dashboard';
        createMenuForm.sort_order = 100;
        createMenuForm.is_active = true;
    };

    const startEditMenu = (menu) => {
        const menuId = Number(menu?.id) || 0;
        if (menuId <= 0) return;

        editingMenuId.value = menuId;
        createMenuForm.clearErrors();
        createMenuForm.parent_id = menu.parent_id === null ? null : Number(menu.parent_id) || null;
        createMenuForm.label = String(menu?.label || '');
        createMenuForm.route = menu?.route ? String(menu.route) : '';
        createMenuForm.icon = String(menu?.icon || 'dashboard');
        createMenuForm.sort_order = Number(menu?.sort_order) || 0;
        createMenuForm.is_active = Boolean(menu?.is_active);
    };

    const submitCreateMenu = () => {
        createMenuForm
            .transform((data) => ({
                ...data,
                parent_id: data.parent_id === null || data.parent_id === '' ? null : Number(data.parent_id),
                icon: String(data.icon || 'dashboard'),
                sort_order: Number(data.sort_order || 0),
                is_active: Boolean(data.is_active),
            }))
            .post(storePath, {
                preserveScroll: true,
                onSuccess: resetCreateMenuForm,
            });
    };

    const submitUpdateMenu = () => {
        const menuId = Number(editingMenuId.value) || 0;
        if (menuId <= 0) return;

        createMenuForm
            .transform((data) => ({
                ...data,
                parent_id: data.parent_id === null || data.parent_id === '' ? null : Number(data.parent_id),
                icon: String(data.icon || 'dashboard'),
                sort_order: Number(data.sort_order || 0),
                is_active: Boolean(data.is_active),
            }))
            .patch(updatePath(menuId), {
                preserveScroll: true,
                onSuccess: resetCreateMenuForm,
            });
    };

    const submitMenuForm = () => {
        if (isEditMode.value) {
            submitUpdateMenu();
            return;
        }

        submitCreateMenu();
    };

    const deleteMenu = (menu) => {
        const menuId = Number(menu?.id) || 0;
        if (menuId <= 0 || deleteMenuForm.processing) return;

        if (typeof window !== 'undefined') {
            const accepted = window.confirm(`Hapus menu "${String(menu?.label || '-')}?"`);
            if (!accepted) return;
        }

        deleteMenuForm.delete(destroyPath(menuId), {
            preserveScroll: true,
            onSuccess: () => {
                if (Number(editingMenuId.value) === menuId) {
                    resetCreateMenuForm();
                }
            },
        });
    };

    const toggleMenuStatus = (menu) => {
        const menuId = Number(menu?.id) || 0;
        if (menuId <= 0 || toggleMenuStatusForm.processing) return;

        const previousState = Boolean(menu?.is_active);
        const nextState = !previousState;
        const previousStateMap = new Map([[menuId, previousState]]);

        menu.is_active = nextState;

        if (!nextState) {
            const descendantIds = collectFlatDescendantIds(menuId);
            descendantIds.forEach((descendantId) => {
                const descendantMenu = findFlatMenuById(descendantId);
                if (!descendantMenu) return;

                previousStateMap.set(descendantId, Boolean(descendantMenu.is_active));
                descendantMenu.is_active = false;
            });
        }

        togglingMenuId.value = menuId;
        toggleMenuStatusForm.clearErrors();

        toggleMenuStatusForm
            .transform(() => ({
                is_active: nextState,
            }))
            .patch(statusPath(menuId), {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    previousStateMap.forEach((state, id) => {
                        const targetMenu = findFlatMenuById(id);
                        if (!targetMenu) return;
                        targetMenu.is_active = state;
                    });
                },
                onFinish: () => {
                    togglingMenuId.value = null;
                },
            });
    };

    const logout = () => logoutForm.post('/logout');

    return {
        dashboardPath,
        flashStatus,
        pageErrors,
        menuItems,
        iconOptions,
        createMenuForm,
        deleteMenuForm,
        reorderMenuForm,
        toggleMenuStatusForm,
        isEditMode,
        flatMenus,
        draggedMenuId,
        dragOverMenuId,
        togglingMenuId,
        depthPaddingClass,
        handleRowDragStart,
        handleRowDragOver,
        handleRowDrop,
        handleRowDragEnd,
        startEditMenu,
        deleteMenu,
        toggleMenuStatus,
        parentOptions,
        resetCreateMenuForm,
        submitMenuForm,
        logout,
    };
};




