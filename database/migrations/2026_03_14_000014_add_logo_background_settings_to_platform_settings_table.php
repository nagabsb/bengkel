<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_settings', 'logo_background_enabled')) {
                $table->boolean('logo_background_enabled')->default(true)->after('app_logo_path');
            }

            if (! Schema::hasColumn('platform_settings', 'logo_background_color')) {
                $table->string('logo_background_color', 7)->nullable()->after('logo_background_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_settings', 'logo_background_color')) {
                $table->dropColumn('logo_background_color');
            }

            if (Schema::hasColumn('platform_settings', 'logo_background_enabled')) {
                $table->dropColumn('logo_background_enabled');
            }
        });
    }
};
