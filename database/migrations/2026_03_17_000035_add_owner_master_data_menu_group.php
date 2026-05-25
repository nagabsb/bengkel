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
        } else {
            $dataMasterMenu->forceFill([
                'parent_id' => null,
                'menu_type' => 'system',
                'label' => 'Data Master',
                'route' => null,
                'icon' => 'archive-box',
                'sort_order' => 17,
                'is_active' => true,
            ])->save();
        }

        $customerMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.customers.index')
            ->first();

        if ($customerMenu) {
            $customerMenu->forceFill([
                'parent_id' => (int) $dataMasterMenu->id,
                'sort_order' => 20,
                'is_active' => true,
            ])->save();
        }

        $teamMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.users.index')
            ->first();

        if ($teamMenu) {
            $teamMenu->forceFill([
                'parent_id' => (int) $dataMasterMenu->id,
                'sort_order' => 30,
                'label' => 'Tim',
                'is_active' => true,
            ])->save();
        }

        $vehicleMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.vehicles.index')
            ->first();

        if ($vehicleMenu) {
            $vehicleMenu->forceFill([
                'parent_id' => (int) $dataMasterMenu->id,
                'sort_order' => 40,
                'label' => 'Kendaraan',
                'is_active' => true,
            ])->save();
        }

        $supplierMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.suppliers.index')
            ->first();

        if (! $supplierMenu) {
            $supplierMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => (int) $dataMasterMenu->id,
                'menu_type' => 'system',
                'label' => 'Supplier',
                'route' => 'owner.suppliers.index',
                'icon' => 'truck',
                'sort_order' => 10,
                'is_active' => true,
            ]);
        } else {
            $supplierMenu->forceFill([
                'parent_id' => (int) $dataMasterMenu->id,
                'label' => 'Supplier',
                'icon' => 'truck',
                'sort_order' => 10,
                'is_active' => true,
            ])->save();
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $permission = Permission::findOrCreate('users.manage', 'web');

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $supplierMenu->id,
                    'permission_id' => (int) $permission->id,
                ],
                [
                    'menu_id' => (int) $supplierMenu->id,
                    'permission_id' => (int) $permission->id,
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
            (int) $supplierMenu->id,
            (int) ($customerMenu?->id ?? 0),
            (int) ($teamMenu?->id ?? 0),
            (int) ($vehicleMenu?->id ?? 0),
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

        $customerMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.customers.index')
            ->first();

        if ($customerMenu) {
            $customerMenu->forceFill([
                'parent_id' => null,
                'sort_order' => 16,
            ])->save();
        }

        $teamMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.users.index')
            ->first();

        if ($teamMenu) {
            $teamMenu->forceFill([
                'parent_id' => null,
                'sort_order' => 40,
                'label' => 'Tim',
            ])->save();
        }

        $vehicleMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.vehicles.index')
            ->first();

        if ($vehicleMenu) {
            $vehicleMenu->forceFill([
                'parent_id' => null,
                'sort_order' => 18,
                'label' => 'Kendaraan',
            ])->save();
        }

        $supplierMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.suppliers.index')
            ->first();

        if ($supplierMenu) {
            if (Schema::hasTable('plan_menu')) {
                PlanMenu::query()
                    ->where('menu_id', (int) $supplierMenu->id)
                    ->delete();
            }

            if (Schema::hasTable('menu_permission')) {
                MenuPermission::query()
                    ->where('menu_id', (int) $supplierMenu->id)
                    ->delete();
            }

            $supplierMenu->delete();
        }

        $dataMasterMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('route')
            ->where('label', 'Data Master')
            ->first();

        if (! $dataMasterMenu) {
            return;
        }

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->where('menu_id', (int) $dataMasterMenu->id)
                ->delete();
        }

        if (Schema::hasTable('menu_permission')) {
            MenuPermission::query()
                ->where('menu_id', (int) $dataMasterMenu->id)
                ->delete();
        }

        $hasChildren = Menu::query()
            ->where('parent_id', (int) $dataMasterMenu->id)
            ->exists();

        if (! $hasChildren) {
            $dataMasterMenu->delete();
        }
    }
};

