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
        $this->renameUsersColumn('workshop_id', 'tenant_id');
        $this->renameRolesColumn('workshop_id', 'tenant_id');
        $this->renameSubscriptionsColumn('workshop_id', 'tenant_id');
        $this->renameMenusColumn('workshop_id', 'tenant_id');
        $this->renameMenuOverridesColumn('workshop_id', 'tenant_id');
    }

    public function down(): void
    {
        $this->renameUsersColumn('tenant_id', 'workshop_id');
        $this->renameRolesColumn('tenant_id', 'workshop_id');
        $this->renameSubscriptionsColumn('tenant_id', 'workshop_id');
        $this->renameMenusColumn('tenant_id', 'workshop_id');
        $this->renameMenuOverridesColumn('tenant_id', 'workshop_id');
    }

    private function renameUsersColumn(string $from, string $to): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $this->renameColumn('users', $from, $to);
        $this->backfillColumn('users', $from, $to);

        $this->safeSchema('users', fn (Blueprint $table) => $table->dropForeign([$to]));
        $this->safeSchema('users', fn (Blueprint $table) => $table->dropIndex("users_{$from}_index"));
        $this->safeSchema('users', fn (Blueprint $table) => $table->dropIndex("users_{$to}_index"));

        $this->safeSchema('users', fn (Blueprint $table) => $table->index($to, "users_{$to}_index"));
        $this->safeSchema('users', fn (Blueprint $table) => $table
            ->foreign($to, "users_{$to}_foreign")
            ->references('id')
            ->on('workshops')
            ->nullOnDelete());
    }

    private function renameRolesColumn(string $from, string $to): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->safeSchema('roles', fn (Blueprint $table) => $table->dropForeign([$from]));
        $this->safeSchema('roles', fn (Blueprint $table) => $table->dropForeign([$to]));
        $this->safeSchema('roles', fn (Blueprint $table) => $table->dropUnique("roles_name_guard_{$from}_unique"));
        $this->safeSchema('roles', fn (Blueprint $table) => $table->dropUnique("roles_name_guard_{$to}_unique"));
        $this->safeSchema('roles', fn (Blueprint $table) => $table->dropIndex("roles_{$from}_index"));
        $this->safeSchema('roles', fn (Blueprint $table) => $table->dropIndex("roles_{$to}_index"));

        $this->renameColumn('roles', $from, $to);
        $this->backfillColumn('roles', $from, $to);

        $this->safeSchema('roles', fn (Blueprint $table) => $table->index($to));
        $this->safeSchema('roles', fn (Blueprint $table) => $table->unique(['name', 'guard_name', $to], "roles_name_guard_{$to}_unique"));
        $this->safeSchema('roles', fn (Blueprint $table) => $table
            ->foreign($to)
            ->references('id')
            ->on('workshops')
            ->cascadeOnDelete());
    }

    private function renameSubscriptionsColumn(string $from, string $to): void
    {
        if (! Schema::hasTable('workshop_subscriptions')) {
            return;
        }

        $this->safeSchema('workshop_subscriptions', fn (Blueprint $table) => $table->dropForeign([$from]));
        $this->safeSchema('workshop_subscriptions', fn (Blueprint $table) => $table->dropForeign([$to]));
        $this->safeSchema('workshop_subscriptions', fn (Blueprint $table) => $table->dropIndex('workshop_subscriptions_workshop_status_index'));
        $this->safeSchema('workshop_subscriptions', fn (Blueprint $table) => $table->dropIndex('workshop_subscriptions_tenant_status_index'));
        $this->safeSchema('workshop_subscriptions', fn (Blueprint $table) => $table->dropIndex("workshop_subscriptions_{$from}_status_index"));
        $this->safeSchema('workshop_subscriptions', fn (Blueprint $table) => $table->dropIndex("workshop_subscriptions_{$to}_status_index"));

        $this->renameColumn('workshop_subscriptions', $from, $to);
        $this->backfillColumn('workshop_subscriptions', $from, $to);

        $this->safeSchema('workshop_subscriptions', fn (Blueprint $table) => $table->index([$to, 'status'], $to === 'tenant_id'
            ? 'workshop_subscriptions_tenant_status_index'
            : 'workshop_subscriptions_workshop_status_index'));
        $this->safeSchema('workshop_subscriptions', fn (Blueprint $table) => $table
            ->foreign($to)
            ->references('id')
            ->on('workshops')
            ->cascadeOnDelete());
    }

    private function renameMenusColumn(string $from, string $to): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $this->safeSchema('menus', fn (Blueprint $table) => $table->dropForeign([$from]));
        $this->safeSchema('menus', fn (Blueprint $table) => $table->dropForeign([$to]));
        $this->safeSchema('menus', fn (Blueprint $table) => $table->dropIndex('menus_workshop_index'));
        $this->safeSchema('menus', fn (Blueprint $table) => $table->dropIndex('menus_tenant_index'));
        $this->safeSchema('menus', fn (Blueprint $table) => $table->dropIndex("menus_{$from}_index"));
        $this->safeSchema('menus', fn (Blueprint $table) => $table->dropIndex("menus_{$to}_index"));

        $this->renameColumn('menus', $from, $to);
        $this->backfillColumn('menus', $from, $to);

        $this->safeSchema('menus', fn (Blueprint $table) => $table->index($to, $to === 'tenant_id' ? 'menus_tenant_index' : 'menus_workshop_index'));
        $this->safeSchema('menus', fn (Blueprint $table) => $table
            ->foreign($to, "menus_{$to}_foreign")
            ->references('id')
            ->on('workshops')
            ->cascadeOnDelete());
    }

    private function renameMenuOverridesColumn(string $from, string $to): void
    {
        if (! Schema::hasTable('workshop_menu_overrides')) {
            return;
        }

        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropForeign([$from]));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropForeign([$to]));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropUnique('workshop_menu_overrides_unique'));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropUnique('workshop_menu_overrides_tenant_unique'));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropUnique('workshop_menu_overrides_workshop_unique'));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropUnique("workshop_menu_overrides_{$from}_unique"));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropUnique("workshop_menu_overrides_{$to}_unique"));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropIndex('workshop_menu_overrides_workshop_index'));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropIndex('workshop_menu_overrides_tenant_index'));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropIndex("workshop_menu_overrides_{$from}_index"));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->dropIndex("workshop_menu_overrides_{$to}_index"));

        $this->renameColumn('workshop_menu_overrides', $from, $to);
        $this->backfillColumn('workshop_menu_overrides', $from, $to);

        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->unique([$to, 'menu_id'], $to === 'tenant_id'
            ? 'workshop_menu_overrides_tenant_unique'
            : 'workshop_menu_overrides_unique'));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table->index($to, $to === 'tenant_id'
            ? 'workshop_menu_overrides_tenant_index'
            : 'workshop_menu_overrides_workshop_index'));
        $this->safeSchema('workshop_menu_overrides', fn (Blueprint $table) => $table
            ->foreign($to)
            ->references('id')
            ->on('workshops')
            ->cascadeOnDelete());
    }

    private function renameColumn(string $table, string $from, string $to): void
    {
        if (! Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            return;
        }

        $this->safeSchema($table, fn (Blueprint $schema) => $schema->renameColumn($from, $to));
    }

    private function backfillColumn(string $table, string $from, string $to): void
    {
        if (! Schema::hasColumn($table, $from) || ! Schema::hasColumn($table, $to)) {
            return;
        }

        DB::table($table)
            ->whereNull($to)
            ->whereNotNull($from)
            ->update([$to => DB::raw($from)]);
    }

    private function safeSchema(string $table, callable $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Throwable) {
            // Keep migration idempotent across existing local schemas.
        }
    }
};
