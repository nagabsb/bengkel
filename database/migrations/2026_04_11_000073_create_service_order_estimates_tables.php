<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_order_estimates')) {
            Schema::create('service_order_estimates', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

                $table->char('service_order_id', 26);
                $table->foreign('service_order_id')->references('id')->on('service_orders')->cascadeOnDelete();

                $table->string('code', 40);
                $table->unsignedInteger('revision')->default(1);
                $table->string('status', 30)->default('draft');

                $table->string('customer_name', 150);
                $table->string('customer_phone', 30)->nullable();
                $table->string('customer_email', 150)->nullable();

                $table->unsignedBigInteger('subtotal_service')->default(0);
                $table->unsignedBigInteger('subtotal_sparepart')->default(0);
                $table->unsignedBigInteger('total_amount')->default(0);

                $table->timestamp('valid_until')->nullable();
                $table->timestamp('approval_requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('expired_at')->nullable();

                $table->string('approval_token_hash', 64)->nullable();
                $table->index('approval_token_hash', 'service_order_estimates_approval_token_hash_idx');

                $table->string('approved_by_name', 150)->nullable();
                $table->string('approved_by_phone', 30)->nullable();
                $table->string('approved_signature_path', 255)->nullable();
                $table->string('approved_ip', 64)->nullable();
                $table->text('approved_user_agent')->nullable();

                $table->text('approval_note')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('internal_note')->nullable();

                $table->char('requested_by_user_id', 26)->nullable();
                $table->foreign('requested_by_user_id')->references('id')->on('users')->nullOnDelete();

                $table->json('approval_payload')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code'], 'service_order_estimates_tenant_code_unique');
                $table->index(
                    ['tenant_id', 'service_order_id', 'status'],
                    'service_order_estimates_tenant_order_status_idx',
                );
                $table->index(
                    ['tenant_id', 'created_at'],
                    'service_order_estimates_tenant_created_idx',
                );
            });
        }

        if (! Schema::hasTable('service_order_estimate_items')) {
            Schema::create('service_order_estimate_items', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

                $table->char('service_order_estimate_id', 26);
                $table->foreign('service_order_estimate_id')->references('id')->on('service_order_estimates')->cascadeOnDelete();

                $table->string('item_type', 20)->default('service');
                $table->char('spare_part_id', 26)->nullable();
                $table->foreign('spare_part_id')->references('id')->on('spare_parts')->nullOnDelete();

                $table->string('label', 150);
                $table->string('unit_label', 50)->nullable();
                $table->text('description')->nullable();

                $table->unsignedInteger('qty')->default(1);
                $table->unsignedBigInteger('unit_price')->default(0);
                $table->unsignedBigInteger('subtotal')->default(0);

                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(
                    ['tenant_id', 'service_order_estimate_id'],
                    'service_order_estimate_items_tenant_estimate_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_estimate_items');
        Schema::dropIfExists('service_order_estimates');
    }
};

