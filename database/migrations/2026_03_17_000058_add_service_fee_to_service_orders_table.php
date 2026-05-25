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
            if (! Schema::hasColumn('service_orders', 'service_fee')) {
                $table->unsignedBigInteger('service_fee')
                    ->default(0)
                    ->after('odometer');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('service_orders', 'service_fee')) {
                $table->dropColumn('service_fee');
            }
        });
    }
};

