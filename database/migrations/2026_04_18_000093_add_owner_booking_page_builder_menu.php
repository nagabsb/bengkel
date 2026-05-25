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

        $builderMenu = Menu::query()->updateOrCreate(
            [
                'tenant_id' => null,
                'menu_type' => 'system',
                'route' => 'owner.bookings.builder',
            ],
            [
                'parent_id' => null,
                'label' => 'Page Builder',
                'icon' => 'palette',
                'sort_order' => 24,
                'is_active' => true,
            ],
        );

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $permission = Permission::findOrCreate('bookings.manage', 'web');

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $builderMenu->id,
                    'permission_id' => (int) $permission->id,
                ],
                [
                    'menu_id' => (int) $builderMenu->id,
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
            ->values()
            ->all();

        foreach ($planIds as $planId) {
            PlanMenu::query()->updateOrCreate(
                [
                    'plan_id' => $planId,
                    'menu_id' => (int) $builderMenu->id,
                ],
                [
                    'plan_id' => $planId,
                    'menu_id' => (int) $builderMenu->id,
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $menuIds = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.bookings.builder')
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->values()
            ->all();

        if (count($menuIds) === 0) {
            return;
        }

        Menu::query()
            ->whereIn('id', $menuIds)
            ->update([
                'is_active' => false,
            ]);

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
    }
};
