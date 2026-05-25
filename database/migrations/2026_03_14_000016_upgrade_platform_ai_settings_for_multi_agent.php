<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_ai_settings')) {
            return;
        }

        Schema::table('platform_ai_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_ai_settings', 'name')) {
                $table->string('name', 100)->nullable()->after('id');
            }

            if (! Schema::hasColumn('platform_ai_settings', 'priority_order')) {
                $table->unsignedSmallInteger('priority_order')->default(100)->after('name');
            }

            if (! Schema::hasColumn('platform_ai_settings', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('priority_order');
            }

            if (! Schema::hasColumn('platform_ai_settings', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_active');
            }

            if (! Schema::hasColumn('platform_ai_settings', 'is_failover_enabled')) {
                $table->boolean('is_failover_enabled')->default(true)->after('is_default');
            }

            if (! Schema::hasColumn('platform_ai_settings', 'monthly_token_limit')) {
                $table->unsignedInteger('monthly_token_limit')->nullable()->after('is_failover_enabled');
            }

            if (! Schema::hasColumn('platform_ai_settings', 'used_token_count')) {
                $table->unsignedInteger('used_token_count')->default(0)->after('monthly_token_limit');
            }

            $table->index(['is_active', 'priority_order'], 'platform_ai_settings_active_priority_idx');
            $table->index('is_default', 'platform_ai_settings_default_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_ai_settings')) {
            return;
        }

        Schema::table('platform_ai_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_ai_settings', 'is_active') && Schema::hasColumn('platform_ai_settings', 'priority_order')) {
                $table->dropIndex('platform_ai_settings_active_priority_idx');
            }

            if (Schema::hasColumn('platform_ai_settings', 'is_default')) {
                $table->dropIndex('platform_ai_settings_default_idx');
            }

            if (Schema::hasColumn('platform_ai_settings', 'used_token_count')) {
                $table->dropColumn('used_token_count');
            }

            if (Schema::hasColumn('platform_ai_settings', 'monthly_token_limit')) {
                $table->dropColumn('monthly_token_limit');
            }

            if (Schema::hasColumn('platform_ai_settings', 'is_failover_enabled')) {
                $table->dropColumn('is_failover_enabled');
            }

            if (Schema::hasColumn('platform_ai_settings', 'is_default')) {
                $table->dropColumn('is_default');
            }

            if (Schema::hasColumn('platform_ai_settings', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('platform_ai_settings', 'priority_order')) {
                $table->dropColumn('priority_order');
            }

            if (Schema::hasColumn('platform_ai_settings', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};

