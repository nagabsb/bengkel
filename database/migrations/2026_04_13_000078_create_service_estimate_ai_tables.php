<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_ai_prompt_settings')) {
            Schema::create('platform_ai_prompt_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('feature_key', 80)->unique();
                $table->string('name', 150);
                $table->longText('system_prompt');
                $table->longText('feature_prompt');
                $table->boolean('is_active')->default(true);
                $table->char('created_by_user_id', 26)->nullable();
                $table->char('updated_by_user_id', 26)->nullable();
                $table->timestamps();

                $table->index(['is_active', 'feature_key'], 'platform_ai_prompt_settings_active_feature_idx');

                $table->foreign('created_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign('updated_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('tenant_ai_prompt_overrides')) {
            Schema::create('tenant_ai_prompt_overrides', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->string('feature_key', 80);
                $table->string('communication_tone', 20)->default('semi_formal');
                $table->json('preferred_sparepart_brands')->nullable();
                $table->text('estimation_policy_notes')->nullable();
                $table->string('labor_rounding_rule', 20)->default('nearest');
                $table->text('additional_constraints')->nullable();
                $table->boolean('is_active')->default(true);
                $table->char('created_by_user_id', 26)->nullable();
                $table->char('updated_by_user_id', 26)->nullable();
                $table->timestamps();

                $table->unique(
                    ['tenant_id', 'feature_key'],
                    'tenant_ai_prompt_overrides_tenant_feature_unique',
                );
                $table->index(
                    ['tenant_id', 'is_active', 'feature_key'],
                    'tenant_ai_prompt_overrides_tenant_active_feature_idx',
                );

                $table->foreign('created_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign('updated_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('service_order_estimate_ai_logs')) {
            Schema::create('service_order_estimate_ai_logs', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->char('service_order_id', 26);
                $table->foreign('service_order_id')->references('id')->on('service_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('ai_agent_id')->nullable();
                $table->foreign('ai_agent_id')
                    ->references('id')
                    ->on('platform_ai_settings')
                    ->nullOnDelete();
                $table->string('feature_key', 80)->default('service_estimate_v1');
                $table->string('status', 20)->default('success');
                $table->char('generated_by_user_id', 26)->nullable();
                $table->foreign('generated_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->json('input_payload')->nullable();
                $table->json('prompt_snapshot')->nullable();
                $table->json('output_payload')->nullable();
                $table->text('error_message')->nullable();
                $table->unsignedInteger('prompt_tokens')->default(0);
                $table->unsignedInteger('completion_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);
                $table->unsignedInteger('latency_ms')->nullable();
                $table->timestamps();

                $table->index(
                    ['tenant_id', 'service_order_id', 'created_at'],
                    'service_order_estimate_ai_logs_tenant_order_created_idx',
                );
                $table->index(
                    ['tenant_id', 'status', 'created_at'],
                    'service_order_estimate_ai_logs_tenant_status_created_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_estimate_ai_logs');
        Schema::dropIfExists('tenant_ai_prompt_overrides');
        Schema::dropIfExists('platform_ai_prompt_settings');
    }
};
