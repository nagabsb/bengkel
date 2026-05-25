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

        if ($dataMasterMenu) {
            $dataMasterMenu->forceFill([
                'parent_id' => null,
                'sort_order' => 20,
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
    }

    public function down(): void
    {
        // Keep latest menu order to avoid navigation drift on rollback.
    }
};

