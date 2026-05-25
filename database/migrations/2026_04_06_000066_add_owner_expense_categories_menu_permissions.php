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

        $expenseCategoryMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.expense-categories.index')
            ->first();

        if (! $expenseCategoryMenu) {
            $expenseCategoryMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => (int) $dataMasterMenu->id,
                'menu_type' => 'system',
                'label' => 'Kategori Pengeluaran',
                'route' => 'owner.expense-categories.index',
                'icon' => 'tag',
                'sort_order' => 55,
                'is_active' => true,
            ]);
        } else {
            $expenseCategoryMenu->forceFill([
                'parent_id' => (int) $dataMasterMenu->id,
                'label' => 'Kategori Pengeluaran',
                'icon' => 'tag',
                'sort_order' => 55,
                'is_active' => true,
            ])->save();
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $viewPermission = Permission::findOrCreate('expense_categories.view', 'web');
            $managePermission = Permission::findOrCreate('expense_categories.manage', 'web');

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $expenseCategoryMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
                [
                    'menu_id' => (int) $expenseCategoryMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
            );

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $expenseCategoryMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
                [
                    'menu_id' => (int) $expenseCategoryMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
            );

            $legacyPermissionIds = Permission::query()
                ->whereIn('name', [
                    'owner.expense_categories.view',
                    'owner.expense_categories.manage',
                ])
                ->where('guard_name', 'web')
                ->pluck('id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->filter(fn (int $permissionId): bool => $permissionId > 0)
                ->values()
                ->all();

            if (count($legacyPermissionIds) > 0) {
                MenuPermission::query()
                    ->where('menu_id', (int) $expenseCategoryMenu->id)
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
            (int) $expenseCategoryMenu->id,
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

        $expenseCategoryMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.expense-categories.index')
            ->first();

        if (! $expenseCategoryMenu) {
            return;
        }

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->where('menu_id', (int) $expenseCategoryMenu->id)
                ->delete();
        }

        if (Schema::hasTable('menu_permission')) {
            MenuPermission::query()
                ->where('menu_id', (int) $expenseCategoryMenu->id)
                ->delete();
        }

        $expenseCategoryMenu->delete();
    }
};
