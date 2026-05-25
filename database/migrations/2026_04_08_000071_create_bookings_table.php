<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings')) {
            return;
        }

        Schema::create('bookings', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('workshop_id', 26);
            $table->string('code', 30);
            $table->date('booking_date');
            $table->time('booking_time')->nullable();
            $table->unsignedInteger('queue_number');
            $table->string('customer_name', 150);
            $table->string('customer_phone', 30)->nullable();
            $table->string('complaint', 1000);
            $table->string('notes', 500)->nullable();
            $table->string('status', 30)->default('queued');
            $table->char('created_by_user_id', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'bookings_tenant_code_unique');
            $table->unique(
                ['tenant_id', 'workshop_id', 'booking_date', 'queue_number'],
                'bookings_tenant_workshop_date_queue_unique',
            );
            $table->index(['tenant_id', 'workshop_id', 'booking_date'], 'bookings_tenant_workshop_date_idx');
            $table->index(['tenant_id', 'status', 'booking_date'], 'bookings_tenant_status_date_idx');
            $table->index(['tenant_id', 'created_at'], 'bookings_tenant_created_idx');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('workshop_id')
                ->references('id')
                ->on('workshops')
                ->cascadeOnDelete();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
