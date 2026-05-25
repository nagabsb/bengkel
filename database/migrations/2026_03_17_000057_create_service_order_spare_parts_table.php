<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_order_spare_parts')) {
            return;
        }

        Schema::create('service_order_spare_parts', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('workshop_id', 26);
            $table->char('service_order_id', 26);
            $table->char('spare_part_id', 26);
            $table->char('warehouse_id', 26)->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'workshop_id', 'service_order_id'],
                'service_order_spare_parts_tenant_workshop_order_idx',
            );
            $table->index(
                ['tenant_id', 'workshop_id', 'spare_part_id'],
                'service_order_spare_parts_tenant_workshop_spare_part_idx',
            );
            $table->index(
                ['tenant_id', 'workshop_id', 'warehouse_id'],
                'service_order_spare_parts_tenant_workshop_warehouse_idx',
            );

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('workshop_id')
                ->references('id')
                ->on('workshops')
                ->cascadeOnDelete();

            $table->foreign('service_order_id')
                ->references('id')
                ->on('service_orders')
                ->cascadeOnDelete();

            $table->foreign('spare_part_id')
                ->references('id')
                ->on('spare_parts')
                ->cascadeOnDelete();

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_spare_parts');
    }
};

