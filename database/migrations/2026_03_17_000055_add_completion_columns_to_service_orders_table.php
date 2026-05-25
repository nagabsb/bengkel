<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_orders', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('service_orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }

            if (! Schema::hasColumn('service_orders', 'completion_notes')) {
                $table->string('completion_notes', 1000)->nullable()->after('complaint');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        Schema::table('service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('service_orders', 'completion_notes')) {
                $table->dropColumn('completion_notes');
            }

            if (Schema::hasColumn('service_orders', 'completed_at')) {
                $table->dropColumn('completed_at');
            }

            if (Schema::hasColumn('service_orders', 'started_at')) {
                $table->dropColumn('started_at');
            }
        });
    }
};

