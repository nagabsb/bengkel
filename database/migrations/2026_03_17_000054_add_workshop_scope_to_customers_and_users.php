<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->addCustomersWorkshopColumn();
        $this->addUsersWorkshopColumn();
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'workshop_id')) {
            $this->safeSchema('customers', fn (Blueprint $table): bool => $this->safeForeignDrop($table, 'customers_workshop_id_foreign'));
            $this->safeSchema('customers', fn (Blueprint $table): bool => $this->safeIndexDrop($table, 'customers_tenant_workshop_created_idx'));
            $this->safeSchema('customers', fn (Blueprint $table): bool => $this->safeIndexDrop($table, 'customers_tenant_workshop_name_idx'));
            $this->safeSchema('customers', fn (Blueprint $table): bool => $this->safeColumnDrop($table, 'workshop_id'));
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'workshop_id')) {
            $this->safeSchema('users', fn (Blueprint $table): bool => $this->safeForeignDrop($table, 'users_workshop_id_foreign'));
            $this->safeSchema('users', fn (Blueprint $table): bool => $this->safeIndexDrop($table, 'users_tenant_workshop_created_idx'));
            $this->safeSchema('users', fn (Blueprint $table): bool => $this->safeColumnDrop($table, 'workshop_id'));
        }
    }

    private function addCustomersWorkshopColumn(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        if (! Schema::hasColumn('customers', 'workshop_id')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->char('workshop_id', 26)->nullable()->after('tenant_id');
            });
        }

        DB::table('customers')
            ->whereNull('workshop_id')
            ->whereNotNull('tenant_id')
            ->update([
                'workshop_id' => DB::raw('tenant_id'),
            ]);

        if (Schema::hasTable('workshops')) {
            DB::table('customers')
                ->whereNotNull('workshop_id')
                ->whereNotIn('workshop_id', function ($query): void {
                    $query->select('id')->from('workshops');
                })
                ->update(['workshop_id' => null]);
        }

        $this->safeSchema('customers', fn (Blueprint $table): bool => $this->safeIndexAdd($table, ['tenant_id', 'workshop_id', 'created_at'], 'customers_tenant_workshop_created_idx'));
        $this->safeSchema('customers', fn (Blueprint $table): bool => $this->safeIndexAdd($table, ['tenant_id', 'workshop_id', 'name'], 'customers_tenant_workshop_name_idx'));

        if (Schema::hasTable('workshops')) {
            $this->safeSchema('customers', fn (Blueprint $table): bool => $this->safeForeignAdd($table, 'workshop_id', 'customers_workshop_id_foreign', 'workshops'));
        }
    }

    private function addUsersWorkshopColumn(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'workshop_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->char('workshop_id', 26)->nullable()->after('tenant_id');
            });
        }

        DB::table('users')
            ->whereNull('workshop_id')
            ->whereNotNull('tenant_id')
            ->update([
                'workshop_id' => DB::raw('tenant_id'),
            ]);

        if (Schema::hasTable('workshops')) {
            DB::table('users')
                ->whereNotNull('workshop_id')
                ->whereNotIn('workshop_id', function ($query): void {
                    $query->select('id')->from('workshops');
                })
                ->update(['workshop_id' => null]);
        }

        $this->safeSchema('users', fn (Blueprint $table): bool => $this->safeIndexAdd($table, ['tenant_id', 'workshop_id', 'created_at'], 'users_tenant_workshop_created_idx'));

        if (Schema::hasTable('workshops')) {
            $this->safeSchema('users', fn (Blueprint $table): bool => $this->safeForeignAdd($table, 'workshop_id', 'users_workshop_id_foreign', 'workshops'));
        }
    }

    private function safeSchema(string $table, \Closure $callback): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($callback): void {
                $callback($blueprint);
            });
        } catch (\Throwable) {
            // ignore rollback mismatch
        }
    }

    private function safeForeignDrop(Blueprint $table, string $foreignKey): bool
    {
        $table->dropForeign($foreignKey);

        return true;
    }

    private function safeForeignAdd(Blueprint $table, string $column, string $foreignKey, string $referencesTable): bool
    {
        $table->foreign($column, $foreignKey)
            ->references('id')
            ->on($referencesTable)
            ->nullOnDelete();

        return true;
    }

    private function safeIndexDrop(Blueprint $table, string $indexName): bool
    {
        $table->dropIndex($indexName);

        return true;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function safeIndexAdd(Blueprint $table, array $columns, string $indexName): bool
    {
        $table->index($columns, $indexName);

        return true;
    }

    private function safeColumnDrop(Blueprint $table, string $columnName): bool
    {
        $table->dropColumn($columnName);

        return true;
    }
};
