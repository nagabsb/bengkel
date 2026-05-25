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
        Schema::table('users', function (Blueprint $table) {
            $table->char('workshop_id', 26)->nullable()->after('id')->index();
            $table->string('role', 50)->nullable()->after('password')->index();
            $table->string('user_type', 50)->nullable()->after('role')->index();
            $table->boolean('is_superadmin')->default(false)->after('user_type')->index();
            $table->boolean('is_owner')->default(false)->after('is_superadmin')->index();

            $table->foreign('workshop_id')
                ->references('id')
                ->on('workshops')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['workshop_id']);
            $table->dropColumn([
                'workshop_id',
                'role',
                'user_type',
                'is_superadmin',
                'is_owner',
            ]);
        });
    }
};
