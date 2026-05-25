<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('workshop_id', 26)->nullable();
            $table->char('service_order_id', 26)->nullable();
            $table->char('customer_id', 26)->nullable();
            $table->string('code', 40);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('unpaid');
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('remaining_amount')->default(0);
            $table->timestamp('last_paid_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('notes', 500)->nullable();
            $table->char('created_by_user_id', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code', 'invoices_code_unique');
            $table->unique(['tenant_id', 'service_order_id'], 'invoices_tenant_service_order_unique');
            $table->index(['tenant_id', 'workshop_id', 'status'], 'invoices_tenant_workshop_status_idx');
            $table->index(['tenant_id', 'due_date', 'status'], 'invoices_tenant_due_date_status_idx');
            $table->index(['tenant_id', 'invoice_date'], 'invoices_tenant_invoice_date_idx');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('workshop_id')
                ->references('id')
                ->on('workshops')
                ->nullOnDelete();

            $table->foreign('service_order_id')
                ->references('id')
                ->on('service_orders')
                ->nullOnDelete();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->nullOnDelete();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

