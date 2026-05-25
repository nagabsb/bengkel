<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spare_parts')) {
            return;
        }

        Schema::create('spare_parts', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('supplier_id', 26)->nullable();
            $table->string('name', 150);
            $table->string('sku', 80)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('unit', 30)->nullable();
            $table->unsignedBigInteger('purchase_price')->nullable();
            $table->unsignedBigInteger('selling_price')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'created_at'], 'spare_parts_tenant_created_idx');
            $table->index(['tenant_id', 'name'], 'spare_parts_tenant_name_idx');
            $table->index(['tenant_id', 'supplier_id'], 'spare_parts_tenant_supplier_idx');
            $table->index(['tenant_id', 'sku'], 'spare_parts_tenant_sku_idx');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_parts');
    }
};
