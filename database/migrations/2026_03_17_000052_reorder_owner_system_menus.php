<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

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
                'sort_order' => 20,
                'is_active' => true,
            ]);
        } else {
            $dataMasterMenu->forceFill([
                'parent_id' => null,
                'label' => 'Data Master',
                'icon' => 'archive-box',
                'sort_order' => 20,
                'is_active' => true,
            ])->save();
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
        } else {
            $settingsMenu->forceFill([
                'parent_id' => null,
                'label' => 'Pengaturan',
                'icon' => 'settings',
                'sort_order' => 90,
                'is_active' => true,
            ])->save();
        }

        $reorderByRoute = static function (
            string $route,
            ?int $parentId,
            int $sortOrder,
            ?string $label = null,
            ?string $icon = null,
        ): void {
            $menu = Menu::query()
                ->whereNull('tenant_id')
                ->where('menu_type', 'system')
                ->where('route', $route)
                ->first();

            if (! $menu) {
                return;
            }

            $payload = [
                'parent_id' => $parentId,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ];

            if ($label !== null) {
                $payload['label'] = $label;
            }

            if ($icon !== null) {
                $payload['icon'] = $icon;
            }

            $menu->forceFill($payload)->save();
        };

        $reorderByRoute('owner.dashboard', null, 10, 'Dashboard', 'dashboard');
        $reorderByRoute('owner.workshops.index', null, 30, 'Bengkel', 'building');
        $reorderByRoute('owner.orders.index', null, 40, 'Servis', 'services');

        $reorderByRoute('owner.customers.index', (int) $dataMasterMenu->id, 10, 'Pelanggan', 'customers');
        $reorderByRoute('owner.vehicles.index', (int) $dataMasterMenu->id, 20, 'Kendaraan', 'car');
        $reorderByRoute('owner.users.index', (int) $dataMasterMenu->id, 30, 'Tim', 'users');
        $reorderByRoute('owner.suppliers.index', (int) $dataMasterMenu->id, 40, 'Supplier', 'truck');
        $reorderByRoute('owner.warehouses.index', (int) $dataMasterMenu->id, 50, 'Gudang', 'inventory');
        $reorderByRoute('owner.sparepart-categories.index', (int) $dataMasterMenu->id, 60, 'Kategori Sparepart', 'tag');
        $reorderByRoute('owner.sparepart-units.index', (int) $dataMasterMenu->id, 70, 'Satuan Sparepart', 'clipboard');
        $reorderByRoute('owner.spareparts.index', (int) $dataMasterMenu->id, 80, 'Sparepart', 'products');

        $reorderByRoute('owner.settings?tab=permissions', (int) $settingsMenu->id, 10, 'Permission', 'settings');
        $reorderByRoute('owner.settings?tab=menus', (int) $settingsMenu->id, 20, 'Management Menu', 'settings');
    }

    public function down(): void
    {
        // Keep reordered menu positions to avoid navigation drift on rollback.
    }
};
