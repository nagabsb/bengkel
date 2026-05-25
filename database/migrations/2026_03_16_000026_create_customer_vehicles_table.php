<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_vehicles')) {
            return;
        }

        Schema::create('customer_vehicles', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('customer_id', 26);
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->string('variant', 100)->nullable();
            $table->string('plate_number', 20)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'customer_id', 'created_at'], 'customer_vehicles_tenant_customer_created_idx');
            $table->index(['tenant_id', 'customer_id', 'plate_number'], 'customer_vehicles_tenant_customer_plate_idx');
            $table->index(['tenant_id', 'brand', 'model'], 'customer_vehicles_tenant_brand_model_idx');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vehicles');
    }
};

