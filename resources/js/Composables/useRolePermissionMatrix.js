import { ref, watch } from 'vue';

const resolveRoleKey = (role) => String(role?.key || role?.name || role?.id || '');

const normalizePermissionIds = (permissionIds, allowedPermissionIds = null) => {
    const normalized = Array.isArray(permissionIds)
        ? permissionIds
            .map((permissionId) => Number(permissionId) || 0)
            .filter((permissionId) => permissionId > 0)
        : [];

    const scoped = Array.isArray(allowedPermissionIds)
        ? normalized.filter((permissionId) => allowedPermissionIds.includes(permissionId))
        : normalized;

    return scoped
        .filter((permissionId, index, list) => list.indexOf(permissionId) === index)
        .sort((a, b) => a - b);
};

export const useRolePermissionMatrix = (rolesSource, form, options = {}) => {
    const lastServerSnapshot = ref('');

    const resolveAllowedPermissionIds = () => Array.isArray(options?.allowedPermissionIds?.value)
        ? options.allowedPermissionIds.value
        : null;

    const buildServerSnapshot = (roles) => JSON.stringify(
        (roles || []).map((role) => ({
            key: resolveRoleKey(role),
            permission_ids: normalizePermissionIds(role?.permission_ids, resolveAllowedPermissionIds()),
        })),
    );

    const initializeRolePermissions = (roles) => {
        const allowedPermissionIds = resolveAllowedPermissionIds();
        const nextMap = {};

        for (const role of roles || []) {
            const roleKey = resolveRoleKey(role);
            if (roleKey === '') {
                continue;
            }

            nextMap[roleKey] = normalizePermissionIds(role?.permission_ids, allowedPermissionIds);
        }

        form.role_permissions = nextMap;
    };

    watch(
        rolesSource,
        (roles) => {
            const snapshot = buildServerSnapshot(roles);
            if (snapshot === lastServerSnapshot.value) {
                return;
            }

            lastServerSnapshot.value = snapshot;
            initializeRolePermissions(roles);
        },
        {
            immediate: true,
            deep: false,
        },
    );

    const hasRolePermission = (roleKey, permissionId) => {
        const normalizedRoleKey = String(roleKey);
        const assignedPermissions = form.role_permissions?.[normalizedRoleKey] || [];
        if (!Array.isArray(assignedPermissions)) {
            return false;
        }

        return assignedPermissions.includes(Number(permissionId));
    };

    const toggleRolePermission = (roleKey, permissionId, checked) => {
        const normalizedRoleKey = String(roleKey);
        const normalizedPermissionId = Number(permissionId);
        const current = Array.isArray(form.role_permissions?.[normalizedRoleKey])
            ? [...form.role_permissions[normalizedRoleKey]]
            : [];

        const next = checked
            ? [...current, normalizedPermissionId]
            : current.filter((assignedId) => assignedId !== normalizedPermissionId);

        form.role_permissions = {
            ...form.role_permissions,
            [normalizedRoleKey]: normalizePermissionIds(next),
        };
    };

    const selectedPermissionCount = (roleKey) => {
        const normalizedRoleKey = String(roleKey);
        const assignedPermissions = form.role_permissions?.[normalizedRoleKey] || [];
        return Array.isArray(assignedPermissions) ? assignedPermissions.length : 0;
    };

    return {
        resolveRoleKey,
        hasRolePermission,
        toggleRolePermission,
        selectedPermissionCount,
    };
};
