<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_spare_part_stocks')) {
            return;
        }

        Schema::create('warehouse_spare_part_stocks', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('workshop_id', 26);
            $table->char('warehouse_id', 26);
            $table->char('spare_part_id', 26);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->timestamps();

            $table->index(
                ['tenant_id', 'workshop_id', 'warehouse_id'],
                'wsp_stocks_tenant_workshop_warehouse_idx',
            );
            $table->index(
                ['tenant_id', 'workshop_id', 'spare_part_id'],
                'wsp_stocks_tenant_workshop_part_idx',
            );
            $table->unique(
                ['tenant_id', 'workshop_id', 'warehouse_id', 'spare_part_id'],
                'wsp_stocks_unique_scope_idx',
            );

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('workshop_id')
                ->references('id')
                ->on('workshops')
                ->cascadeOnDelete();

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->cascadeOnDelete();

            $table->foreign('spare_part_id')
                ->references('id')
                ->on('spare_parts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_spare_part_stocks');
    }
};
