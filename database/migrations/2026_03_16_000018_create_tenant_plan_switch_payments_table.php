<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_plan_switch_payments')) {
            return;
        }

        Schema::create('tenant_plan_switch_payments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('requested_by_user_id', 26)->nullable();
            $table->foreignId('current_plan_price_id')->nullable()->constrained('plan_prices');
            $table->foreignId('target_plan_price_id')->constrained('plan_prices');
            $table->enum('payment_method', ['midtrans', 'manual']);
            $table->enum('status', ['pending', 'paid', 'cancelled', 'expired', 'failed'])->default('pending');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->string('payment_gateway', 50)->nullable();
            $table->string('payment_gateway_reference', 191)->nullable();
            $table->string('payment_url', 500)->nullable();
            $table->string('manual_provider_name', 100)->nullable();
            $table->string('manual_account_name', 100)->nullable();
            $table->string('manual_account_number', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'tenant_plan_switch_payments_tenant_status_idx');
            $table->index(['tenant_id', 'target_plan_price_id', 'status'], 'tenant_plan_switch_payments_target_status_idx');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('requested_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_plan_switch_payments');
    }
};
