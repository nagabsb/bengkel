<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_payment_methods')) {
            return;
        }

        Schema::create('platform_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label', 100);
            $table->boolean('is_enabled')->default(false);
            $table->string('midtrans_environment', 20)->nullable();
            $table->string('midtrans_merchant_id', 100)->nullable();
            $table->text('midtrans_server_key')->nullable();
            $table->text('midtrans_client_key')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order'], 'platform_payment_methods_enabled_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payment_methods');
    }
};
