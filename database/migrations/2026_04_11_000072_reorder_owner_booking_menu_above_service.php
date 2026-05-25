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

        $serviceMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.orders.index')
            ->first();

        $bookingMenu = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->where('route', 'owner.bookings.index')
            ->first();

        if (! $bookingMenu) {
            return;
        }

        $targetSortOrder = 39;
        if ($serviceMenu) {
            $serviceSortOrder = (int) ($serviceMenu->sort_order ?? 40);
            $targetSortOrder = $serviceSortOrder > 0
                ? $serviceSortOrder - 1
                : 1;
        }

        $bookingMenu->forceFill([
            'parent_id' => null,
            'sort_order' => $targetSortOrder,
            'is_active' => true,
        ])->save();
    }

    public function down(): void
    {
        // Keep latest menu order to avoid navigation drift on rollback.
    }
};

