<?php

namespace App\Services\Owner;

use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\PlanMenu;
use App\Models\WorkshopMenuOverride;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class OwnerMenuService
{
    /**
     * Finance menu is intentionally hidden from owner sidebar for now.
     *
     * @var array<int, string>
     */
    private const HIDDEN_OWNER_MENU_ROUTES = [
        'owner.invoices.index',
        'owner.invoice-payments.index',
        'owner.receivables.index',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildOwnerMenuTree(
        string $tenantId,
        mixed $planId,
        bool $hasPlanMenuTable,
    ): array {
        if (! Schema::hasTable('menus')) {
            return [];
        }

        $planAllowedSystemMenuIds = [];
        if (is_numeric($planId) && $hasPlanMenuTable && Schema::hasTable('plan_menu')) {
            $planAllowedSystemMenuIds = PlanMenu::query()
                ->where('plan_id', (int) $planId)
                ->pluck('menu_id')
                ->map(fn ($menuId): int => (int) $menuId)
                ->values()
                ->all();
        }

        $menus = Menu::query()
            ->whereNull('tenant_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'tenant_id',
                'parent_id',
                'menu_type',
                'label',
                'route',
                'icon',
                'sort_order',
                'is_active',
            ]);

        if ($menus->isEmpty()) {
            return [];
        }

        $overrideMap = collect();
        if (Schema::hasTable('workshop_menu_overrides')) {
            $overrideMap = WorkshopMenuOverride::query()
                ->where('tenant_id', $tenantId)
                ->get([
                    'menu_id',
                    'custom_label',
                    'custom_route',
                    'custom_icon',
                    'sort_order',
                    'is_visible',
                    'is_active',
                ])
                ->keyBy('menu_id');
        }

        $menuIds = $menus
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->values()
            ->all();

        $permissionIdsByMenu = collect();
        $permissionNamesByMenu = collect();
        if (Schema::hasTable('menu_permission') && Schema::hasTable('permissions')) {
            $permissionRows = MenuPermission::query()
                ->whereIn('menu_id', $menuIds)
                ->join('permissions', 'permissions.id', '=', 'menu_permission.permission_id')
                ->get([
                    'menu_permission.menu_id as menu_id',
                    'menu_permission.permission_id as permission_id',
                    'permissions.name as permission_name',
                ]);

            $permissionIdsByMenu = $permissionRows
                ->groupBy('menu_id')
                ->map(function (Collection $rows): array {
                    return $rows
                        ->pluck('permission_id')
                        ->map(fn ($permissionId): int => (int) $permissionId)
                        ->unique()
                        ->values()
                        ->all();
                });

            $permissionNamesByMenu = $permissionRows
                ->groupBy('menu_id')
                ->map(function (Collection $rows): array {
                    return $rows
                        ->pluck('permission_name')
                        ->map(fn ($permissionName): string => (string) $permissionName)
                        ->filter(fn (string $permissionName): bool => $permissionName !== '')
                        ->unique()
                        ->values()
                        ->all();
                });
        }

        $formattedMenus = $menus
            ->map(function (Menu $menu) use (
                $overrideMap,
                $planAllowedSystemMenuIds,
                $planId,
                $permissionIdsByMenu,
                $permissionNamesByMenu,
            ): array {
                $override = $overrideMap->get($menu->id);
                $isSystemMenu = $menu->tenant_id === null;
                $hasPlanRules = is_numeric($planId) && count($planAllowedSystemMenuIds) > 0;
                $menuId = (int) $menu->id;

                return [
                    'id' => $menuId,
                    'tenant_id' => $menu->tenant_id ? (string) $menu->tenant_id : null,
                    'parent_id' => $menu->parent_id ? (int) $menu->parent_id : null,
                    'menu_type' => (string) ($menu->menu_type ?? ($isSystemMenu ? 'system' : 'tenant')),
                    'label' => (string) ($override?->custom_label ?: $menu->label),
                    'base_label' => (string) $menu->label,
                    'route' => $override?->custom_route ?: $menu->route,
                    'icon' => (string) ($override?->custom_icon ?: $menu->icon ?: 'dashboard'),
                    'sort_order' => (int) ($override?->sort_order ?? $menu->sort_order ?? 0),
                    'is_visible' => (bool) ($override?->is_visible ?? true),
                    'is_active' => (bool) ($override?->is_active ?? $menu->is_active),
                    'locked_by_plan' => $hasPlanRules
                        ? $isSystemMenu && ! in_array($menuId, $planAllowedSystemMenuIds, true)
                        : false,
                    'permission_ids' => $permissionIdsByMenu->get($menuId, []),
                    'permission_names' => $permissionNamesByMenu->get($menuId, []),
                ];
            })
            ->values();

        $menusByParent = $formattedMenus->groupBy(fn (array $menu): int => (int) ($menu['parent_id'] ?? 0));
        $buildTree = function (int $parentId) use (&$buildTree, $menusByParent): array {
            return collect($menusByParent->get($parentId, []))
                ->map(fn (array $menu): array => [
                    ...$menu,
                    'children' => $buildTree((int) $menu['id']),
                ])
                ->values()
                ->all();
        };

        return $buildTree(0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $menuTree
     * @return array<int, array<string, mixed>>
     */
    public function buildSidebarMenuItems(
        array $menuTree,
        string $tenantId,
        ?Authenticatable $user,
        string $currentUri,
    ): array {
        return $this->buildSidebarMenuLevel($menuTree, $tenantId, $user, $this->normalizeUri($currentUri));
    }

    /**
     * @param  array<int, array<string, mixed>>  $menus
     * @return array<int, array<string, mixed>>
     */
    private function buildSidebarMenuLevel(
        array $menus,
        string $tenantId,
        ?Authenticatable $user,
        string $currentUri,
    ): array {
        return collect($menus)
            ->map(function (array $menu) use ($tenantId, $user, $currentUri): ?array {
                $routeName = trim((string) ($menu['route'] ?? ''));
                $baseLabel = strtolower(trim((string) ($menu['base_label'] ?? $menu['label'] ?? '')));

                if (in_array($routeName, self::HIDDEN_OWNER_MENU_ROUTES, true) || $baseLabel === 'keuangan') {
                    return null;
                }

                $isVisible = (bool) ($menu['is_visible'] ?? true);
                $isActiveMenu = (bool) ($menu['is_active'] ?? false);
                $isLockedByPlan = (bool) ($menu['locked_by_plan'] ?? false);

                if (! $isVisible || ! $isActiveMenu || $isLockedByPlan) {
                    return null;
                }

                $children = $this->buildSidebarMenuLevel(
                    is_array($menu['children'] ?? null) ? $menu['children'] : [],
                    $tenantId,
                    $user,
                    $currentUri,
                );

                $permissionNames = collect($menu['permission_names'] ?? [])
                    ->map(fn ($permissionName): string => (string) $permissionName)
                    ->filter(fn (string $permissionName): bool => $permissionName !== '')
                    ->values()
                    ->all();

                $canAccessSelf = $this->userCanAccessMenu($user, $permissionNames);
                $resolvedHref = $canAccessSelf
                    ? $this->resolveMenuHref((string) ($menu['route'] ?? ''), $tenantId)
                    : null;

                if (count($children) === 0 && ! $canAccessSelf) {
                    return null;
                }

                $isActive = $this->isMenuActive($resolvedHref, $currentUri)
                    || collect($children)->contains(fn (array $child): bool => (bool) ($child['active'] ?? false));

                return [
                    'key' => 'menu-'.$menu['id'],
                    'label' => (string) ($menu['label'] ?? 'Menu'),
                    'icon' => (string) ($menu['icon'] ?? 'dashboard'),
                    'href' => $resolvedHref,
                    'active' => $isActive,
                    'children' => $children,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    private function userCanAccessMenu(?Authenticatable $user, array $permissionNames): bool
    {
        $effectivePermissionNames = $this->expandPermissionAliases($permissionNames);

        if (count($effectivePermissionNames) === 0) {
            return true;
        }

        if (! $user || ! method_exists($user, 'can')) {
            return false;
        }

        foreach ($effectivePermissionNames as $permissionName) {
            try {
                if ($user->can($permissionName)) {
                    return true;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $permissionNames
     * @return array<int, string>
     */
    private function expandPermissionAliases(array $permissionNames): array
    {
        $aliasMap = [
            'owner.vehicles.view' => ['service_orders.view'],
            'owner.vehicles.manage' => ['service_orders.manage'],
            'owner.orders.view' => ['service_orders.view'],
            'owner.orders.manage' => ['service_orders.manage'],
            'owner.bookings.view' => ['bookings.view'],
            'owner.bookings.manage' => ['bookings.manage'],
            'owner.spareparts.view' => ['spareparts.view'],
            'owner.spareparts.manage' => ['spareparts.manage'],
            'owner.expenses.view' => ['expenses.view', 'finance.view'],
            'owner.expenses.manage' => ['expenses.manage', 'finance.manage'],
            'owner.expense_categories.view' => ['expense_categories.view'],
            'owner.expense_categories.manage' => ['expense_categories.manage'],
        ];

        return collect($permissionNames)
            ->map(fn ($permissionName): string => (string) $permissionName)
            ->filter(fn (string $permissionName): bool => $permissionName !== '')
            ->flatMap(function (string $permissionName) use ($aliasMap): array {
                return [
                    $permissionName,
                    ...($aliasMap[$permissionName] ?? []),
                ];
            })
            ->unique()
            ->values()
            ->all();
    }

    private function resolveMenuHref(string $routeOrPath, string $tenantId): ?string
    {
        $normalizedTarget = trim($routeOrPath);
        if ($normalizedTarget === '') {
            return null;
        }

        if (str_starts_with($normalizedTarget, '/')) {
            return $normalizedTarget;
        }

        $routeName = $normalizedTarget;
        $queryString = '';

        if (str_contains($normalizedTarget, '?')) {
            [$routeName, $queryString] = explode('?', $normalizedTarget, 2);
            $routeName = trim($routeName);
            $queryString = trim($queryString);
        }

        if ($routeName === '' || ! Route::has($routeName)) {
            return null;
        }

        $params = [];
        if (str_starts_with($routeName, 'owner.')) {
            $params['tenant'] = $tenantId;
        }

        try {
            $href = route($routeName, $params, false);
        } catch (\Throwable) {
            return null;
        }

        return $queryString !== '' ? $href.'?'.$queryString : $href;
    }

    private function isMenuActive(?string $href, string $currentUri): bool
    {
        if (! is_string($href) || $href === '') {
            return false;
        }

        $normalizedHref = $this->normalizeUri($href);
        if ($normalizedHref === $currentUri) {
            return true;
        }

        $hrefPath = (string) (parse_url($normalizedHref, PHP_URL_PATH) ?? '');
        $currentPath = (string) (parse_url($currentUri, PHP_URL_PATH) ?? '');

        if ($hrefPath === '' || $currentPath === '') {
            return false;
        }

        return ! str_contains($normalizedHref, '?') && $hrefPath === $currentPath;
    }

    private function normalizeUri(string $uri): string
    {
        $normalizedUri = trim($uri);
        if ($normalizedUri === '') {
            return '/';
        }

        return str_starts_with($normalizedUri, '/')
            ? $normalizedUri
            : '/'.$normalizedUri;
    }
}








