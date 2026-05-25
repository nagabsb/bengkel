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

        Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('parent_id')
            ->whereIn('route', ['owner.products.index', 'owner.users.index'])
            ->update([
                'is_active' => false,
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
            ->whereIn('route', ['owner.products.index', 'owner.users.index'])
            ->update([
                'is_active' => true,
            ]);
    }
};

