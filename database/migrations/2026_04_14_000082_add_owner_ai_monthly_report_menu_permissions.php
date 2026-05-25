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
        }

        $aiMonthlyMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.reports.ai-monthly.index')
            ->first();

        if (! $aiMonthlyMenu) {
            $aiMonthlyMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => (int) $reportsRootMenu->id,
                'menu_type' => 'system',
                'label' => 'Laporan AI Bulanan',
                'route' => 'owner.reports.ai-monthly.index',
                'icon' => 'analytics',
                'sort_order' => 50,
                'is_active' => true,
            ]);
        } else {
            $aiMonthlyMenu->forceFill([
                'parent_id' => (int) $reportsRootMenu->id,
                'label' => 'Laporan AI Bulanan',
                'icon' => 'analytics',
                'sort_order' => 50,
                'is_active' => true,
            ])->save();
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $salesViewPermission = Permission::findOrCreate('service_orders.view', 'web');
            $salesManagePermission = Permission::findOrCreate('service_orders.manage', 'web');

            $this->syncMenuPermission((int) $aiMonthlyMenu->id, (int) $salesViewPermission->id);
            $this->syncMenuPermission((int) $aiMonthlyMenu->id, (int) $salesManagePermission->id);
        }

        if (! Schema::hasTable('plans') || ! Schema::hasTable('plan_menu')) {
            return;
        }

        $planIds = Plan::query()
            ->pluck('id')
            ->map(fn ($planId): int => (int) $planId)
            ->filter(fn (int $planId): bool => $planId > 0)
            ->values();

        foreach ($planIds as $planId) {
            PlanMenu::query()->updateOrCreate(
                [
                    'plan_id' => $planId,
                    'menu_id' => (int) $aiMonthlyMenu->id,
                ],
                [
                    'plan_id' => $planId,
                    'menu_id' => (int) $aiMonthlyMenu->id,
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $aiMonthlyMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.reports.ai-monthly.index')
            ->first();

        if (! $aiMonthlyMenu) {
            return;
        }

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->where('menu_id', (int) $aiMonthlyMenu->id)
                ->delete();
        }

        if (Schema::hasTable('menu_permission')) {
            MenuPermission::query()
                ->where('menu_id', (int) $aiMonthlyMenu->id)
                ->delete();
        }

        $aiMonthlyMenu->delete();
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
