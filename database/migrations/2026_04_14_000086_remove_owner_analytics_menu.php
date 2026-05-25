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

        $analyticsMenuIds = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where(function ($query): void {
                $query
                    ->where('route', 'owner.analytics.index')
                    ->orWhere('label', 'Analitik');
            })
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->values()
            ->all();

        if (count($analyticsMenuIds) === 0) {
            return;
        }

        Menu::query()
            ->whereIn('id', $analyticsMenuIds)
            ->update([
                'is_active' => false,
            ]);

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->whereIn('menu_id', $analyticsMenuIds)
                ->delete();
        }

        if (Schema::hasTable('menu_permission')) {
            MenuPermission::query()
                ->whereIn('menu_id', $analyticsMenuIds)
                ->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $analyticsMenus = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where(function ($query): void {
                $query
                    ->where('route', 'owner.analytics.index')
                    ->orWhere('label', 'Analitik');
            })
            ->get(['id']);

        if ($analyticsMenus->isEmpty()) {
            return;
        }

        $analyticsMenuIds = $analyticsMenus
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->values()
            ->all();

        Menu::query()
            ->whereIn('id', $analyticsMenuIds)
            ->update([
                'is_active' => true,
            ]);

        if (Schema::hasTable('menu_permission') && Schema::hasTable('permissions')) {
            $financeViewPermissionId = (int) Permission::query()
                ->where('name', 'finance.view')
                ->value('id');

            if ($financeViewPermissionId > 0) {
                foreach ($analyticsMenuIds as $menuId) {
                    MenuPermission::query()->updateOrCreate(
                        [
                            'menu_id' => $menuId,
                            'permission_id' => $financeViewPermissionId,
                        ],
                        [
                            'menu_id' => $menuId,
                            'permission_id' => $financeViewPermissionId,
                        ],
                    );
                }
            }
        }

        if (Schema::hasTable('plan_menu') && Schema::hasTable('plans')) {
            $proPlanId = (int) Plan::query()
                ->where('slug', 'pro')
                ->value('id');

            if ($proPlanId > 0) {
                foreach ($analyticsMenuIds as $menuId) {
                    PlanMenu::query()->updateOrCreate(
                        [
                            'plan_id' => $proPlanId,
                            'menu_id' => $menuId,
                        ],
                        [
                            'plan_id' => $proPlanId,
                            'menu_id' => $menuId,
                        ],
                    );
                }
            }
        }
    }
};
