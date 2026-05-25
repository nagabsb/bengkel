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

        $supplierMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.suppliers.index')
            ->first();

        if (! $supplierMenu) {
            return;
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $viewPermission = Permission::findOrCreate('suppliers.view', 'web');
            $managePermission = Permission::findOrCreate('suppliers.manage', 'web');

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $supplierMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
                [
                    'menu_id' => (int) $supplierMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
            );

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $supplierMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
                [
                    'menu_id' => (int) $supplierMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
            );

            $legacyPermissionId = Permission::query()
                ->where('name', 'users.manage')
                ->where('guard_name', 'web')
                ->value('id');

            if (is_numeric($legacyPermissionId)) {
                MenuPermission::query()
                    ->where('menu_id', (int) $supplierMenu->id)
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

        foreach ($planIds as $planId) {
            PlanMenu::query()->updateOrCreate(
                [
                    'plan_id' => $planId,
                    'menu_id' => (int) $supplierMenu->id,
                ],
                [
                    'plan_id' => $planId,
                    'menu_id' => (int) $supplierMenu->id,
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $supplierMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.suppliers.index')
            ->first();

        if (! $supplierMenu) {
            return;
        }

        if (Schema::hasTable('menu_permission') && Schema::hasTable('permissions')) {
            $permissionIds = Permission::query()
                ->whereIn('name', ['suppliers.view', 'suppliers.manage'])
                ->where('guard_name', 'web')
                ->pluck('id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->filter(fn (int $permissionId): bool => $permissionId > 0)
                ->values();

            if ($permissionIds->isNotEmpty()) {
                MenuPermission::query()
                    ->where('menu_id', (int) $supplierMenu->id)
                    ->whereIn('permission_id', $permissionIds->all())
                    ->delete();
            }
        }
    }
};

