<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_payments')) {
            return;
        }

        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('workshop_id', 26)->nullable();
            $table->char('invoice_id', 26);
            $table->date('paid_at');
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('method', 40)->default('cash');
            $table->string('reference_number', 80)->nullable();
            $table->string('notes', 500)->nullable();
            $table->char('created_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id', 'paid_at'], 'invoice_payments_tenant_invoice_paid_at_idx');
            $table->index(['tenant_id', 'workshop_id', 'paid_at'], 'invoice_payments_tenant_workshop_paid_at_idx');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('workshop_id')
                ->references('id')
                ->on('workshops')
                ->nullOnDelete();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnDelete();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};

