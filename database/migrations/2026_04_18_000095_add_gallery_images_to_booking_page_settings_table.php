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

        if (Schema::hasColumn('booking_page_settings', 'gallery_images')) {
            return;
        }

        Schema::table('booking_page_settings', function (Blueprint $table): void {
            $table->json('gallery_images')->nullable()->after('trust_badge');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_page_settings')) {
            return;
        }

        if (! Schema::hasColumn('booking_page_settings', 'gallery_images')) {
            return;
        }

        Schema::table('booking_page_settings', function (Blueprint $table): void {
            $table->dropColumn('gallery_images');
        });
    }
};
