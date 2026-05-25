<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_vehicle_masters')) {
            return;
        }

        Schema::create('tenant_vehicle_masters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('vehicle_type', 20)->default('motor');
            $table->string('brand', 120);
            $table->string('model', 120);
            $table->string('source', 40)->default('manual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(
                ['tenant_id', 'vehicle_type', 'brand', 'model'],
                'tenant_vehicle_masters_tenant_type_brand_model_uq',
            );
            $table->index(
                ['tenant_id', 'vehicle_type', 'is_active'],
                'tenant_vehicle_masters_tenant_type_active_idx',
            );
            $table->index(
                ['tenant_id', 'brand', 'model'],
                'tenant_vehicle_masters_tenant_brand_model_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_vehicle_masters');
    }
};

