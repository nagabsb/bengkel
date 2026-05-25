<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_orders', 'vehicle_condition')) {
                $table->string('vehicle_condition', 1000)->nullable()->after('complaint');
            }

            if (! Schema::hasColumn('service_orders', 'estimated_days')) {
                $table->unsignedSmallInteger('estimated_days')->nullable()->after('vehicle_condition');
            }

            if (! Schema::hasColumn('service_orders', 'estimated_finish_date')) {
                $table->date('estimated_finish_date')->nullable()->after('estimated_days');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('service_orders', 'estimated_finish_date')) {
                $table->dropColumn('estimated_finish_date');
            }

            if (Schema::hasColumn('service_orders', 'estimated_days')) {
                $table->dropColumn('estimated_days');
            }

            if (Schema::hasColumn('service_orders', 'vehicle_condition')) {
                $table->dropColumn('vehicle_condition');
            }
        });
    }
};

