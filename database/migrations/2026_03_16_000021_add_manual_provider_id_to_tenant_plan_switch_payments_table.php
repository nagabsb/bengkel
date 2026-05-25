<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_plan_switch_payments')) {
            return;
        }

        Schema::table('tenant_plan_switch_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_plan_switch_payments', 'manual_provider_id')) {
                $table->foreignId('manual_provider_id')
                    ->nullable()
                    ->after('payment_url')
                    ->constrained('platform_manual_payment_providers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_plan_switch_payments')) {
            return;
        }

        Schema::table('tenant_plan_switch_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_plan_switch_payments', 'manual_provider_id')) {
                $table->dropConstrainedForeignId('manual_provider_id');
            }
        });
    }
};
