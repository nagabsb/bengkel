<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_manual_payment_providers')) {
            return;
        }

        Schema::create('platform_manual_payment_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_method_id')->constrained('platform_payment_methods')->cascadeOnDelete();
            $table->string('provider_name', 100);
            $table->string('account_name', 100);
            $table->string('account_number', 100);
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['payment_method_id', 'is_active', 'sort_order'], 'platform_manual_payment_providers_method_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_manual_payment_providers');
    }
};
