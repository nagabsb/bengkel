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

        $reorderByRoute = static function (string $route, int $sortOrder): void {
            Menu::query()
                ->whereNull('tenant_id')
                ->where('menu_type', 'system')
                ->where('route', $route)
                ->whereNull('parent_id')
                ->update([
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]);
        };

        $reorderByRoute('owner.dashboard', 10);
        $reorderByRoute('owner.workshops.index', 15);
        $reorderByRoute('owner.orders.index', 20);

        Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('parent_id')
            ->whereNull('route')
            ->where('label', 'Data Master')
            ->update([
                'sort_order' => 17,
                'is_active' => true,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('parent_id')
            ->whereNull('route')
            ->where('label', 'Data Master')
            ->update([
                'sort_order' => 20,
            ]);
    }
};

