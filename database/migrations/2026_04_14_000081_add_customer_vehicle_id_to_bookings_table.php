<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || Schema::hasColumn('bookings', 'customer_vehicle_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->char('customer_vehicle_id', 26)
                ->nullable()
                ->after('customer_phone');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'customer_vehicle_id'],
                'bookings_tenant_customer_vehicle_idx',
            );

            if (Schema::hasTable('customer_vehicles')) {
                $table->foreign('customer_vehicle_id')
                    ->references('id')
                    ->on('customer_vehicles')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'customer_vehicle_id')) {
            return;
        }

        try {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropForeign(['customer_vehicle_id']);
            });
        } catch (\Throwable) {
            // noop
        }

        try {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropIndex('bookings_tenant_customer_vehicle_idx');
            });
        } catch (\Throwable) {
            // noop
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('customer_vehicle_id');
        });
    }
};
