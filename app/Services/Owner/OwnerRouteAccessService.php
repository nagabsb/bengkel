<?php

namespace App\Services\Owner;

use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\PlanMenu;
use App\Models\Tenant;
use App\Models\WorkshopMenuOverride;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OwnerRouteAccessService
{
    /**
     * @var array<string, array{route_name: string, query: array<string, string>}>
     */
    private const ROUTE_ALIASES = [
        'owner.permissions.sync' => [
            'route_name' => 'owner.settings',
            'query' => ['tab' => 'permissions'],
        ],
        'owner.settings.print.update' => [
            'route_name' => 'owner.settings',
            'query' => ['tab' => 'nota'],
        ],
        'owner.settings.print.kiosk-installer' => [
            'route_name' => 'owner.settings',
            'query' => ['tab' => 'nota'],
        ],
        'owner.workshops.store' => [
            'route_name' => 'owner.workshops.index',
            'query' => [],
        ],
        'owner.workshops.switch-active' => [
            'route_name' => 'owner.dashboard',
            'query' => [],
        ],
        'owner.workshops.switch-plan' => [
            'route_name' => 'owner.workshops.index',
            'query' => [],
        ],
        'owner.workshops.continue-pending-payment' => [
            'route_name' => 'owner.workshops.index',
            'query' => [],
        ],
        'owner.workshops.confirm-midtrans-payment' => [
            'route_name' => 'owner.workshops.index',
            'query' => [],
        ],
        'owner.workshops.update' => [
            'route_name' => 'owner.workshops.index',
            'query' => [],
        ],
        'owner.workshops.destroy' => [
            'route_name' => 'owner.workshops.index',
            'query' => [],
        ],
        'owner.customers.store' => [
            'route_name' => 'owner.customers.index',
            'query' => [],
        ],
        'owner.customers.update' => [
            'route_name' => 'owner.customers.index',
            'query' => [],
        ],
        'owner.customers.destroy' => [
            'route_name' => 'owner.customers.index',
            'query' => [],
        ],
        'owner.suppliers.store' => [
            'route_name' => 'owner.suppliers.index',
            'query' => [],
        ],
        'owner.suppliers.update' => [
            'route_name' => 'owner.suppliers.index',
            'query' => [],
        ],
        'owner.suppliers.destroy' => [
            'route_name' => 'owner.suppliers.index',
            'query' => [],
        ],
        'owner.warehouses.store' => [
            'route_name' => 'owner.warehouses.index',
            'query' => [],
        ],
        'owner.warehouses.update' => [
            'route_name' => 'owner.warehouses.index',
            'query' => [],
        ],
        'owner.warehouses.destroy' => [
            'route_name' => 'owner.warehouses.index',
            'query' => [],
        ],
        'owner.spareparts.store' => [
            'route_name' => 'owner.spareparts.index',
            'query' => [],
        ],
        'owner.spareparts.update' => [
            'route_name' => 'owner.spareparts.index',
            'query' => [],
        ],
        'owner.spareparts.destroy' => [
            'route_name' => 'owner.spareparts.index',
            'query' => [],
        ],
        'owner.sparepart-categories.store' => [
            'route_name' => 'owner.sparepart-categories.index',
            'query' => [],
        ],
        'owner.sparepart-categories.update' => [
            'route_name' => 'owner.sparepart-categories.index',
            'query' => [],
        ],
        'owner.sparepart-categories.destroy' => [
            'route_name' => 'owner.sparepart-categories.index',
            'query' => [],
        ],
        'owner.sparepart-units.store' => [
            'route_name' => 'owner.sparepart-units.index',
            'query' => [],
        ],
        'owner.sparepart-units.update' => [
            'route_name' => 'owner.sparepart-units.index',
            'query' => [],
        ],
        'owner.sparepart-units.destroy' => [
            'route_name' => 'owner.sparepart-units.index',
            'query' => [],
        ],
        'owner.orders.store' => [
            'route_name' => 'owner.orders.index',
            'query' => [],
        ],
        'owner.orders.estimates.store' => [
            'route_name' => 'owner.orders.index',
            'query' => [],
        ],
        'owner.orders.estimates.ai-draft' => [
            'route_name' => 'owner.orders.index',
            'query' => [],
        ],
        'owner.orders.diagnosis.ai-draft' => [
            'route_name' => 'owner.orders.index',
            'query' => [],
        ],
        'owner.orders.update-status' => [
            'route_name' => 'owner.orders.index',
            'query' => [],
        ],
        'owner.reports.sales.export' => [
            'route_name' => 'owner.reports.sales.index',
            'query' => [],
        ],
        'owner.bookings.store' => [
            'route_name' => 'owner.bookings.index',
            'query' => [],
        ],
        'owner.bookings.update-status' => [
            'route_name' => 'owner.bookings.index',
            'query' => [],
        ],
        'owner.bookings.builder.update' => [
            'route_name' => 'owner.bookings.builder',
            'query' => [],
        ],
        'owner.expenses.store' => [
            'route_name' => 'owner.expenses.index',
            'query' => [],
        ],
        'owner.expenses.update' => [
            'route_name' => 'owner.expenses.index',
            'query' => [],
        ],
        'owner.expenses.destroy' => [
            'route_name' => 'owner.expenses.index',
            'query' => [],
        ],
        'owner.expense-categories.store' => [
            'route_name' => 'owner.expense-categories.index',
            'query' => [],
        ],
        'owner.expense-categories.update' => [
            'route_name' => 'owner.expense-categories.index',
            'query' => [],
        ],
        'owner.expense-categories.destroy' => [
            'route_name' => 'owner.expense-categories.index',
            'query' => [],
        ],
        'owner.invoices.payments.store' => [
            'route_name' => 'owner.invoice-payments.index',
            'query' => [],
        ],
        'owner.invoices.due-date.update' => [
            'route_name' => 'owner.receivables.index',
            'query' => [],
        ],
        'owner.invoices.reminder.mark' => [
            'route_name' => 'owner.receivables.index',
            'query' => [],
        ],
        'owner.vehicles.store' => [
            'route_name' => 'owner.vehicles.index',
            'query' => [],
        ],
        'owner.vehicles.update' => [
            'route_name' => 'owner.vehicles.index',
            'query' => [],
        ],
        'owner.vehicles.destroy' => [
            'route_name' => 'owner.vehicles.index',
            'query' => [],
        ],
        'owner.vehicles.sync' => [
            'route_name' => 'owner.vehicles.index',
            'query' => [],
        ],
        'owner.users.store' => [
            'route_name' => 'owner.users.index',
            'query' => [],
        ],
        'owner.users.update' => [
            'route_name' => 'owner.users.index',
            'query' => [],
        ],
        'owner.users.destroy' => [
            'route_name' => 'owner.users.index',
            'query' => [],
        ],
    ];

    public function __construct(
        private readonly TenantPlanResolver $planResolver,
    ) {}

    public function canAccess(Request $request, string $tenantId, ?Authenticatable $user): bool
    {
        return $this->canAccessResolvedRouteContext(
            $this->resolveEffectiveRouteContext($request),
            $tenantId,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function canAccessRouteName(
        string $routeName,
        array $query,
        string $tenantId,
        ?Authenticatable $user,
    ): bool {
        $routeContext = $this->resolveRouteContextByName($routeName, $query);

        return $this->canAccessResolvedRouteContext($routeContext, $tenantId, $user);
    }

    /**
     * @param  array{route_name: string, query: array<string, string>}  $routeContext
     */
    private function canAccessResolvedRouteContext(
        array $routeContext,
        string $tenantId,
        ?Authenticatable $user,
    ): bool {
        $routeName = $routeContext['route_name'];

        if ($routeName === '' || ! str_starts_with($routeName, 'owner.')) {
            return true;
        }

        if (! $user || ! method_exists($user, 'can')) {
            return false;
        }

        if (! $this->isTenantActive($tenantId)) {
            return false;
        }

        $plan = $this->planResolver->forTenantId($tenantId);
        if (! $plan) {
            return false;
        }

        if (! Schema::hasTable('menus')) {
            return false;
        }

        $candidateMenus = $this->resolveCandidateMenus($routeName);
        if ($candidateMenus->isEmpty()) {
            return false;
        }

        $menuIds = $candidateMenus
            ->pluck('id')
            ->map(fn (mixed $menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->values()
            ->all();

        if (count($menuIds) === 0) {
            return false;
        }

        $planId = data_get($plan, 'plan.id');
        $planAllowedMenuIds = $this->resolvePlanAllowedMenuIds($planId);
        $hasPlanRules = is_numeric($planId);

        $menuTreeReference = Menu::query()
            ->whereNull('tenant_id')
            ->get(['id', 'parent_id', 'is_active'])
            ->keyBy('id');

        $overrideByMenuId = collect();
        if (Schema::hasTable('workshop_menu_overrides')) {
            $overrideByMenuId = WorkshopMenuOverride::query()
                ->where('tenant_id', $tenantId)
                ->get(['menu_id', 'is_visible', 'is_active'])
                ->keyBy('menu_id');
        }

        $permissionNamesByMenuId = $this->resolvePermissionNamesByMenuId($menuIds);
        $effectiveQuery = $routeContext['query'];

        foreach ($candidateMenus as $menu) {
            if (! $this->doesRouteQueryMatch((string) ($menu->route ?? ''), $effectiveQuery)) {
                continue;
            }

            $menuId = (int) $menu->id;
            if (! $this->isMenuAllowedWithAncestors(
                $menuId,
                $menuTreeReference,
                $overrideByMenuId,
                $hasPlanRules,
                $planAllowedMenuIds,
            )) {
                continue;
            }

            $permissionNames = $permissionNamesByMenuId->get($menuId, []);
            if (! $this->userHasMenuPermission($user, $permissionNames)) {
                continue;
            }

            return true;
        }

        if (
            $routeName === 'owner.settings'
            && strtolower(trim((string) ($effectiveQuery['tab'] ?? ''))) === 'nota'
        ) {
            return $this->canAccessResolvedRouteContext(
                [
                    'route_name' => 'owner.settings',
                    'query' => ['tab' => 'permissions'],
                ],
                $tenantId,
                $user,
            );
        }

        return false;
    }

    /**
     * @return array{route_name: string, query: array<string, string>}
     */
    private function resolveEffectiveRouteContext(Request $request): array
    {
        return $this->resolveRouteContextByName(
            (string) ($request->route()?->getName() ?? ''),
            $request->query(),
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{route_name: string, query: array<string, string>}
     */
    private function resolveRouteContextByName(string $routeName, array $query): array
    {
        $query = $this->normalizeRouteQuery($query);

        $routeAlias = self::ROUTE_ALIASES[$routeName] ?? null;
        if (is_array($routeAlias)) {
            $routeName = (string) ($routeAlias['route_name'] ?? '');
            $query = [
                ...((array) ($routeAlias['query'] ?? [])),
                ...$query,
            ];
        }

        if ($routeName === 'owner.settings' && ! array_key_exists('tab', $query)) {
            $query['tab'] = 'permissions';
        }

        return [
            'route_name' => $routeName,
            'query' => $query,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    private function normalizeRouteQuery(array $query): array
    {
        return collect($query)
            ->mapWithKeys(function (mixed $value, string $key): array {
                if (is_array($value)) {
                    $firstValue = reset($value);

                    return [$key => is_scalar($firstValue) ? (string) $firstValue : ''];
                }

                return [$key => is_scalar($value) ? (string) $value : ''];
            })
            ->all();
    }

    /**
     * @return Collection<int, Menu>
     */
    private function resolveCandidateMenus(string $routeName): Collection
    {
        return Menu::query()
            ->whereNull('tenant_id')
            ->whereNotNull('route')
            ->where('is_active', true)
            ->get(['id', 'parent_id', 'route', 'is_active'])
            ->filter(function (Menu $menu) use ($routeName): bool {
                $routeTarget = $this->parseRouteTarget((string) ($menu->route ?? ''));
                if (! $routeTarget) {
                    return false;
                }

                return $routeTarget['route_name'] === $routeName;
            })
            ->values();
    }

    /**
     * @param  array<string, string>  $effectiveQuery
     */
    private function doesRouteQueryMatch(string $menuRoute, array $effectiveQuery): bool
    {
        $routeTarget = $this->parseRouteTarget($menuRoute);
        if (! $routeTarget) {
            return false;
        }

        $routeQuery = $routeTarget['query'];
        if (count($routeQuery) === 0) {
            return true;
        }

        foreach ($routeQuery as $key => $value) {
            if (! array_key_exists($key, $effectiveQuery) || $effectiveQuery[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{route_name: string, query: array<string, string>}|null
     */
    private function parseRouteTarget(string $routeTarget): ?array
    {
        $normalizedTarget = trim($routeTarget);
        if ($normalizedTarget === '' || str_starts_with($normalizedTarget, '/')) {
            return null;
        }

        $routeName = $normalizedTarget;
        $query = [];

        if (str_contains($normalizedTarget, '?')) {
            [$routeName, $queryString] = explode('?', $normalizedTarget, 2);
            $routeName = trim($routeName);

            parse_str((string) $queryString, $parsedQuery);
            $query = collect($parsedQuery)
                ->mapWithKeys(function (mixed $value, string $key): array {
                    if (is_array($value)) {
                        $firstValue = reset($value);

                        return [$key => is_scalar($firstValue) ? (string) $firstValue : ''];
                    }

                    return [$key => is_scalar($value) ? (string) $value : ''];
                })
                ->all();
        }

        if ($routeName === '') {
            return null;
        }

        return [
            'route_name' => $routeName,
            'query' => $query,
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function resolvePlanAllowedMenuIds(mixed $planId): Collection
    {
        if (! is_numeric($planId) || ! Schema::hasTable('plan_menu')) {
            return collect();
        }

        return PlanMenu::query()
            ->where('plan_id', (int) $planId)
            ->pluck('menu_id')
            ->map(fn (mixed $menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->unique()
            ->values();
    }

    /**
     * @param  array<int, int>  $menuIds
     * @return Collection<int, array<int, string>>
     */
    private function resolvePermissionNamesByMenuId(array $menuIds): Collection
    {
        if (count($menuIds) === 0 || ! Schema::hasTable('menu_permission') || ! Schema::hasTable('permissions')) {
            return collect();
        }

        return MenuPermission::query()
            ->whereIn('menu_id', $menuIds)
            ->join('permissions', 'permissions.id', '=', 'menu_permission.permission_id')
            ->get([
                'menu_permission.menu_id as menu_id',
                'permissions.name as permission_name',
            ])
            ->groupBy('menu_id')
            ->map(function (Collection $rows): array {
                return $rows
                    ->pluck('permission_name')
                    ->map(fn (mixed $permissionName): string => (string) $permissionName)
                    ->filter(fn (string $permissionName): bool => $permissionName !== '')
                    ->unique()
                    ->values()
                    ->all();
            });
    }

    private function isMenuAllowedWithAncestors(
        int $menuId,
        Collection $menuTreeReference,
        Collection $overrideByMenuId,
        bool $hasPlanRules,
        Collection $planAllowedMenuIds,
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

            if ($hasPlanRules && ! $planAllowedMenuIds->contains($currentMenuId)) {
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
     * @param  array<int, string>  $permissionNames
     */
    private function userHasMenuPermission(?Authenticatable $user, array $permissionNames): bool
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

    private function isTenantActive(string $tenantId): bool
    {
        if (! Schema::hasTable('tenants')) {
            return false;
        }

        return Tenant::query()
            ->where('id', $tenantId)
            ->where('is_active', true)
            ->exists();
    }
}
