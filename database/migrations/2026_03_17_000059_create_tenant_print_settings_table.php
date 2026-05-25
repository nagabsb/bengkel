<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_print_settings')) {
            return;
        }

        Schema::create('tenant_print_settings', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->unique();
            $table->string('printer_name', 120)->default('Printer Utama');
            $table->string('print_type', 30)->default('thermal');
            $table->string('paper_size', 20)->default('80mm');
            $table->timestamps();

            $table->index(['tenant_id', 'print_type'], 'tenant_print_settings_tenant_type_idx');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_print_settings');
    }
};
