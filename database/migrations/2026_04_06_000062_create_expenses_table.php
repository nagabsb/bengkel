<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            return;
        }

        Schema::create('expenses', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('workshop_id', 26);
            $table->date('expense_date');
            $table->string('category', 80);
            $table->string('description', 255);
            $table->string('reference_number', 80)->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('amount');
            $table->char('created_by_user_id', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'workshop_id', 'expense_date'], 'expenses_tenant_workshop_date_idx');
            $table->index(['tenant_id', 'category', 'expense_date'], 'expenses_tenant_category_date_idx');
            $table->index(['tenant_id', 'created_at'], 'expenses_tenant_created_idx');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('workshop_id')
                ->references('id')
                ->on('workshops')
                ->cascadeOnDelete();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
