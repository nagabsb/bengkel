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

        $profitLossMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.reports.profit-loss.index')
            ->first();

        if (! $profitLossMenu) {
            $profitLossMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => (int) $reportsRootMenu->id,
                'menu_type' => 'system',
                'label' => 'Laporan Laba Rugi',
                'route' => 'owner.reports.profit-loss.index',
                'icon' => 'finance',
                'sort_order' => 45,
                'is_active' => true,
            ]);
        } else {
            $profitLossMenu->forceFill([
                'parent_id' => (int) $reportsRootMenu->id,
                'label' => 'Laporan Laba Rugi',
                'icon' => 'finance',
                'sort_order' => 45,
                'is_active' => true,
            ])->save();
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $serviceViewPermission = Permission::findOrCreate('service_orders.view', 'web');
            $serviceManagePermission = Permission::findOrCreate('service_orders.manage', 'web');
            $expenseViewPermission = Permission::findOrCreate('expenses.view', 'web');
            $expenseManagePermission = Permission::findOrCreate('expenses.manage', 'web');

            $this->syncMenuPermission((int) $profitLossMenu->id, (int) $serviceViewPermission->id);
            $this->syncMenuPermission((int) $profitLossMenu->id, (int) $serviceManagePermission->id);
            $this->syncMenuPermission((int) $profitLossMenu->id, (int) $expenseViewPermission->id);
            $this->syncMenuPermission((int) $profitLossMenu->id, (int) $expenseManagePermission->id);
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
                    'menu_id' => (int) $profitLossMenu->id,
                ],
                [
                    'plan_id' => $planId,
                    'menu_id' => (int) $profitLossMenu->id,
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $profitLossMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.reports.profit-loss.index')
            ->first();

        if (! $profitLossMenu) {
            return;
        }

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->where('menu_id', (int) $profitLossMenu->id)
                ->delete();
        }

        if (Schema::hasTable('menu_permission')) {
            MenuPermission::query()
                ->where('menu_id', (int) $profitLossMenu->id)
                ->delete();
        }

        $profitLossMenu->delete();
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

