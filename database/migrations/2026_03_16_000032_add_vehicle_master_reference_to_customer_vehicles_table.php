<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_vehicles')) {
            return;
        }

        Schema::table('customer_vehicles', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_vehicles', 'vehicle_master_brand_id')) {
                $table->foreignId('vehicle_master_brand_id')
                    ->nullable()
                    ->after('customer_id');
            }

            if (! Schema::hasColumn('customer_vehicles', 'vehicle_master_model_id')) {
                $table->foreignId('vehicle_master_model_id')
                    ->nullable()
                    ->after('vehicle_master_brand_id');
            }
        });

        Schema::table('customer_vehicles', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_vehicles', 'vehicle_master_brand_id')) {
                $table->index(
                    ['tenant_id', 'vehicle_master_brand_id'],
                    'customer_vehicles_tenant_master_brand_idx',
                );
                $table->foreign('vehicle_master_brand_id')
                    ->references('id')
                    ->on('vehicle_master_brands')
                    ->nullOnDelete();
            }

            if (Schema::hasColumn('customer_vehicles', 'vehicle_master_model_id')) {
                $table->index(
                    ['tenant_id', 'vehicle_master_model_id'],
                    'customer_vehicles_tenant_master_model_idx',
                );
                $table->foreign('vehicle_master_model_id')
                    ->references('id')
                    ->on('vehicle_master_models')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_vehicles')) {
            return;
        }

        Schema::table('customer_vehicles', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_vehicles', 'vehicle_master_model_id')) {
                $table->dropForeign(['vehicle_master_model_id']);
                $table->dropIndex('customer_vehicles_tenant_master_model_idx');
                $table->dropColumn('vehicle_master_model_id');
            }

            if (Schema::hasColumn('customer_vehicles', 'vehicle_master_brand_id')) {
                $table->dropForeign(['vehicle_master_brand_id']);
                $table->dropIndex('customer_vehicles_tenant_master_brand_idx');
                $table->dropColumn('vehicle_master_brand_id');
            }
        });
    }
};

