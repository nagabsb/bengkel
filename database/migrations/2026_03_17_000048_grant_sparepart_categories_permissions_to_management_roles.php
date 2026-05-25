<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $viewPermission = Permission::findOrCreate('sparepart_categories.view', 'web');
        $managePermission = Permission::findOrCreate('sparepart_categories.manage', 'web');

        $managementRoles = Role::query()
            ->whereHas('permissions', function ($query): void {
                $query->whereIn('name', ['users.manage', 'customers.manage'])
                    ->where('guard_name', 'web');
            })
            ->get();

        foreach ($managementRoles as $role) {
            $role->givePermissionTo([$viewPermission, $managePermission]);
        }
    }

    public function down(): void
    {
        // Keep granted permissions to avoid accidental access regression on rollback.
    }
};

