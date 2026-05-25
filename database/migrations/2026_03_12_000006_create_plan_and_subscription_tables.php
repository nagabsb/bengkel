<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedInteger('max_workshops')->default(1);
            $table->unsignedInteger('max_users_per_ws')->default(5);
            $table->boolean('has_ai_feature')->default(false);
            $table->boolean('has_notification')->default(false);
            $table->boolean('has_loyalty')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('label', 100);
            $table->unsignedInteger('duration_months');
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedTinyInteger('discount_pct')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'duration_months'], 'plan_prices_plan_duration_unique');
        });

        Schema::create('workshop_subscriptions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workshop_id', 26);
            $table->foreignId('plan_price_id')->constrained('plan_prices');
            $table->enum('status', ['trial', 'active', 'expired', 'cancelled', 'suspended'])->default('trial');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();

            $table->index(['workshop_id', 'status'], 'workshop_subscriptions_workshop_status_index');
            $table->foreign('workshop_id')
                ->references('id')
                ->on('workshops')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_subscriptions');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
    }
};
