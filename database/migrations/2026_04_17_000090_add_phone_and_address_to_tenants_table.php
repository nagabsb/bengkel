<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        $hasPhone = Schema::hasColumn('tenants', 'phone');
        $hasAddress = Schema::hasColumn('tenants', 'address');
        if ($hasPhone && $hasAddress) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) use ($hasPhone, $hasAddress): void {
            if (! $hasPhone) {
                $table->string('phone', 30)->nullable()->after('subdomain');
            }

            if (! $hasAddress) {
                $table->string('address', 255)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        $hasAddress = Schema::hasColumn('tenants', 'address');
        $hasPhone = Schema::hasColumn('tenants', 'phone');
        if (! $hasAddress && ! $hasPhone) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) use ($hasAddress, $hasPhone): void {
            if ($hasAddress) {
                $table->dropColumn('address');
            }

            if ($hasPhone) {
                $table->dropColumn('phone');
            }
        });
    }
};
