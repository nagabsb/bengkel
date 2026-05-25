<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        $this->rebindTenantForeignKey('users', 'tenant_id', 'tenants', true, false);
        $this->rebindTenantForeignKey('roles', 'tenant_id', 'tenants', true, true);
        $this->rebindTenantForeignKey('workshop_subscriptions', 'tenant_id', 'tenants', false, true);
        $this->rebindTenantForeignKey('menus', 'tenant_id', 'tenants', true, true);
        $this->rebindTenantForeignKey('workshop_menu_overrides', 'tenant_id', 'tenants', false, true);
        $this->rebindTenantForeignKey('workshops', 'tenant_id', 'tenants', true, true);
    }

    public function down(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        $this->rebindTenantForeignKey('users', 'tenant_id', 'workshops', true, false);
        $this->rebindTenantForeignKey('roles', 'tenant_id', 'workshops', true, true);
        $this->rebindTenantForeignKey('workshop_subscriptions', 'tenant_id', 'workshops', false, true);
        $this->rebindTenantForeignKey('menus', 'tenant_id', 'workshops', true, true);
        $this->rebindTenantForeignKey('workshop_menu_overrides', 'tenant_id', 'workshops', false, true);
        $this->rebindTenantForeignKey('workshops', 'tenant_id', 'tenants', true, true);
    }

    private function rebindTenantForeignKey(
        string $table,
        string $column,
        string $targetTable,
        bool $nullable,
        bool $cascadeOnDelete,
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($targetTable)) {
            return;
        }

        $legacyForeignNames = [
            "{$table}_{$column}_foreign",
            "{$table}_workshop_id_foreign",
            "{$table}_tenant_id_foreign",
        ];

        $this->safeSchema($table, fn (Blueprint $schema) => $schema->dropForeign([$column]));
        foreach ($legacyForeignNames as $foreignName) {
            $this->safeSchema($table, fn (Blueprint $schema) => $schema->dropForeign($foreignName));
        }

        $this->safeSchema($table, function (Blueprint $schema) use ($table, $column, $targetTable, $nullable, $cascadeOnDelete): void {
            $foreign = $schema
                ->foreign($column, "{$table}_{$column}_foreign")
                ->references('id')
                ->on($targetTable);

            if ($nullable && ! $cascadeOnDelete) {
                $foreign->nullOnDelete();
                return;
            }

            if ($cascadeOnDelete) {
                $foreign->cascadeOnDelete();
            }
        });
    }

    private function isSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }

    private function safeSchema(string $table, callable $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Throwable) {
            // Keep migration idempotent across local schemas.
        }
    }
};