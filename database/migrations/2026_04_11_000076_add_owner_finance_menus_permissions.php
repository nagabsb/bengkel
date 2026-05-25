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

        $financeRootMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('route')
            ->where('label', 'Keuangan')
            ->first();

        if (! $financeRootMenu) {
            $financeRootMenu = Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => null,
                'menu_type' => 'system',
                'label' => 'Keuangan',
                'route' => null,
                'icon' => 'finance',
                'sort_order' => 45,
                'is_active' => true,
            ]);
        } else {
            $financeRootMenu->forceFill([
                'parent_id' => null,
                'label' => 'Keuangan',
                'icon' => 'finance',
                'sort_order' => 45,
                'is_active' => true,
            ])->save();
        }

        $invoiceMenu = $this->upsertChildMenu(
            parentId: (int) $financeRootMenu->id,
            route: 'owner.invoices.index',
            label: 'Invoice',
            icon: 'credit-card',
            sortOrder: 10,
        );

        $paymentMenu = $this->upsertChildMenu(
            parentId: (int) $financeRootMenu->id,
            route: 'owner.invoice-payments.index',
            label: 'Pembayaran',
            icon: 'wallet',
            sortOrder: 20,
        );

        $receivableMenu = $this->upsertChildMenu(
            parentId: (int) $financeRootMenu->id,
            route: 'owner.receivables.index',
            label: 'Piutang',
            icon: 'receipt',
            sortOrder: 30,
        );

        if (Schema::hasTable('permissions') && Schema::hasTable('menu_permission')) {
            $invoiceViewPermission = Permission::findOrCreate('invoices.view', 'web');
            $invoiceManagePermission = Permission::findOrCreate('invoices.manage', 'web');
            $paymentViewPermission = Permission::findOrCreate('invoice_payments.view', 'web');
            $paymentManagePermission = Permission::findOrCreate('invoice_payments.manage', 'web');
            $receivableViewPermission = Permission::findOrCreate('receivables.view', 'web');
            $receivableManagePermission = Permission::findOrCreate('receivables.manage', 'web');

            $this->syncMenuPermission((int) $invoiceMenu->id, (int) $invoiceViewPermission->id);
            $this->syncMenuPermission((int) $invoiceMenu->id, (int) $invoiceManagePermission->id);
            $this->syncMenuPermission((int) $paymentMenu->id, (int) $paymentViewPermission->id);
            $this->syncMenuPermission((int) $paymentMenu->id, (int) $paymentManagePermission->id);
            $this->syncMenuPermission((int) $receivableMenu->id, (int) $receivableViewPermission->id);
            $this->syncMenuPermission((int) $receivableMenu->id, (int) $receivableManagePermission->id);
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
            (int) $financeRootMenu->id,
            (int) $invoiceMenu->id,
            (int) $paymentMenu->id,
            (int) $receivableMenu->id,
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

        $menuRoutes = [
            'owner.invoices.index',
            'owner.invoice-payments.index',
            'owner.receivables.index',
        ];

        $menuIds = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereIn('route', $menuRoutes)
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->values()
            ->all();

        if (count($menuIds) > 0) {
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

            Menu::query()
                ->whereIn('id', $menuIds)
                ->delete();
        }

        $financeRootMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('route')
            ->where('label', 'Keuangan')
            ->first();

        if (! $financeRootMenu) {
            return;
        }

        $hasOtherChildren = Menu::query()
            ->where('parent_id', (int) $financeRootMenu->id)
            ->exists();

        if (! $hasOtherChildren) {
            if (Schema::hasTable('plan_menu')) {
                PlanMenu::query()
                    ->where('menu_id', (int) $financeRootMenu->id)
                    ->delete();
            }

            if (Schema::hasTable('menu_permission')) {
                MenuPermission::query()
                    ->where('menu_id', (int) $financeRootMenu->id)
                    ->delete();
            }

            $financeRootMenu->delete();
        }
    }

    private function upsertChildMenu(
        int $parentId,
        string $route,
        string $label,
        string $icon,
        int $sortOrder,
    ): Menu {
        $menu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', $route)
            ->first();

        if (! $menu) {
            return Menu::query()->create([
                'tenant_id' => null,
                'parent_id' => $parentId,
                'menu_type' => 'system',
                'label' => $label,
                'route' => $route,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);
        }

        $menu->forceFill([
            'parent_id' => $parentId,
            'label' => $label,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ])->save();

        return $menu;
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

