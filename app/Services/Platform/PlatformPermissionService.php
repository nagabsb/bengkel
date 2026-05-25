<?php

namespace App\Services\Platform;

use App\Models\MenuPermission;
use App\Models\ModelHasRole;
use App\Models\RoleHasPermission;
use App\Models\Tenant;
use App\Services\Menu\SystemMenuPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PlatformPermissionService
{
    public function __construct(
        private readonly SystemMenuPermissionService $systemMenuPermissionService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(Request $request): array
    {
        $hasRolePermissionTable = Schema::hasTable('role_has_permissions');
        $hasModelHasRolesTable = Schema::hasTable('model_has_roles');
        $hasPermissionsTable = Schema::hasTable('permissions');
        $hasMenusTable = Schema::hasTable('menus');
        $hasMenuPermissionTable = Schema::hasTable('menu_permission');
        $allowedTemplateRoleNames = ['superadmin', 'owner'];

        $permissionSearch = trim((string) $request->query('permission_search', ''));
        $sortablePermissionColumns = ['menu_order', 'name', 'guard_name'];
        $permissionSortBy = (string) $request->query('permission_sort_by', 'menu_order');
        if (! in_array($permissionSortBy, $sortablePermissionColumns, true)) {
            $permissionSortBy = 'menu_order';
        }

        $permissionSortDir = strtolower((string) $request->query('permission_sort_dir', 'asc')) === 'desc'
            ? 'desc'
            : 'asc';

        $allowedPerPage = [10, 20, 50];
        $permissionPerPage = (int) $request->query('permission_per_page', 10);
        if (! in_array($permissionPerPage, $allowedPerPage, true)) {
            $permissionPerPage = 10;
        }
        $permissionCursor = trim((string) $request->query('permission_cursor', ''));

        $this->systemMenuPermissionService->syncCatalog();

        // Superadmin permission matrix must stay in sync with Menu Management mapping.
        $visiblePermissionIds = collect(
            $this->systemMenuPermissionService->resolveSystemMenuPermissionIds(menuIds: null, onlyActiveMenus: false),
        )
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->unique()
            ->values()
            ->all();

        $hasMenuOrdering = $hasMenuPermissionTable && $hasMenusTable;
        // Source data must come from permissions table; menu mapping is only for ordering/labeling.
        $usePermissionScope = false;

        $permissionMenuLabelMap = [];
        if ($hasMenuOrdering) {
            $menuPermissionRows = MenuPermission::query()
                ->join('menus', 'menus.id', '=', 'menu_permission.menu_id')
                ->whereNull('menus.tenant_id')
                ->where('menus.menu_type', 'system')
                ->when($usePermissionScope, function ($query) use ($visiblePermissionIds) {
                    $query->whereIn('menu_permission.permission_id', $visiblePermissionIds);
                })
                ->orderBy('menus.sort_order')
                ->orderBy('menus.id')
                ->get([
                    'menu_permission.permission_id as permission_id',
                    'menus.label as menu_label',
                ]);

            $permissionMenuLabelMap = $menuPermissionRows
                ->groupBy('permission_id')
                ->map(fn (Collection $rows): string => (string) data_get($rows->first(), 'menu_label', ''))
                ->all();
        }

        $roles = collect();
        if (Schema::hasTable('roles')) {
            $roleRows = Role::query()
                ->whereIn('name', $allowedTemplateRoleNames)
                ->orderByRaw("FIELD(name, 'superadmin', 'owner', 'admin', 'kasir', 'mekanik')")
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'tenant_id', 'name', 'guard_name']);

            $roles = $roleRows
                ->groupBy('name')
                ->map(function (Collection $rows, string $roleName) use (
                    $hasModelHasRolesTable,
                    $hasRolePermissionTable,
                    $usePermissionScope,
                    $visiblePermissionIds,
                ): array {
                    $roleIds = $rows
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all();

                    $templateRole = $rows->first(fn (Role $role): bool => $role->tenant_id === null)
                        ?? $rows->first();

                    $templatePermissionIds = $hasRolePermissionTable
                        ? RoleHasPermission::query()
                            ->where('role_id', (int) $templateRole?->id)
                            ->pluck('permission_id')
                            ->map(fn ($permissionId): int => (int) $permissionId)
                            ->values()
                            ->all()
                        : [];

                    if ($usePermissionScope) {
                        $templatePermissionIds = collect($templatePermissionIds)
                            ->filter(fn (int $permissionId): bool => in_array($permissionId, $visiblePermissionIds, true))
                            ->values()
                            ->all();
                    }

                    $memberCount = $hasModelHasRolesTable && count($roleIds) > 0
                        ? (int) ModelHasRole::query()
                            ->whereIn('role_id', $roleIds)
                            ->distinct()
                            ->count('model_id')
                        : 0;

                    $tenantCount = $rows
                        ->pluck('tenant_id')
                        ->filter(fn ($tenantId): bool => is_string($tenantId) && $tenantId !== '')
                        ->unique()
                        ->count();

                    return [
                        'key' => $roleName,
                        'name' => $roleName,
                        'guard_name' => (string) ($templateRole?->guard_name ?? 'web'),
                        'member_count' => $memberCount,
                        'permission_count' => count($templatePermissionIds),
                        'permission_ids' => $templatePermissionIds,
                        'tenant_count' => $tenantCount,
                        'scope_label' => 'Role Template',
                    ];
                })
                ->values();
        }

        $permissionScopeNames = [];
        $permissionPayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $permissionPerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];
        if ($hasPermissionsTable) {
            $permissionQuery = Permission::query()
                ->select(['permissions.id', 'permissions.name', 'permissions.guard_name'])
                ->when($usePermissionScope, function ($query) use ($visiblePermissionIds) {
                    $query->whereIn('permissions.id', $visiblePermissionIds);
                })
                ->when($permissionSearch !== '', function ($query) use ($permissionSearch) {
                    $query->where(function ($nestedQuery) use ($permissionSearch) {
                        $nestedQuery
                            ->where('permissions.name', 'like', "%{$permissionSearch}%")
                            ->orWhere('permissions.guard_name', 'like', "%{$permissionSearch}%");
                    });
                });

            if ($hasMenuOrdering) {
                $permissionMenuOrderSubQuery = MenuPermission::query()
                    ->join('menus', 'menus.id', '=', 'menu_permission.menu_id')
                    ->whereNull('menus.tenant_id')
                    ->where('menus.menu_type', 'system')
                    ->selectRaw('menu_permission.permission_id as permission_id, MIN(menus.sort_order) as menu_sort_order, MIN(menus.id) as menu_sort_id')
                    ->groupBy('menu_permission.permission_id');

                $permissionQuery->leftJoinSub($permissionMenuOrderSubQuery, 'permission_menu_order', function ($join) {
                    $join->on('permission_menu_order.permission_id', '=', 'permissions.id');
                });
            }

            $permissionQuery->selectRaw('permissions.id as permission_id_cursor');

            $isMenuOrderSort = $permissionSortBy === 'menu_order' && $hasMenuOrdering;
            if ($isMenuOrderSort) {
                $permissionQuery
                    ->selectRaw('COALESCE(permission_menu_order.menu_sort_order, 2147483647) as menu_sort_order_cursor')
                    ->selectRaw('COALESCE(permission_menu_order.menu_sort_id, 2147483647) as menu_sort_id_cursor')
                    ->orderBy('menu_sort_order_cursor', $permissionSortDir)
                    ->orderBy('menu_sort_id_cursor', $permissionSortDir)
                    ->orderBy('permissions.name', $permissionSortDir)
                    ->orderBy('permission_id_cursor', $permissionSortDir);
            } else {
                $sortableColumn = [
                    'name' => 'permissions.name',
                    'guard_name' => 'permissions.guard_name',
                ][$permissionSortBy] ?? 'permissions.name';

                $permissionQuery
                    ->orderBy($sortableColumn, $permissionSortDir)
                    ->orderBy('permission_id_cursor', $permissionSortDir);
            }

            $permissionScopeNames = Permission::query()
                ->select(['permissions.id', 'permissions.name', 'permissions.guard_name'])
                ->when($usePermissionScope, function ($query) use ($visiblePermissionIds) {
                    $query->whereIn('id', $visiblePermissionIds);
                })
                ->pluck('name')
                ->map(fn ($permissionName): string => (string) $permissionName)
                ->values()
                ->all();

            $permissionPaginator = $this->cursorPaginateWithFallback(
                $permissionQuery,
                $permissionPerPage,
                ['*'],
                $permissionCursor,
            );

            $roleCountMap = collect();
            if ($hasRolePermissionTable) {
                $pagePermissionIds = collect($permissionPaginator->items())
                    ->pluck('id')
                    ->map(fn ($permissionId): int => (int) $permissionId)
                    ->values()
                    ->all();

                if (count($pagePermissionIds) > 0) {
                    $roleCountMap = RoleHasPermission::query()
                        ->select('permission_id')
                        ->selectRaw('COUNT(DISTINCT role_id) as total')
                        ->whereIn('permission_id', $pagePermissionIds)
                        ->groupBy('permission_id')
                        ->pluck('total', 'permission_id');
                }
            }

            $permissionRows = collect($permissionPaginator->items())->map(function ($permission) use ($roleCountMap, $permissionMenuLabelMap): array {
                    $permissionId = (int) data_get($permission, 'id');
                    $permissionName = (string) data_get($permission, 'name');

                    $menuLabel = trim((string) ($permissionMenuLabelMap[$permissionId] ?? ''));
                    if ($menuLabel === '') {
                        if (Str::startsWith($permissionName, 'platform.tenants.')) {
                            $menuLabel = 'Tenant';
                        } elseif (Str::startsWith($permissionName, 'platform.billing.')) {
                            $menuLabel = 'Billing';
                        } elseif (Str::startsWith($permissionName, 'customers.')) {
                            $menuLabel = 'Pelanggan';
                        }
                    }

                    $permissionAction = Str::contains($permissionName, '.')
                        ? Str::afterLast($permissionName, '.')
                        : 'access';

                    return [
                        'id' => $permissionId,
                        'name' => $permissionName,
                        'display_name' => $menuLabel !== '' ? $menuLabel : $permissionName,
                        'action' => $permissionAction,
                        'menu_label' => $menuLabel,
                        'guard_name' => (string) data_get($permission, 'guard_name'),
                        'role_count' => (int) ($roleCountMap->get($permissionId) ?? 0),
                    ];
                })->values();

            $permissionPayload = [
                'mode' => 'cursor',
                'data' => $permissionRows->all(),
                'per_page' => $permissionPaginator->perPage(),
                'total' => count($permissionScopeNames),
                'from' => $permissionRows->isEmpty() ? 0 : 1,
                'to' => $permissionRows->count(),
                'current_cursor' => $permissionPaginator->cursor()?->encode(),
                'next_cursor' => $permissionPaginator->nextCursor()?->encode(),
                'prev_cursor' => $permissionPaginator->previousCursor()?->encode(),
                'has_more_pages' => $permissionPaginator->hasMorePages(),
            ];
        }

        if ($usePermissionScope) {
            $roles = $roles
                ->map(function (array $role) use ($visiblePermissionIds): array {
                    $permissionIds = collect($role['permission_ids'] ?? [])
                        ->map(fn ($permissionId): int => (int) $permissionId)
                        ->filter(fn (int $permissionId): bool => in_array($permissionId, $visiblePermissionIds, true))
                        ->values()
                        ->all();

                    return [
                        ...$role,
                        'permission_ids' => $permissionIds,
                        'permission_count' => count($permissionIds),
                    ];
                })
                ->values();
        }

        $tenantsCount = Schema::hasTable('tenants')
            ? (int) Tenant::query()->count()
            : 0;

        $permissionModuleCount = collect($permissionScopeNames)
            ->map(fn (string $permissionName): string => Str::contains($permissionName, '.')
                ? Str::before($permissionName, '.')
                : 'general')
            ->unique()
            ->count();

        return [
            'roles' => $roles,
            'permissions' => $permissionPayload,
            'permissionFilters' => [
                'search' => $permissionSearch,
                'sort_by' => $permissionSortBy,
                'sort_dir' => $permissionSortDir,
                'per_page' => $permissionPerPage,
                'cursor' => $permissionPayload['current_cursor'],
            ],
            'permissionScopeTotal' => count($permissionScopeNames),
            'permissionModuleCount' => $permissionModuleCount,
            'tenantsCount' => $tenantsCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $rolePermissions
     */
    public function syncTemplateRolePermissions(array $rolePermissions): void
    {
        $allowedTemplateRoleNames = ['superadmin', 'owner'];
        $payload = collect($rolePermissions)
            ->mapWithKeys(function (array $permissionIds, string|int $roleName): array {
                $normalizedRoleName = trim((string) $roleName);
                if ($normalizedRoleName === '') {
                    return [];
                }

                return [
                    $normalizedRoleName => collect($permissionIds)
                        ->map(fn ($permissionId): int => (int) $permissionId)
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $permissionIds, string $roleName): bool => $roleName !== '');

        if ($payload->isEmpty()) {
            throw ValidationException::withMessages([
                'role_permissions' => 'Tidak ada role yang bisa disinkronkan.',
            ]);
        }

        $roleNames = $payload
            ->keys()
            ->map(fn ($roleName): string => (string) $roleName)
            ->values()
            ->all();

        $unknownRoleNames = collect($roleNames)
            ->reject(fn (string $roleName): bool => in_array($roleName, $allowedTemplateRoleNames, true))
            ->values()
            ->all();

        if (count($unknownRoleNames) > 0) {
            throw ValidationException::withMessages([
                'role_permissions' => 'Superadmin hanya boleh mengatur template role superadmin dan owner.',
            ]);
        }

        $this->systemMenuPermissionService->syncCatalog();
        $allowedPermissionIds = null;
        if (Schema::hasTable('permissions')) {
            $allowedPermissionIds = Permission::query()
                ->select(['permissions.id', 'permissions.name', 'permissions.guard_name'])
                ->pluck('id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->unique()
                ->values()
                ->all();

            if (count($allowedPermissionIds) === 0) {
                throw ValidationException::withMessages([
                    'role_permissions' => 'Belum ada data permission.',
                ]);
            }
        }

        if (is_array($allowedPermissionIds) && count($allowedPermissionIds) > 0) {
            $outOfScopePermissionIds = $payload
                ->flatMap(fn (array $permissionIds) => $permissionIds)
                ->filter(fn (int $permissionId): bool => ! in_array($permissionId, $allowedPermissionIds, true))
                ->unique()
                ->values()
                ->all();

            if (count($outOfScopePermissionIds) > 0) {
                throw ValidationException::withMessages([
                    'role_permissions' => 'Ada permission yang tidak valid.',
                ]);
            }
        }

        $rolesByName = Role::query()
            ->whereIn('name', $roleNames)
            ->get()
            ->groupBy(fn (Role $role): string => (string) $role->name);

        if ($rolesByName->count() !== count($roleNames)) {
            throw ValidationException::withMessages([
                'role_permissions' => 'Sebagian role tidak valid.',
            ]);
        }

        DB::transaction(function () use ($payload, $rolesByName): void {
            foreach ($payload as $roleName => $permissionIds) {
                /** @var Collection<int, Role> $roleGroup */
                $roleGroup = $rolesByName->get((string) $roleName, collect());
                foreach ($roleGroup as $role) {
                    $role->syncPermissions($permissionIds);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function cursorPaginateWithFallback(
        \Illuminate\Database\Eloquent\Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
    ): \Illuminate\Contracts\Pagination\CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'permission_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'permission_cursor_fallback', null)
                ->withQueryString();
        }
    }
}










