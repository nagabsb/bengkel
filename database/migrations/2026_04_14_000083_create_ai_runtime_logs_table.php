<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_runtime_logs')) {
            Schema::create('ai_runtime_logs', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('tenant_id')->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                $table->string('source', 40)->default('owner_service_runtime');
                $table->string('feature_key', 80)->nullable();
                $table->string('status', 20)->default('success');
                $table->char('requester_user_id', 26)->nullable();
                $table->foreign('requester_user_id')->references('id')->on('users')->nullOnDelete();
                $table->char('service_order_id', 26)->nullable();
                $table->foreign('service_order_id')->references('id')->on('service_orders')->nullOnDelete();
                $table->unsignedBigInteger('ai_agent_id')->nullable();
                $table->foreign('ai_agent_id')->references('id')->on('platform_ai_settings')->nullOnDelete();
                $table->string('provider', 30)->nullable();
                $table->string('agent_model', 120)->nullable();
                $table->unsignedInteger('prompt_tokens')->default(0);
                $table->unsignedInteger('completion_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);
                $table->unsignedInteger('latency_ms')->nullable();
                $table->text('error_message')->nullable();
                $table->json('meta_payload')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at'], 'ai_runtime_logs_tenant_created_idx');
                $table->index(['source', 'created_at'], 'ai_runtime_logs_source_created_idx');
                $table->index(['feature_key', 'created_at'], 'ai_runtime_logs_feature_created_idx');
                $table->index(['status', 'created_at'], 'ai_runtime_logs_status_created_idx');
            });
        }

        if (! Schema::hasTable('service_order_estimate_ai_logs') || ! Schema::hasTable('ai_runtime_logs')) {
            return;
        }

        $sourceQuery = DB::table('service_order_estimate_ai_logs')
            ->select([
                'service_order_estimate_ai_logs.id',
                'service_order_estimate_ai_logs.tenant_id',
                DB::raw("'owner_service_runtime' as source"),
                'service_order_estimate_ai_logs.feature_key',
                'service_order_estimate_ai_logs.status',
                DB::raw('service_order_estimate_ai_logs.generated_by_user_id as requester_user_id'),
                'service_order_estimate_ai_logs.service_order_id',
                'service_order_estimate_ai_logs.ai_agent_id',
                DB::raw('NULL as provider'),
                DB::raw('NULL as agent_model'),
                DB::raw('COALESCE(service_order_estimate_ai_logs.prompt_tokens, 0) as prompt_tokens'),
                DB::raw('COALESCE(service_order_estimate_ai_logs.completion_tokens, 0) as completion_tokens'),
                DB::raw('COALESCE(service_order_estimate_ai_logs.total_tokens, 0) as total_tokens'),
                'service_order_estimate_ai_logs.latency_ms',
                'service_order_estimate_ai_logs.error_message',
                DB::raw('NULL as meta_payload'),
                'service_order_estimate_ai_logs.created_at',
                'service_order_estimate_ai_logs.updated_at',
            ])
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('ai_runtime_logs')
                    ->whereColumn('ai_runtime_logs.id', 'service_order_estimate_ai_logs.id');
            });

        DB::table('ai_runtime_logs')->insertUsing([
            'id',
            'tenant_id',
            'source',
            'feature_key',
            'status',
            'requester_user_id',
            'service_order_id',
            'ai_agent_id',
            'provider',
            'agent_model',
            'prompt_tokens',
            'completion_tokens',
            'total_tokens',
            'latency_ms',
            'error_message',
            'meta_payload',
            'created_at',
            'updated_at',
        ], $sourceQuery);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runtime_logs');
    }
};
