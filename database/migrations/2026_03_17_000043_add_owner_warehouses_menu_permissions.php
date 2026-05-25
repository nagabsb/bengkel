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

        $dataMasterMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('route')
            ->where('label', 'Data Master')
            ->first();

        if (! $dataMasterMenu) {
            $dataMasterMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => null,
                'menu_type' => 'system',
                'label' => 'Data Master',
                'route' => null,
                'icon' => 'archive-box',
                'sort_order' => 17,
                'is_active' => true,
            ]);
        }

        $warehouseMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.warehouses.index')
            ->first();

        if (! $warehouseMenu) {
            $warehouseMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => (int) $dataMasterMenu->id,
                'menu_type' => 'system',
                'label' => 'Gudang',
                'route' => 'owner.warehouses.index',
                'icon' => 'warehouse',
                'sort_order' => 12,
                'is_active' => true,
            ]);
        } else {
            $warehouseMenu->forceFill([
                'parent_id' => (int) $dataMasterMenu->id,
                'label' => 'Gudang',
                'icon' => 'warehouse',
                'sort_order' => 12,
                'is_active' => true,
            ])->save();
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $viewPermission = Permission::findOrCreate('warehouses.view', 'web');
            $managePermission = Permission::findOrCreate('warehouses.manage', 'web');

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $warehouseMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
                [
                    'menu_id' => (int) $warehouseMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
            );

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $warehouseMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
                [
                    'menu_id' => (int) $warehouseMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
            );
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
            (int) $dataMasterMenu->id,
            (int) $warehouseMenu->id,
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

        $warehouseMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.warehouses.index')
            ->first();

        if (! $warehouseMenu) {
            return;
        }

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->where('menu_id', (int) $warehouseMenu->id)
                ->delete();
        }

        if (Schema::hasTable('menu_permission')) {
            MenuPermission::query()
                ->where('menu_id', (int) $warehouseMenu->id)
                ->delete();
        }

        $warehouseMenu->delete();
    }
};
