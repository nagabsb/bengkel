<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_ai_settings')) {
            return;
        }

        Schema::create('platform_ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 40)->default('openai');
            $table->string('agent_model', 120)->default('gpt-4.1-mini');
            $table->text('api_key')->nullable();
            $table->unsignedInteger('test_success_count')->default(0);
            $table->unsignedInteger('test_failed_count')->default(0);
            $table->string('last_test_status', 20)->nullable();
            $table->string('last_test_message', 255)->nullable();
            $table->unsignedInteger('last_test_prompt_tokens')->default(0);
            $table->unsignedInteger('last_test_completion_tokens')->default(0);
            $table->unsignedInteger('last_test_total_tokens')->default(0);
            $table->unsignedInteger('last_known_quota_remaining')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_settings');
    }
};