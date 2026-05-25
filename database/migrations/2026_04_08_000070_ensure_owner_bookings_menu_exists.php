<?php

use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\Plan;
use App\Models\PlanMenu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $bookingMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.bookings.index')
            ->first();

        if (! $bookingMenu) {
            $bookingMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => null,
                'menu_type' => 'system',
                'label' => 'Booking',
                'route' => 'owner.bookings.index',
                'icon' => 'calendar',
                'sort_order' => 23,
                'is_active' => true,
            ]);
        } else {
            $bookingMenu->forceFill([
                'parent_id' => null,
                'menu_type' => 'system',
                'label' => 'Booking',
                'icon' => 'calendar',
                'sort_order' => 23,
                'is_active' => true,
            ])->save();
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $viewPermission = Permission::findOrCreate('bookings.view', 'web');
            $managePermission = Permission::findOrCreate('bookings.manage', 'web');

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $bookingMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
                [
                    'menu_id' => (int) $bookingMenu->id,
                    'permission_id' => (int) $viewPermission->id,
                ],
            );

            MenuPermission::query()->updateOrCreate(
                [
                    'menu_id' => (int) $bookingMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
                [
                    'menu_id' => (int) $bookingMenu->id,
                    'permission_id' => (int) $managePermission->id,
                ],
            );

            $legacyPermissionIds = Permission::query()
                ->whereIn('name', [
                    'owner.bookings.view',
                    'owner.bookings.manage',
                ])
                ->where('guard_name', 'web')
                ->pluck('id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->filter(fn (int $permissionId): bool => $permissionId > 0)
                ->values()
                ->all();

            if (count($legacyPermissionIds) > 0) {
                MenuPermission::query()
                    ->where('menu_id', (int) $bookingMenu->id)
                    ->whereIn('permission_id', $legacyPermissionIds)
                    ->delete();
            }
        }

        if (Schema::hasTable('plans') && Schema::hasTable('plan_menu')) {
            $planIds = Plan::query()
                ->pluck('id')
                ->map(fn ($planId): int => (int) $planId)
                ->filter(fn (int $planId): bool => $planId > 0)
                ->values();

            foreach ($planIds as $planId) {
                PlanMenu::query()->updateOrCreate(
                    [
                        'plan_id' => $planId,
                        'menu_id' => (int) $bookingMenu->id,
                    ],
                    [
                        'plan_id' => $planId,
                        'menu_id' => (int) $bookingMenu->id,
                    ],
                );
            }
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            $bookingViewPermission = Permission::findOrCreate('bookings.view', 'web');
            $bookingManagePermission = Permission::findOrCreate('bookings.manage', 'web');

            $managementRoles = Role::query()
                ->whereHas('permissions', function ($query): void {
                    $query->whereIn('name', ['users.manage', 'customers.manage'])
                        ->where('guard_name', 'web');
                })
                ->get();

            foreach ($managementRoles as $role) {
                $role->givePermissionTo([$bookingViewPermission, $bookingManagePermission]);
            }
        }
    }

    public function down(): void
    {
        // Keep booking menu and permissions to avoid access regressions.
    }
};
