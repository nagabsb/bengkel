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

        $permissions = [
            Permission::findOrCreate('invoices.view', 'web'),
            Permission::findOrCreate('invoices.manage', 'web'),
            Permission::findOrCreate('invoice_payments.view', 'web'),
            Permission::findOrCreate('invoice_payments.manage', 'web'),
            Permission::findOrCreate('receivables.view', 'web'),
            Permission::findOrCreate('receivables.manage', 'web'),
        ];

        $managementRoles = Role::query()
            ->whereHas('permissions', function ($query): void {
                $query->whereIn('name', ['users.manage', 'customers.manage'])
                    ->where('guard_name', 'web');
            })
            ->get();

        foreach ($managementRoles as $role) {
            $role->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        // Keep granted permissions to avoid accidental access regression on rollback.
    }
};

