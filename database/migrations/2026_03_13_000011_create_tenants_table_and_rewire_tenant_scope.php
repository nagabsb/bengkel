<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->createTenantsTable();
        $this->ensureWorkshopTenantColumn();
        $this->backfillTenantsFromWorkshops();
        $this->attachWorkshopTenantForeignKey();
        $this->rebindTenantForeignKeys('tenants');
    }

    public function down(): void
    {
        $this->rebindTenantForeignKeys('workshops');
        $this->dropWorkshopTenantForeignKey();
        $this->dropWorkshopTenantColumn();

        if (Schema::hasTable('tenants')) {
            Schema::dropIfExists('tenants');
        }
    }

    private function createTenantsTable(): void
    {
        if (Schema::hasTable('tenants')) {
            return;
        }

        Schema::create('tenants', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('name', 150);
            $table->string('code', 20)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function ensureWorkshopTenantColumn(): void
    {
        if (! Schema::hasTable('workshops') || Schema::hasColumn('workshops', 'tenant_id')) {
            return;
        }

        $this->safeSchema('workshops', function (Blueprint $table): void {
            $table->char('tenant_id', 26)->nullable()->after('id');
            $table->index('tenant_id', 'workshops_tenant_id_index');
        });
    }

    private function backfillTenantsFromWorkshops(): void
    {
        if (! Schema::hasTable('workshops') || ! Schema::hasTable('tenants')) {
            return;
        }

        $tenantModel = $this->tenantModel();
        $workshopModel = $this->workshopModel();

        $workshopModel->newQuery()
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'name', 'code', 'is_active', 'created_at', 'updated_at'])
            ->each(function (Model $workshop) use ($tenantModel, $workshopModel): void {
                $workshopId = trim((string) $workshop->getAttribute('id'));
                if ($workshopId === '') {
                    return;
                }

                $tenantId = trim((string) $workshop->getAttribute('tenant_id'));
                if ($tenantId === '') {
                    $tenantId = $workshopId;
                }

                $name = trim((string) $workshop->getAttribute('name'));
                $code = trim((string) $workshop->getAttribute('code'));
                $isActive = (bool) $workshop->getAttribute('is_active');
                $createdAt = $workshop->getAttribute('created_at') ?? now();

                $tenantModel->newQuery()->updateOrCreate(
                    ['id' => $tenantId],
                    [
                        'name' => $name !== '' ? $name : sprintf('Tenant %s', $workshopId),
                        'code' => $this->normalizeTenantCode($code, $workshopId),
                        'is_active' => $isActive,
                        'created_at' => $createdAt,
                        'updated_at' => now(),
                    ],
                );

                if (trim((string) $workshop->getAttribute('tenant_id')) === $tenantId) {
                    return;
                }

                $workshopModel->newQuery()
                    ->where('id', $workshopId)
                    ->update([
                        'tenant_id' => $tenantId,
                        'updated_at' => now(),
                    ]);
            });
    }

    private function attachWorkshopTenantForeignKey(): void
    {
        if (! Schema::hasTable('workshops') || ! Schema::hasColumn('workshops', 'tenant_id')) {
            return;
        }

        $this->safeSchema('workshops', fn (Blueprint $table) => $table->dropForeign(['tenant_id']));
        $this->safeSchema('workshops', fn (Blueprint $table) => $table
            ->foreign('tenant_id', 'workshops_tenant_id_foreign')
            ->references('id')
            ->on('tenants')
            ->cascadeOnDelete());
    }

    private function dropWorkshopTenantForeignKey(): void
    {
        if (! Schema::hasTable('workshops') || ! Schema::hasColumn('workshops', 'tenant_id')) {
            return;
        }

        $this->safeSchema('workshops', fn (Blueprint $table) => $table->dropForeign(['tenant_id']));
        $this->safeSchema('workshops', fn (Blueprint $table) => $table->dropForeign('workshops_tenant_id_foreign'));
    }

    private function dropWorkshopTenantColumn(): void
    {
        if (! Schema::hasTable('workshops') || ! Schema::hasColumn('workshops', 'tenant_id')) {
            return;
        }

        $this->safeSchema('workshops', fn (Blueprint $table) => $table->dropIndex('workshops_tenant_id_index'));
        $this->safeSchema('workshops', fn (Blueprint $table) => $table->dropColumn('tenant_id'));
    }

    private function rebindTenantForeignKeys(string $targetTable): void
    {
        if (! in_array($targetTable, ['tenants', 'workshops'], true)) {
            return;
        }

        $this->rebindTenantForeignKey('users', 'tenant_id', $targetTable, true, false);
        $this->rebindTenantForeignKey('roles', 'tenant_id', $targetTable, true, true);
        $this->rebindTenantForeignKey('workshop_subscriptions', 'tenant_id', $targetTable, false, true);
        $this->rebindTenantForeignKey('menus', 'tenant_id', $targetTable, true, true);
        $this->rebindTenantForeignKey('workshop_menu_overrides', 'tenant_id', $targetTable, false, true);
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

        $this->safeSchema($table, fn (Blueprint $schema) => $schema->dropForeign([$column]));

        $this->safeSchema($table, function (Blueprint $schema) use ($column, $targetTable, $nullable, $cascadeOnDelete): void {
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

    private function tenantModel(): Model
    {
        return new class extends Model {
            protected $table = 'tenants';

            public $incrementing = false;

            protected $keyType = 'string';

            protected $guarded = [];
        };
    }

    private function workshopModel(): Model
    {
        return new class extends Model {
            protected $table = 'workshops';

            public $incrementing = false;

            protected $keyType = 'string';

            protected $guarded = [];
        };
    }

    private function normalizeTenantCode(string $code, string $workshopId): string
    {
        $normalized = trim($code);
        if ($normalized !== '') {
            return Str::upper(substr($normalized, 0, 20));
        }

        return Str::upper(substr('TEN-' . $workshopId, 0, 20));
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