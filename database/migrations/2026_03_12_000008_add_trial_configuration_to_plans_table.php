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
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_trial')
                ->default(true)
                ->after('has_loyalty');

            $table->unsignedSmallInteger('trial_duration_days')
                ->default(14)
                ->after('has_trial')
                ->comment('Trial duration in days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'has_trial',
                'trial_duration_days',
            ]);
        });
    }
};
