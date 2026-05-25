<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_order_mechanics')) {
            return;
        }

        Schema::create('service_order_mechanics', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('workshop_id', 26);
            $table->char('service_order_id', 26);
            $table->char('user_id', 26);
            $table->timestamps();

            $table->unique(
                ['service_order_id', 'user_id'],
                'service_order_mechanics_order_user_unique',
            );
            $table->index(
                ['tenant_id', 'workshop_id', 'service_order_id'],
                'service_order_mechanics_tenant_workshop_order_idx',
            );
            $table->index(
                ['tenant_id', 'workshop_id', 'user_id'],
                'service_order_mechanics_tenant_workshop_user_idx',
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

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_mechanics');
    }
};

