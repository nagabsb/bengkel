<?php

namespace App\Services\Menu;

use App\Models\Menu;
use App\Models\MenuPermission;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class SystemMenuPermissionService
{
    /**
     * @param  array<int, int>|null  $menuIds
     * @return array<int, int>
     */
    public function resolveSystemMenuPermissionIds(?array $menuIds = null, bool $onlyActiveMenus = true): array
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('permissions') || ! Schema::hasTable('menu_permission')) {
            return [];
        }

        $query = MenuPermission::query()
            ->join('menus', 'menus.id', '=', 'menu_permission.menu_id')
            ->whereNull('menus.tenant_id')
            ->where('menus.menu_type', 'system');

        if ($onlyActiveMenus) {
            $query->where('menus.is_active', true);
        }

        if (is_array($menuIds)) {
            $normalizedMenuIds = collect($menuIds)
                ->map(fn ($menuId): int => (int) $menuId)
                ->filter(fn (int $menuId): bool => $menuId > 0)
                ->unique()
                ->values()
                ->all();

            if (count($normalizedMenuIds) === 0) {
                return [];
            }

            $query->whereIn('menus.id', $normalizedMenuIds);
        }

        return $query
            ->pluck('menu_permission.permission_id')
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function resolvePlatformPermissionIds(): array
    {
        if (! Schema::hasTable('permissions')) {
            return [];
        }

        return Permission::query()
            ->where('name', 'like', 'platform.%')
            ->pluck('id')
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function inferPermissionNames(string $label, ?string $route): array
    {
        $normalizedLabel = Str::of($label)->lower()->squish()->value();
        $normalizedRoute = Str::of((string) $route)->before('?')->lower()->squish()->value();
        $context = trim("{$normalizedRoute} {$normalizedLabel}");

        if ($context === '') {
            return [];
        }

        $containsAny = static function (array $needles) use ($context): bool {
            foreach ($needles as $needle) {
                if ($needle !== '' && Str::contains($context, $needle)) {
                    return true;
                }
            }

            return false;
        };

        $isOwnerReportRoute = Str::startsWith($normalizedRoute, 'owner.reports.');
        if ($isOwnerReportRoute) {
            return collect($this->inferPermissionNamesFromRoute($normalizedRoute))
                ->map(fn ($permissionName): string => (string) $permissionName)
                ->filter(fn (string $permissionName): bool => $permissionName !== '')
                ->unique()
                ->values()
                ->all();
        }

        $permissionNames = [];

        if ($containsAny(['platform', 'tenant'])) {
            $permissionNames[] = 'platform.tenants.view';
            $permissionNames[] = 'platform.tenants.manage';
        }

        if ($containsAny(['billing', 'langganan', 'subscription', 'tagihan'])) {
            $permissionNames[] = 'platform.billing.view';
            $permissionNames[] = 'platform.billing.manage';
        }

        if ($containsAny(['dashboard', 'dasbor'])) {
            $permissionNames[] = 'owner.dashboard.view';
        }

        if ($containsAny(['order', 'orders', 'pesanan', 'service'])) {
            $permissionNames[] = 'service_orders.view';
            $permissionNames[] = 'service_orders.manage';
        }

        if ($containsAny(['booking', 'bookings', 'antrian', 'queue'])) {
            $permissionNames[] = 'bookings.view';
            $permissionNames[] = 'bookings.manage';
        }

        if ($containsAny(['vehicle', 'vehicles', 'kendaraan'])) {
            $permissionNames[] = 'service_orders.view';
            $permissionNames[] = 'service_orders.manage';
        }

        if ($containsAny(['product', 'products', 'produk', 'inventory', 'stok'])) {
            $permissionNames[] = 'inventory.view';
            $permissionNames[] = 'inventory.manage';
        }

        if ($containsAny(['user', 'users', 'pengguna', 'tim', 'team', 'permission', 'izin'])) {
            $permissionNames[] = 'users.manage';
        }

        if ($containsAny(['workshop', 'workshops', 'bengkel', 'cabang'])) {
            $permissionNames[] = 'users.manage';
        }

        if (! $isOwnerReportRoute && $containsAny(['report', 'reports', 'laporan', 'finance', 'keuangan'])) {
            $permissionNames[] = 'finance.view';
            $permissionNames[] = 'finance.manage';
        }

        $isExpenseCategoryContext = $containsAny(['kategori pengeluaran', 'expense categories', 'expense category']);

        if ($containsAny(['expense', 'expenses', 'pengeluaran', 'biaya']) && ! $isExpenseCategoryContext) {
            $permissionNames[] = 'expenses.view';
            $permissionNames[] = 'expenses.manage';
        }

        if ($isExpenseCategoryContext) {
            $permissionNames[] = 'expense_categories.view';
            $permissionNames[] = 'expense_categories.manage';
        }

        if ($containsAny(['analytics', 'analitik'])) {
            $permissionNames[] = 'finance.view';
        }

        if ($containsAny(['customer', 'customers', 'pelanggan'])) {
            $permissionNames[] = 'customers.view';
            $permissionNames[] = 'customers.manage';
        }

        if ($containsAny(['supplier', 'suppliers', 'vendor'])) {
            $permissionNames[] = 'suppliers.view';
            $permissionNames[] = 'suppliers.manage';
        }

        if ($containsAny(['warehouse', 'warehouses', 'gudang'])) {
            $permissionNames[] = 'warehouses.view';
            $permissionNames[] = 'warehouses.manage';
        }

        $isSparePartCategoryContext = $containsAny(['kategori sparepart', 'sparepart categories', 'sparepart category']);
        $isSparePartUnitContext = $containsAny(['satuan sparepart', 'sparepart units', 'sparepart unit']);

        if (
            $containsAny(['sparepart', 'spareparts', 'spare part', 'suku cadang', 'suku-cadang'])
            && ! $isSparePartCategoryContext
            && ! $isSparePartUnitContext
        ) {
            $permissionNames[] = 'spareparts.view';
            $permissionNames[] = 'spareparts.manage';
        }

        if ($isSparePartCategoryContext) {
            $permissionNames[] = 'sparepart_categories.view';
            $permissionNames[] = 'sparepart_categories.manage';
        }

        if ($isSparePartUnitContext) {
            $permissionNames[] = 'sparepart_units.view';
            $permissionNames[] = 'sparepart_units.manage';
        }

        if (count($permissionNames) === 0) {
            $permissionNames = [
                ...$permissionNames,
                ...$this->inferPermissionNamesFromRoute($normalizedRoute),
            ];
        }

        return collect($permissionNames)
            ->map(fn ($permissionName): string => (string) $permissionName)
            ->unique()
            ->values()
            ->all();
    }

    public function syncMenuPermissionMap(int $menuId, string $label, ?string $route): void
    {
        if (
            $menuId <= 0
            || ! Schema::hasTable('menus')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('menu_permission')
        ) {
            return;
        }

        $nextPermissionNames = $this->inferPermissionNames($label, $route);
        if (count($nextPermissionNames) === 0) {
            return;
        }

        $nextPermissionIds = collect($nextPermissionNames)
            ->map(function (string $permissionName): int {
                $permission = Permission::findOrCreate($permissionName, 'web');

                return (int) $permission->id;
            })
            ->filter(fn (int $permissionId): bool => $permissionId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $currentPermissionIds = MenuPermission::query()
            ->where('menu_id', $menuId)
            ->pluck('permission_id')
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->filter(fn (int $permissionId): bool => $permissionId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($currentPermissionIds === $nextPermissionIds) {
            return;
        }

        $removedPermissionIds = collect($currentPermissionIds)
            ->diff($nextPermissionIds)
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->values()
            ->all();

        MenuPermission::query()
            ->where('menu_id', $menuId)
            ->delete();

        if (count($nextPermissionIds) > 0) {
            MenuPermission::query()->insert(
                collect($nextPermissionIds)
                    ->map(fn (int $permissionId): array => [
                        'menu_id' => $menuId,
                        'permission_id' => $permissionId,
                    ])
                    ->values()
                    ->all(),
            );
        }

        $this->deleteOrphanPermissions($removedPermissionIds);
    }

    /**
     * @return array<int, int>
     */
    public function collectMenuPermissionIds(int $menuId): array
    {
        if ($menuId <= 0 || ! Schema::hasTable('menu_permission')) {
            return [];
        }

        return MenuPermission::query()
            ->where('menu_id', $menuId)
            ->pluck('permission_id')
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->filter(fn (int $permissionId): bool => $permissionId > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    public function deleteOrphanPermissions(array $permissionIds): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('menu_permission')) {
            return;
        }

        $normalizedPermissionIds = collect($permissionIds)
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->filter(fn (int $permissionId): bool => $permissionId > 0)
            ->unique()
            ->values()
            ->all();

        if (count($normalizedPermissionIds) === 0) {
            return;
        }

        $stillMappedPermissionIds = MenuPermission::query()
            ->whereIn('permission_id', $normalizedPermissionIds)
            ->pluck('permission_id')
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->unique()
            ->values()
            ->all();

        $orphanPermissionIds = collect($normalizedPermissionIds)
            ->reject(fn (int $permissionId): bool => in_array($permissionId, $stillMappedPermissionIds, true))
            ->values()
            ->all();

        if (count($orphanPermissionIds) === 0) {
            return;
        }

        Permission::query()
            ->whereIn('id', $orphanPermissionIds)
            ->delete();
    }

    /**
     * @param  array<int, int>|null  $menuIds
     */
    public function syncCatalog(?array $menuIds = null): void
    {
        if (
            ! Schema::hasTable('menus')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('menu_permission')
        ) {
            return;
        }

        $query = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system');

        if (is_array($menuIds)) {
            $normalizedMenuIds = collect($menuIds)
                ->map(fn ($menuId): int => (int) $menuId)
                ->filter(fn (int $menuId): bool => $menuId > 0)
                ->unique()
                ->values()
                ->all();

            if (count($normalizedMenuIds) === 0) {
                return;
            }

            $query->whereIn('id', $normalizedMenuIds);
        }

        $systemMenus = $query
            ->orderBy('id')
            ->get(['id', 'label', 'route'])
            ->map(fn (Menu $menu): array => [
                'id' => (int) $menu->id,
                'label' => (string) $menu->label,
                'route' => $menu->route ? (string) $menu->route : null,
            ]);

        /** @var array{id:int,label:string,route:?string} $systemMenu */
        foreach ($systemMenus as $systemMenu) {
            $this->syncMenuPermissionMap(
                $systemMenu['id'],
                $systemMenu['label'],
                $systemMenu['route'],
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function inferPermissionNamesFromRoute(string $normalizedRoute): array
    {
        if ($normalizedRoute === '') {
            return [];
        }

        $segments = collect(explode('.', $normalizedRoute))
            ->map(fn (string $segment): string => trim($segment))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->values();

        if ($segments->isEmpty()) {
            return [];
        }

        $prefix = (string) $segments->first();
        $module = (string) ($segments->get(1) ?? '');
        if ($module === '') {
            $module = (string) $segments->first();
        }

        $module = Str::of($module)
            ->replace('-', '_')
            ->replace(' ', '_')
            ->lower()
            ->value();

        if ($module === '') {
            return [];
        }

        if ($prefix === 'owner' && $module === 'reports') {
            $reportSegment = Str::of((string) ($segments->get(2) ?? ''))
                ->replace('-', '_')
                ->replace(' ', '_')
                ->lower()
                ->value();

            $ownerReportPermissionMap = [
                'sales' => ['service_orders.view', 'service_orders.manage'],
                'spareparts' => ['spareparts.view', 'spareparts.manage'],
                'expenses' => ['expenses.view', 'expenses.manage'],
                'customers' => ['customers.view', 'customers.manage'],
                'profit_loss' => ['expenses.view', 'expenses.manage'],
                'ai_monthly' => ['service_orders.view', 'service_orders.manage'],
            ];

            if (array_key_exists($reportSegment, $ownerReportPermissionMap)) {
                return $ownerReportPermissionMap[$reportSegment];
            }
        }

        if ($prefix === 'platform') {
            return [
                "platform.{$module}.view",
                "platform.{$module}.manage",
            ];
        }

        if ($prefix === 'owner' && $module === 'dashboard') {
            return ['owner.dashboard.view'];
        }

        if ($prefix === 'owner') {
            $ownerModulePermissionMap = [
                'orders' => ['service_orders.view', 'service_orders.manage'],
                'bookings' => ['bookings.view', 'bookings.manage'],
                'vehicles' => ['service_orders.view', 'service_orders.manage'],
                'spareparts' => ['spareparts.view', 'spareparts.manage'],
                'sparepart_categories' => ['sparepart_categories.view', 'sparepart_categories.manage'],
                'sparepart_units' => ['sparepart_units.view', 'sparepart_units.manage'],
                'warehouses' => ['warehouses.view', 'warehouses.manage'],
                'suppliers' => ['suppliers.view', 'suppliers.manage'],
                'customers' => ['customers.view', 'customers.manage'],
                'expenses' => ['expenses.view', 'expenses.manage'],
                'expense_categories' => ['expense_categories.view', 'expense_categories.manage'],
                'users' => ['users.manage'],
                'settings' => ['users.manage'],
                'workshops' => ['users.manage'],
            ];

            if (array_key_exists($module, $ownerModulePermissionMap)) {
                return $ownerModulePermissionMap[$module];
            }
        }

        if ($prefix === 'owner') {
            return [
                "owner.{$module}.view",
                "owner.{$module}.manage",
            ];
        }

        return [
            "{$module}.view",
            "{$module}.manage",
        ];
    }
}
