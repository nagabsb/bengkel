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
            if (! Schema::hasColumn('customer_vehicles', 'vehicle_type')) {
                $table->string('vehicle_type', 20)->default('motor');
                $table->index(
                    ['tenant_id', 'customer_id', 'vehicle_type'],
                    'customer_vehicles_tenant_customer_type_idx',
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_vehicles')) {
            return;
        }

        Schema::table('customer_vehicles', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_vehicles', 'vehicle_type')) {
                $table->dropIndex('customer_vehicles_tenant_customer_type_idx');
                $table->dropColumn('vehicle_type');
            }
        });
    }
};

