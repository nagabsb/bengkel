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

        $settingsMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('route')
            ->where('label', 'Pengaturan')
            ->first();

        if (! $settingsMenu) {
            $settingsMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => null,
                'menu_type' => 'system',
                'label' => 'Pengaturan',
                'route' => null,
                'icon' => 'settings',
                'sort_order' => 90,
                'is_active' => true,
            ]);
        }

        $notaMenu = Menu::query()->updateOrCreate(
            [
                'tenant_id' => null,
                'menu_type' => 'system',
                'route' => 'owner.settings?tab=nota',
            ],
            [
                'parent_id' => (int) $settingsMenu->id,
                'label' => 'Nota',
                'icon' => 'printer',
                'sort_order' => 15,
                'is_active' => true,
            ],
        );

        if (Schema::hasTable('menu_permission') && Schema::hasTable('permissions')) {
            $usersManagePermission = Permission::findOrCreate('users.manage', 'web');

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $notaMenu->id,
                    'permission_id' => (int) $usersManagePermission->id,
                ],
                [
                    'menu_id' => (int) $notaMenu->id,
                    'permission_id' => (int) $usersManagePermission->id,
                ],
            );
        }

        if (Schema::hasTable('plan_menu') && Schema::hasTable('plans')) {
            $planIds = Plan::query()
                ->whereIn('slug', ['starter', 'growth', 'pro'])
                ->pluck('id')
                ->map(fn ($planId): int => (int) $planId)
                ->filter(fn (int $planId): bool => $planId > 0)
                ->values()
                ->all();

            foreach ($planIds as $planId) {
                PlanMenu::query()->updateOrCreate(
                    [
                        'plan_id' => $planId,
                        'menu_id' => (int) $notaMenu->id,
                    ],
                    [
                        'plan_id' => $planId,
                        'menu_id' => (int) $notaMenu->id,
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

        $notaMenuIds = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.settings?tab=nota')
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->values()
            ->all();

        if (count($notaMenuIds) === 0) {
            return;
        }

        Menu::query()
            ->whereIn('id', $notaMenuIds)
            ->update([
                'is_active' => false,
            ]);

        if (Schema::hasTable('plan_menu')) {
            PlanMenu::query()
                ->whereIn('menu_id', $notaMenuIds)
                ->delete();
        }

        if (Schema::hasTable('menu_permission')) {
            MenuPermission::query()
                ->whereIn('menu_id', $notaMenuIds)
                ->delete();
        }
    }
};
