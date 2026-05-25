<?php

namespace App\Services\Owner;

use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\ModelHasRole;
use App\Models\PlanMenu;
use App\Models\RoleHasPermission;
use App\Models\WorkshopMenuOverride;
use App\Services\Menu\SystemMenuPermissionService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class OwnerSettingsService
{
    /**
     * Some finance routes are intentionally hidden from owner sidebar for now.
     *
     * @var array<int, string>
     */
    private const HIDDEN_OWNER_MENU_ROUTES = [
        'owner.invoices.index',
        'owner.invoice-payments.index',
        'owner.receivables.index',
    ];

    public function __construct(
        private readonly SystemMenuPermissionService $systemMenuPermissionService,
        private readonly OwnerMenuService $ownerMenuService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(
        Request $request,
        string $tenantId,
        TenantPlanResolver $planResolver,
    ): array {
        $activeTab = $this->resolveActiveTab((string) $request->query('tab', 'permissions'));
        $permissionSearch = trim((string) $request->query('permission_search', ''));
        $permissionSortBy = $this->resolvePermissionSortBy((string) $request->query('permission_sort_by', 'menu_label'));
        $permissionSortDir = $this->resolvePermissionSortDirection((string) $request->query('permission_sort_dir', 'asc'));
        $permissionPerPage = $this->resolvePermissionPerPage((int) $request->query('permission_per_page', 50));
        $permissionCursor = trim((string) $request->query('permission_cursor', ''));

        $package = $planResolver->forTenantId($tenantId);
        $planId = data_get($package, 'plan.id');

        $hasRolePermissionTable = Schema::hasTable('role_has_permissions');
        $hasModelHasRolesTable = Schema::hasTable('model_has_roles');
        $hasPermissionsTable = Schema::hasTable('permissions');
        $hasMenusTable = Schema::hasTable('menus');
        $hasPlanMenuTable = Schema::hasTable('plan_menu');
        $hasMenuPermissionTable = Schema::hasTable('menu_permission');

        $this->systemMenuPermissionService->syncCatalog();

        $ownerPermissionScopeIds = $this->resolveOwnerPermissionScopeIds(
            $tenantId,
            $planId,
            $hasRolePermissionTable,
            $hasMenusTable,
            $hasPlanMenuTable,
            $hasMenuPermissionTable,
        );

        $roles = $this->buildManagedRoleRows(
            $tenantId,
            $ownerPermissionScopeIds,
            $hasRolePermissionTable,
            $hasModelHasRolesTable,
        );

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
        $activeMenuIdsForPermissionScope = [];
        if ($hasMenusTable && $hasMenuPermissionTable) {
            $activeMenuIdsForPermissionScope = $this->resolveTenantActiveSystemMenuIds(
                $tenantId,
                $this->resolvePlanScopedMenuIds($planId, $hasPlanMenuTable),
            );
        }

        if ($hasPermissionsTable) {
            [$permissionScopeNames, $permissionPayload] = $this->buildPermissionPaginator(
                $ownerPermissionScopeIds,
                $permissionSearch,
                $permissionSortBy,
                $permissionSortDir,
                $permissionPerPage,
                $permissionCursor,
                $hasRolePermissionTable,
                $activeMenuIdsForPermissionScope,
            );
        }

        $menuTree = [];
        $menuItems = [];
        if ($hasMenusTable) {
            $menuTree = $this->ownerMenuService->buildOwnerMenuTree(
                $tenantId,
                $planId,
                $hasPlanMenuTable,
            );

            $menuItems = $this->ownerMenuService->buildSidebarMenuItems(
                $menuTree,
                $tenantId,
                $request->user(),
                $this->resolveCurrentUri($request),
            );
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'activeTab' => $activeTab,
            'roles' => $roles,
            'permissions' => $permissionPayload,
            'permissionFilters' => [
                'search' => $permissionSearch,
                'sort_by' => $permissionSortBy,
                'sort_dir' => $permissionSortDir,
                'per_page' => $permissionPerPage,
                'cursor' => $permissionPayload['current_cursor'],
            ],
            'canManagePermissions' => $this->canManagePermissions($request->user()),
            'ownerPermissionCount' => count($ownerPermissionScopeIds),
            'permissionScopeNames' => $permissionScopeNames,
            'menus' => $menuTree,
            'menuItems' => $menuItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $rolePermissions
     */
    public function syncRolePermissions(
        string $tenantId,
        array $rolePermissions,
        TenantPlanResolver $planResolver,
    ): void {
        $package = $planResolver->forTenantId($tenantId);
        $planId = data_get($package, 'plan.id');

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            throw ValidationException::withMessages([
                'role_permissions' => 'Tabel RBAC belum siap.',
            ]);
        }

        $this->systemMenuPermissionService->syncCatalog();

        $hasMenusTable = Schema::hasTable('menus');
        $hasPlanMenuTable = Schema::hasTable('plan_menu');
        $hasMenuPermissionTable = Schema::hasTable('menu_permission');

        $ownerPermissionScopeIds = $this->resolveOwnerPermissionScopeIds(
            $tenantId,
            $planId,
            hasRolePermissionTable: true,
            hasMenusTable: $hasMenusTable,
            hasPlanMenuTable: $hasPlanMenuTable,
            hasMenuPermissionTable: $hasMenuPermissionTable,
        );

        $managedRoleNames = ['admin', 'kasir', 'mekanik'];
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

        $unknownRoleNames = $payload
            ->keys()
            ->reject(fn (string $roleName): bool => in_array($roleName, $managedRoleNames, true))
            ->values()
            ->all();

        if (count($unknownRoleNames) > 0) {
            throw ValidationException::withMessages([
                'role_permissions' => 'Owner hanya bisa mengatur role admin, kasir, dan mekanik.',
            ]);
        }

        if ($hasMenuPermissionTable && count($ownerPermissionScopeIds) === 0) {
            throw ValidationException::withMessages([
                'role_permissions' => 'Belum ada mapping permission dari menu tenant.',
            ]);
        }

        $outOfScopePermissionIds = $payload
            ->flatMap(fn (array $permissionIds) => $permissionIds)
            ->filter(fn (int $permissionId): bool => ! in_array($permissionId, $ownerPermissionScopeIds, true))
            ->unique()
            ->values()
            ->all();

        if (count($outOfScopePermissionIds) > 0) {
            throw ValidationException::withMessages([
                'role_permissions' => 'Ada permission di luar scope owner/menu aktif tenant.',
            ]);
        }

        $rolesByName = Role::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('name', $managedRoleNames)
            ->get()
            ->keyBy(fn (Role $role): string => (string) $role->name);

        $missingRoles = $payload
            ->keys()
            ->reject(fn (string $roleName): bool => $rolesByName->has($roleName))
            ->values()
            ->all();

        if (count($missingRoles) > 0) {
            throw ValidationException::withMessages([
                'role_permissions' => 'Sebagian role tenant tidak ditemukan.',
            ]);
        }

        DB::transaction(function () use ($payload, $rolesByName): void {
            foreach ($payload as $roleName => $permissionIds) {
                /** @var Role $role */
                $role = $rolesByName->get((string) $roleName);
                $role->syncPermissions($permissionIds);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<int, int>
     */
    private function resolveOwnerPermissionScopeIds(
        string $tenantId,
        mixed $planId,
        bool $hasRolePermissionTable,
        bool $hasMenusTable,
        bool $hasPlanMenuTable,
        bool $hasMenuPermissionTable,
    ): array {
        $ownerScope = collect();
        if (Schema::hasTable('roles') && $hasRolePermissionTable) {
            $ownerRoleId = Role::query()
                ->where('tenant_id', $tenantId)
                ->where('name', 'owner')
                ->value('id');

            if (is_numeric($ownerRoleId)) {
                $ownerAllowedPermissionIds = RoleHasPermission::query()
                    ->where('role_id', (int) $ownerRoleId)
                    ->pluck('permission_id')
                    ->map(fn ($permissionId): int => (int) $permissionId)
                    ->values()
                    ->all();

                $ownerScope = collect($ownerAllowedPermissionIds)
                    ->map(fn ($permissionId): int => (int) $permissionId)
                    ->unique()
                    ->values();
            }
        }

        if (! $hasMenuPermissionTable || ! $hasMenusTable) {
            return $ownerScope->values()->all();
        }

        // Tenant scope should follow active menu + plan + tenant override visibility.
        $tenantMenuScopedPermissionIds = $this->resolveTenantMenuScopedPermissionIds(
            $tenantId,
            $planId,
            $hasPlanMenuTable,
        );

        if (count($tenantMenuScopedPermissionIds) > 0) {
            return $tenantMenuScopedPermissionIds;
        }

        return $ownerScope->values()->all();
    }

    /**
     * @param  array<int, int>  $ownerPermissionScopeIds
     * @return Collection<int, array<string, mixed>>
     */
    private function buildManagedRoleRows(
        string $tenantId,
        array $ownerPermissionScopeIds,
        bool $hasRolePermissionTable,
        bool $hasModelHasRolesTable,
    ): Collection {
        if (! Schema::hasTable('roles')) {
            return collect();
        }

        $managedRoleNames = ['admin', 'kasir', 'mekanik'];
        $roles = Role::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('name', $managedRoleNames)
            ->orderByRaw("CASE name
                WHEN 'admin' THEN 1
                WHEN 'kasir' THEN 2
                WHEN 'mekanik' THEN 3
                ELSE 99
            END")
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        $roleIds = $roles
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $memberCountMap = collect();
        if ($hasModelHasRolesTable && count($roleIds) > 0) {
            $memberCountMap = ModelHasRole::query()
                ->select('role_id')
                ->selectRaw('COUNT(DISTINCT model_id) as total')
                ->whereIn('role_id', $roleIds)
                ->groupBy('role_id')
                ->pluck('total', 'role_id');
        }

        $permissionMap = collect();
        if ($hasRolePermissionTable && count($roleIds) > 0) {
            $permissionMap = RoleHasPermission::query()
                ->whereIn('role_id', $roleIds)
                ->get(['role_id', 'permission_id'])
                ->groupBy('role_id')
                ->map(function (Collection $rows): array {
                    return $rows
                        ->pluck('permission_id')
                        ->map(fn ($permissionId): int => (int) $permissionId)
                        ->values()
                        ->all();
                });
        }

        return $roles
            ->map(function (Role $role) use ($ownerPermissionScopeIds, $memberCountMap, $permissionMap): array {
                $permissionIds = $permissionMap->get($role->id, []);
                if (count($ownerPermissionScopeIds) > 0) {
                    $permissionIds = collect($permissionIds)
                        ->filter(fn (int $permissionId): bool => in_array($permissionId, $ownerPermissionScopeIds, true))
                        ->values()
                        ->all();
                } else {
                    $permissionIds = [];
                }

                return [
                    'id' => (int) $role->id,
                    'key' => (string) $role->name,
                    'name' => (string) $role->name,
                    'guard_name' => (string) $role->guard_name,
                    'member_count' => (int) ($memberCountMap->get($role->id) ?? 0),
                    'permission_count' => count($permissionIds),
                    'permission_ids' => $permissionIds,
                ];
            })
            ->values();
    }

    /**
     * @param  array<int, int>  $ownerPermissionScopeIds
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private function buildPermissionPaginator(
        array $ownerPermissionScopeIds,
        string $permissionSearch,
        string $permissionSortBy,
        string $permissionSortDir,
        int $permissionPerPage,
        string $permissionCursor,
        bool $hasRolePermissionTable,
        array $activeMenuIds,
    ): array {
        $scopeQuery = Permission::query()
            ->when(count($ownerPermissionScopeIds) > 0, function ($query) use ($ownerPermissionScopeIds) {
                $query->whereIn('id', $ownerPermissionScopeIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            });

        $permissionScopeNames = (clone $scopeQuery)
            ->pluck('name')
            ->map(fn ($permissionName): string => (string) $permissionName)
            ->values()
            ->all();

        $emptyPayload = [
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

        if (
            count($ownerPermissionScopeIds) === 0
            || count($activeMenuIds) === 0
            || ! Schema::hasTable('menu_permission')
            || ! Schema::hasTable('menus')
        ) {
            return [$permissionScopeNames, $emptyPayload];
        }

        $menuPermissionQuery = MenuPermission::query()
            ->join('permissions', 'permissions.id', '=', 'menu_permission.permission_id')
            ->join('menus', 'menus.id', '=', 'menu_permission.menu_id')
            ->leftJoin('menus as parent_menus', 'parent_menus.id', '=', 'menus.parent_id')
            ->whereIn('menu_permission.permission_id', $ownerPermissionScopeIds)
            ->whereIn('menu_permission.menu_id', $activeMenuIds)
            ->whereNull('menus.tenant_id')
            ->where('menus.menu_type', 'system')
            ->where('menus.is_active', true)
            ->when($permissionSearch !== '', function ($query) use ($permissionSearch) {
                $query->where(function ($nestedQuery) use ($permissionSearch) {
                    $nestedQuery
                        ->where('permissions.name', 'like', "%{$permissionSearch}%")
                        ->orWhere('permissions.guard_name', 'like', "%{$permissionSearch}%")
                        ->orWhere('menus.label', 'like', "%{$permissionSearch}%")
                        ->orWhere('parent_menus.label', 'like', "%{$permissionSearch}%");
                });
            });

        if ($permissionSortBy === 'guard_name') {
            $menuPermissionQuery->orderBy('permissions.guard_name', $permissionSortDir);
        } elseif ($permissionSortBy === 'menu_label' || $permissionSortBy === 'submenu_label') {
            $menuPermissionQuery->orderBy('menus.label', $permissionSortDir);
        } else {
            $menuPermissionQuery->orderBy('permissions.name', $permissionSortDir);
        }

        $menuPermissionQuery
            ->orderBy('menus.sort_order')
            ->orderBy('menus.id')
            ->orderBy('menu_permission.permission_id');

        $permissionTotal = (clone $menuPermissionQuery)->count();

        $permissionPaginator = $this->cursorPaginateWithFallback(
            $menuPermissionQuery,
            $permissionPerPage,
            [
                'menu_permission.menu_id as row_menu_id',
                'menu_permission.permission_id as row_permission_id',
                'permissions.id as permission_id',
                'permissions.name as permission_name',
                'permissions.guard_name as permission_guard_name',
                'menus.label as menu_label',
            ],
            $permissionCursor,
        );

        $pagePermissionIds = collect($permissionPaginator->items())
            ->pluck('permission_id')
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->filter(fn (int $permissionId): bool => $permissionId > 0)
            ->unique()
            ->values()
            ->all();

        $roleCountMap = collect();
        if ($hasRolePermissionTable && count($pagePermissionIds) > 0) {
            $roleCountMap = RoleHasPermission::query()
                ->select('permission_id')
                ->selectRaw('COUNT(DISTINCT role_id) as total')
                ->whereIn('permission_id', $pagePermissionIds)
                ->groupBy('permission_id')
                ->pluck('total', 'permission_id');
        }

        $menuPathMap = $this->resolveSystemMenuPathMap(
            collect($permissionPaginator->items())
                ->pluck('row_menu_id')
                ->map(fn ($menuId): int => (int) $menuId)
                ->filter(fn (int $menuId): bool => $menuId > 0)
                ->unique()
                ->values()
                ->all(),
        );

        $permissionRows = collect($permissionPaginator->items())
            ->map(function (object $row) use ($menuPathMap, $roleCountMap): array {
                $menuId = (int) ($row->row_menu_id ?? 0);
                $permissionId = (int) ($row->permission_id ?? $row->row_permission_id ?? 0);
                $permissionName = trim((string) ($row->permission_name ?? ''));
                $menuPath = trim((string) ($menuPathMap->get($menuId) ?? $row->menu_label ?? ''));
                [$menuLabel, $subMenuLabel] = $this->splitMenuPath($menuPath, (string) ($row->menu_label ?? ''));

                return [
                    'row_key' => "menu-{$menuId}-permission-{$permissionId}",
                    'id' => $permissionId,
                    'permission_id' => $permissionId,
                    'name' => $permissionName,
                    'display_name' => $permissionName,
                    'action' => str_contains($permissionName, '.')
                        ? (string) str($permissionName)->afterLast('.')
                        : 'access',
                    'menu_id' => $menuId,
                    'menu_path' => $menuPath !== '' ? $menuPath : null,
                    'menu_label' => $menuLabel !== '' ? $menuLabel : null,
                    'submenu_label' => $subMenuLabel !== '' ? $subMenuLabel : '-',
                    'guard_name' => (string) ($row->permission_guard_name ?? 'web'),
                    'role_count' => (int) ($roleCountMap->get($permissionId) ?? 0),
                ];
            })
            ->filter(fn (array $row): bool => ($row['id'] ?? 0) > 0)
            ->values();

        return [
            $permissionScopeNames,
            [
                'mode' => 'cursor',
                'data' => $permissionRows->all(),
                'per_page' => $permissionPaginator->perPage(),
                'total' => (int) $permissionTotal,
                'from' => $permissionRows->isEmpty() ? 0 : 1,
                'to' => $permissionRows->count(),
                'current_cursor' => $permissionPaginator->cursor()?->encode(),
                'next_cursor' => $permissionPaginator->nextCursor()?->encode(),
                'prev_cursor' => $permissionPaginator->previousCursor()?->encode(),
                'has_more_pages' => $permissionPaginator->hasMorePages(),
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function resolveTenantMenuScopedPermissionIds(
        string $tenantId,
        mixed $planId,
        bool $hasPlanMenuTable,
    ): array {
        $menuScopeIds = $this->resolvePlanScopedMenuIds($planId, $hasPlanMenuTable);

        $tenantActiveMenuIds = $this->resolveTenantActiveSystemMenuIds($tenantId, $menuScopeIds);
        if (count($tenantActiveMenuIds) === 0) {
            return [];
        }

        return $this->systemMenuPermissionService
            ->resolveSystemMenuPermissionIds($tenantActiveMenuIds);
    }

    /**
     * @return array<int, int>
     */
    private function resolvePlanScopedMenuIds(mixed $planId, bool $hasPlanMenuTable): array
    {
        if (! is_numeric($planId) || ! $hasPlanMenuTable) {
            return [];
        }

        return PlanMenu::query()
            ->where('plan_id', (int) $planId)
            ->pluck('menu_id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $menuIds
     * @return array<int, int>
     */
    private function resolveTenantActiveSystemMenuIds(string $tenantId, array $menuIds): array
    {
        $menuTreeReference = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('is_active', true)
            ->when(count($menuIds) > 0, fn ($query) => $query->whereIn('id', $menuIds))
            ->get(['id', 'parent_id', 'route', 'is_active'])
            ->filter(function (Menu $menu): bool {
                $routeTarget = trim((string) ($menu->route ?? ''));
                if ($routeTarget === '') {
                    return true;
                }

                $routeName = trim((string) explode('?', $routeTarget, 2)[0]);

                return ! in_array($routeName, self::HIDDEN_OWNER_MENU_ROUTES, true);
            })
            ->keyBy('id');

        if ($menuTreeReference->isEmpty()) {
            return [];
        }

        $routeMenuIds = $menuTreeReference
            ->filter(function (Menu $menu): bool {
                $routeTarget = trim((string) ($menu->route ?? ''));

                return $routeTarget !== '';
            })
            ->keys()
            ->map(fn (mixed $menuId): int => (int) $menuId)
            ->values()
            ->all();

        if (count($routeMenuIds) === 0) {
            return [];
        }

        $overrideByMenuId = collect();
        if (Schema::hasTable('workshop_menu_overrides')) {
            $overrideByMenuId = WorkshopMenuOverride::query()
                ->where('tenant_id', $tenantId)
                ->get(['menu_id', 'is_visible', 'is_active'])
                ->keyBy('menu_id');
        }

        return collect($routeMenuIds)
            ->filter(fn (int $menuId): bool => $this->isMenuAllowedWithAncestorsForTenant(
                $menuId,
                $menuTreeReference,
                $overrideByMenuId,
            ))
            ->values()
            ->all();
    }

    private function isMenuAllowedWithAncestorsForTenant(
        int $menuId,
        Collection $menuTreeReference,
        Collection $overrideByMenuId,
    ): bool {
        $currentMenuId = $menuId;
        $safetyCounter = 0;

        while ($currentMenuId > 0 && $safetyCounter < 20) {
            $safetyCounter++;

            /** @var Menu|null $menu */
            $menu = $menuTreeReference->get($currentMenuId);
            if (! $menu || ! (bool) $menu->is_active) {
                return false;
            }

            $override = $overrideByMenuId->get($currentMenuId);
            if ($override && (! (bool) $override->is_active || ! (bool) $override->is_visible)) {
                return false;
            }

            $parentId = (int) ($menu->parent_id ?? 0);
            if ($parentId <= 0) {
                return true;
            }

            $currentMenuId = $parentId;
        }

        return $currentMenuId <= 0;
    }

    /**
     * @param  array<int, int>  $menuIds
     * @return Collection<int, string>
     */
    private function resolveSystemMenuPathMap(array $menuIds): Collection
    {
        $normalizedMenuIds = collect($menuIds)
            ->map(fn ($menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->unique()
            ->values()
            ->all();

        if (count($normalizedMenuIds) === 0) {
            return collect();
        }

        $menuById = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->get(['id', 'parent_id', 'label'])
            ->keyBy('id');

        if ($menuById->isEmpty()) {
            return collect();
        }

        return collect($normalizedMenuIds)
            ->mapWithKeys(function (int $menuId) use ($menuById): array {
                return [$menuId => $this->buildSystemMenuPath($menuId, $menuById)];
            });
    }

    private function buildSystemMenuPath(int $menuId, Collection $menuById): string
    {
        $currentMenuId = $menuId;
        $safetyCounter = 0;
        $pathSegments = [];

        while ($currentMenuId > 0 && $safetyCounter < 20) {
            $safetyCounter++;

            /** @var Menu|null $menu */
            $menu = $menuById->get($currentMenuId);
            if (! $menu) {
                break;
            }

            $label = trim((string) $menu->label);
            if ($label !== '') {
                array_unshift($pathSegments, $label);
            }

            $parentId = (int) ($menu->parent_id ?? 0);
            if ($parentId <= 0) {
                break;
            }

            $currentMenuId = $parentId;
        }

        return implode(' / ', $pathSegments);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitMenuPath(string $menuPath, string $fallbackLabel = ''): array
    {
        $segments = collect(explode(' / ', $menuPath))
            ->map(fn (string $segment): string => trim($segment))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->values();

        if ($segments->isEmpty()) {
            $normalizedFallback = trim($fallbackLabel);
            if ($normalizedFallback === '') {
                return ['-', '-'];
            }

            return [
                $normalizedFallback,
                $normalizedFallback,
            ];
        }

        if ($segments->count() === 1) {
            $singleLabel = (string) $segments->first();

            return [
                $singleLabel,
                $singleLabel,
            ];
        }

        return [
            (string) $segments->first(),
            $segments->slice(1)->implode(' / '),
        ];
    }

    private function resolveCurrentUri(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }

    private function canManagePermissions(mixed $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! method_exists($user, 'can')) {
            return false;
        }

        try {
            return $user->can('users.manage');
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveActiveTab(string $tab): string
    {
        $normalizedTab = strtolower(trim($tab));

        return in_array($normalizedTab, ['permissions', 'nota'], true)
            ? $normalizedTab
            : 'permissions';
    }

    private function resolvePermissionSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['menu_label', 'submenu_label', 'name', 'guard_name'], true)
            ? $sortBy
            : 'menu_label';
    }

    private function resolvePermissionSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
    }

    private function resolvePermissionPerPage(int $perPage): int
    {
        return 50;
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









