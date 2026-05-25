<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_ai_prompt_settings')) {
            return;
        }

        if (! Schema::hasColumn('platform_ai_prompt_settings', 'feature_prompt_config')) {
            Schema::table('platform_ai_prompt_settings', function (Blueprint $table): void {
                $table->json('feature_prompt_config')->nullable()->after('feature_prompt');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_ai_prompt_settings')) {
            return;
        }

        if (Schema::hasColumn('platform_ai_prompt_settings', 'feature_prompt_config')) {
            Schema::table('platform_ai_prompt_settings', function (Blueprint $table): void {
                $table->dropColumn('feature_prompt_config');
            });
        }
    }
};
