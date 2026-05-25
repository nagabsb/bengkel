<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_page_settings')) {
            return;
        }

        if (Schema::hasColumn('booking_page_settings', 'cta_size')) {
            return;
        }

        Schema::table('booking_page_settings', function (Blueprint $table): void {
            $table->string('cta_size', 20)->default('medium')->after('cta_label');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_page_settings')) {
            return;
        }

        if (! Schema::hasColumn('booking_page_settings', 'cta_size')) {
            return;
        }

        Schema::table('booking_page_settings', function (Blueprint $table): void {
            $table->dropColumn('cta_size');
        });
    }
};

