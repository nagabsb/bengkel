<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booking_page_settings')) {
            return;
        }

        Schema::create('booking_page_settings', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->string('mode', 20)->default('tech');
            $table->string('primary_color', 7)->default('#0F766E');
            $table->string('font_preset', 20)->default('modern');
            $table->string('radius_preset', 20)->default('medium');
            $table->string('headline', 120)->default('Booking Servis Cepat & Mudah');
            $table->string('subheadline', 180)->default('Atur jadwal servis bengkel Anda tanpa antre panjang.');
            $table->string('cta_label', 60)->default('Booking Sekarang');
            $table->string('trust_badge', 140)->default('Dipercaya pelanggan aktif setiap hari.');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tenant_id', 'booking_page_settings_tenant_unique');
            $table->index(['tenant_id', 'updated_at'], 'booking_page_settings_tenant_updated_idx');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_page_settings');
    }
};
