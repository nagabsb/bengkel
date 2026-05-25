<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_orders')) {
            return;
        }

        Schema::create('service_orders', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('customer_id', 26);
            $table->char('customer_vehicle_id', 26)->nullable();
            $table->string('code', 40)->unique();
            $table->date('service_date');
            $table->string('status', 30)->default('open')->index();
            $table->string('complaint', 1000)->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->char('created_by_user_id', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'service_date'], 'service_orders_tenant_service_date_idx');
            $table->index(['tenant_id', 'status', 'created_at'], 'service_orders_tenant_status_created_idx');
            $table->index(['tenant_id', 'code'], 'service_orders_tenant_code_idx');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();

            $table->foreign('customer_vehicle_id')
                ->references('id')
                ->on('customer_vehicles')
                ->nullOnDelete();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};

