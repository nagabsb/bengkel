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

        $sparePartMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.spareparts.index')
            ->first();

        if (! $sparePartMenu) {
            $sparePartMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => (int) $dataMasterMenu->id,
                'menu_type' => 'system',
                'label' => 'Sparepart',
                'route' => 'owner.spareparts.index',
                'icon' => 'products',
                'sort_order' => 15,
                'is_active' => true,
            ]);
        } else {
            $sparePartMenu->forceFill([
                'parent_id' => (int) $dataMasterMenu->id,
                'label' => 'Sparepart',
                'icon' => 'products',
                'sort_order' => 15,
                'is_active' => true,
            ])->save();
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $viewPermission = Permission::findOrCreate('spareparts.view', 'web');
            $managePermission = Permission::findOrCreate('spareparts.manage', 'web');

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $sparePartMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
                [
                    'menu_id' => (int) $sparePartMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
            );

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $sparePartMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
                [
                    'menu_id' => (int) $sparePartMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
            );

            $legacyPermissionIds = Permission::query()
                ->whereIn('name', [
                    'owner.spareparts.view',
                    'owner.spareparts.manage',
                    'inventory.view',
                    'inventory.manage',
                    'users.manage',
                ])
                ->where('guard_name', 'web')
                ->pluck('id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->filter(fn (int $permissionId): bool => $permissionId > 0)
                ->values()
                ->all();

            if (count($legacyPermissionIds) > 0) {
                MenuPermission::query()
                    ->where('menu_id', (int) $sparePartMenu->id)
                    ->whereIn('permission_id', $legacyPermissionIds)
                    ->delete();
            }
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
            (int) $sparePartMenu->id,
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

        $sparePartMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.spareparts.index')
            ->first();

        if (! $sparePartMenu) {
            return;
        }

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->where('menu_id', (int) $sparePartMenu->id)
                ->delete();
        }

        if (Schema::hasTable('menu_permission')) {
            MenuPermission::query()
                ->where('menu_id', (int) $sparePartMenu->id)
                ->delete();
        }

        $sparePartMenu->delete();
    }
};
