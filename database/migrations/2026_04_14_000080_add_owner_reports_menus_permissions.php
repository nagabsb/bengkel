<?php

use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\Plan;
use App\Models\PlanMenu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $reportsRootMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('parent_id')
            ->where('label', 'Laporan')
            ->first();

        if (! $reportsRootMenu) {
            $reportsRootMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => null,
                'menu_type' => 'system',
                'label' => 'Laporan',
                'route' => null,
                'icon' => 'reports',
                'sort_order' => 55,
                'is_active' => true,
            ]);
        } else {
            $reportsRootMenu->forceFill([
                'parent_id' => null,
                'label' => 'Laporan',
                'route' => null,
                'icon' => 'reports',
                'sort_order' => 55,
                'is_active' => true,
            ])->save();
        }

        $salesMenu = $this->upsertChildMenu(
            parentId: (int) $reportsRootMenu->id,
            route: 'owner.reports.sales.index',
            label: 'Laporan Servis',
            icon: 'services',
            sortOrder: 10,
        );

        $sparepartMenu = $this->upsertChildMenu(
            parentId: (int) $reportsRootMenu->id,
            route: 'owner.reports.spareparts.index',
            label: 'Laporan Sparepart',
            icon: 'products',
            sortOrder: 20,
        );

        $expenseMenu = $this->upsertChildMenu(
            parentId: (int) $reportsRootMenu->id,
            route: 'owner.reports.expenses.index',
            label: 'Laporan Pengeluaran',
            icon: 'finance',
            sortOrder: 30,
        );

        $customerMenu = $this->upsertChildMenu(
            parentId: (int) $reportsRootMenu->id,
            route: 'owner.reports.customers.index',
            label: 'Laporan Pelanggan',
            icon: 'customers',
            sortOrder: 40,
        );

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $salesViewPermission = Permission::findOrCreate('service_orders.view', 'web');
            $salesManagePermission = Permission::findOrCreate('service_orders.manage', 'web');
            $sparepartViewPermission = Permission::findOrCreate('spareparts.view', 'web');
            $sparepartManagePermission = Permission::findOrCreate('spareparts.manage', 'web');
            $expenseViewPermission = Permission::findOrCreate('expenses.view', 'web');
            $expenseManagePermission = Permission::findOrCreate('expenses.manage', 'web');
            $customerViewPermission = Permission::findOrCreate('customers.view', 'web');
            $customerManagePermission = Permission::findOrCreate('customers.manage', 'web');

            $this->syncMenuPermission((int) $salesMenu->id, (int) $salesViewPermission->id);
            $this->syncMenuPermission((int) $salesMenu->id, (int) $salesManagePermission->id);
            $this->syncMenuPermission((int) $sparepartMenu->id, (int) $sparepartViewPermission->id);
            $this->syncMenuPermission((int) $sparepartMenu->id, (int) $sparepartManagePermission->id);
            $this->syncMenuPermission((int) $expenseMenu->id, (int) $expenseViewPermission->id);
            $this->syncMenuPermission((int) $expenseMenu->id, (int) $expenseManagePermission->id);
            $this->syncMenuPermission((int) $customerMenu->id, (int) $customerViewPermission->id);
            $this->syncMenuPermission((int) $customerMenu->id, (int) $customerManagePermission->id);
        }

        if (! Schema::hasTable('plans') || ! Schema::hasTable('plan_menu')) {
            return;
        }

        $planIds = Plan::query()
            ->pluck('id')
            ->map(fn ($planId): int => (int) $planId)
            ->filter(fn (int $planId): bool => $planId > 0)
            ->values();

        $menuIds = collect([
            (int) $reportsRootMenu->id,
            (int) $salesMenu->id,
            (int) $sparepartMenu->id,
            (int) $expenseMenu->id,
            (int) $customerMenu->id,
        ])
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->unique()
            ->values();

        foreach ($planIds as $planId) {
            foreach ($menuIds as $menuId) {
                PlanMenu::query()->updateOrCreate(
                    [
                        'plan_id' => $planId,
                        'menu_id' => $menuId,
                    ],
                    [
                        'plan_id' => $planId,
                        'menu_id' => $menuId,
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $reportMenuRoutes = [
            'owner.reports.sales.index',
            'owner.reports.spareparts.index',
            'owner.reports.expenses.index',
            'owner.reports.customers.index',
        ];

        $menuIds = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereIn('route', $reportMenuRoutes)
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->values()
            ->all();

        if (count($menuIds) > 0) {
            if (Schema::hasTable('plan_menu')) {
                PlanMenu::query()
                    ->whereIn('menu_id', $menuIds)
                    ->delete();
            }

            if (Schema::hasTable('menu_permission')) {
                MenuPermission::query()
                    ->whereIn('menu_id', $menuIds)
                    ->delete();
            }

            Menu::query()
                ->whereIn('id', $menuIds)
                ->delete();
        }

        $reportsRootMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('parent_id')
            ->whereNull('route')
            ->where('label', 'Laporan')
            ->first();

        if (! $reportsRootMenu) {
            return;
        }

        $hasChildren = Menu::query()
            ->where('parent_id', (int) $reportsRootMenu->id)
            ->exists();

        if ($hasChildren) {
            return;
        }

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->where('menu_id', (int) $reportsRootMenu->id)
                ->delete();
        }

        if (Schema::hasTable('menu_permission')) {
            MenuPermission::query()
                ->where('menu_id', (int) $reportsRootMenu->id)
                ->delete();
        }

        $reportsRootMenu->delete();
    }

    private function upsertChildMenu(
        int $parentId,
        string $route,
        string $label,
        string $icon,
        int $sortOrder,
    ): Menu {
        $menu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', $route)
            ->first();

        if (! $menu) {
            return Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => $parentId,
                'menu_type' => 'system',
                'label' => $label,
                'route' => $route,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);
        }

        $menu->forceFill([
            'parent_id' => $parentId,
            'label' => $label,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ])->save();

        return $menu;
    }

    private function syncMenuPermission(int $menuId, int $permissionId): void
    {
        MenuPermission::query()->updateOrCreate(
            [
                'menu_id' => $menuId,
                'permission_id' => $permissionId,
            ],
            [
                'menu_id' => $menuId,
                'permission_id' => $permissionId,
            ],
        );
    }
};
