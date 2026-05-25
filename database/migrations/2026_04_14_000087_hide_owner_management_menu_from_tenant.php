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

        $managementMenuIds = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where(function ($query): void {
                $query
                    ->where('route', 'owner.settings?tab=menus')
                    ->orWhere('label', 'Management Menu');
            })
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->values()
            ->all();

        if (count($managementMenuIds) === 0) {
            return;
        }

        Menu::query()
            ->whereIn('id', $managementMenuIds)
            ->update([
                'is_active' => false,
            ]);

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->whereIn('menu_id', $managementMenuIds)
                ->delete();
        }

        if (Schema::hasTable('menu_permission')) {
            MenuPermission::query()
                ->whereIn('menu_id', $managementMenuIds)
                ->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $managementMenus = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where(function ($query): void {
                $query
                    ->where('route', 'owner.settings?tab=menus')
                    ->orWhere('label', 'Management Menu');
            })
            ->get(['id']);

        if ($managementMenus->isEmpty()) {
            return;
        }

        $managementMenuIds = $managementMenus
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->values()
            ->all();

        Menu::query()
            ->whereIn('id', $managementMenuIds)
            ->update([
                'is_active' => true,
            ]);

        if (Schema::hasTable('menu_permission') && Schema::hasTable('permissions')) {
            $usersManagePermissionId = (int) Permission::query()
                ->where('name', 'users.manage')
                ->value('id');

            if ($usersManagePermissionId > 0) {
                foreach ($managementMenuIds as $menuId) {
                    MenuPermission::query()->updateOrCreate(
                        [
                            'menu_id' => $menuId,
                            'permission_id' => $usersManagePermissionId,
                        ],
                        [
                            'menu_id' => $menuId,
                            'permission_id' => $usersManagePermissionId,
                        ],
                    );
                }
            }
        }

        if (Schema::hasTable('plan_menu') && Schema::hasTable('plans')) {
            $planIds = Plan::query()
                ->whereIn('slug', ['growth', 'pro'])
                ->pluck('id')
                ->map(fn ($planId): int => (int) $planId)
                ->filter(fn (int $planId): bool => $planId > 0)
                ->values()
                ->all();

            foreach ($planIds as $planId) {
                foreach ($managementMenuIds as $menuId) {
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
    }
};
