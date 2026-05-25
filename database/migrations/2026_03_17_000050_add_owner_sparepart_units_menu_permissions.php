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

        $unitMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.sparepart-units.index')
            ->first();

        if (! $unitMenu) {
            $unitMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => (int) $dataMasterMenu->id,
                'menu_type' => 'system',
                'label' => 'Satuan Sparepart',
                'route' => 'owner.sparepart-units.index',
                'icon' => 'clipboard',
                'sort_order' => 17,
                'is_active' => true,
            ]);
        } else {
            $unitMenu->forceFill([
                'parent_id' => (int) $dataMasterMenu->id,
                'label' => 'Satuan Sparepart',
                'icon' => 'clipboard',
                'sort_order' => 17,
                'is_active' => true,
            ])->save();
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $viewPermission = Permission::findOrCreate('sparepart_units.view', 'web');
            $managePermission = Permission::findOrCreate('sparepart_units.manage', 'web');

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $unitMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
                [
                    'menu_id' => (int) $unitMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
            );

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $unitMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
                [
                    'menu_id' => (int) $unitMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
            );

            $legacyPermissionId = Permission::query()
                ->where('name', 'users.manage')
                ->where('guard_name', 'web')
                ->value('id');

            if (is_numeric($legacyPermissionId)) {
                MenuPermission::query()
                    ->where('menu_id', (int) $unitMenu->id)
                    ->where('permission_id', (int) $legacyPermissionId)
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
            (int) $unitMenu->id,
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

        $unitMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.sparepart-units.index')
            ->first();

        if (! $unitMenu) {
            return;
        }

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->where('menu_id', (int) $unitMenu->id)
                ->delete();
        }

        if (Schema::hasTable('menu_permission') && Schema::hasTable('permissions')) {
            $permissionIds = Permission::query()
                ->whereIn('name', ['sparepart_units.view', 'sparepart_units.manage'])
                ->where('guard_name', 'web')
                ->pluck('id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->filter(fn (int $permissionId): bool => $permissionId > 0)
                ->values();

            if ($permissionIds->isNotEmpty()) {
                MenuPermission::query()
                    ->where('menu_id', (int) $unitMenu->id)
                    ->whereIn('permission_id', $permissionIds->all())
                    ->delete();
            }
        }

        $unitMenu->delete();
    }
};

