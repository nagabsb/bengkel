<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('workshops')) {
            return;
        }

        $hasPhone = Schema::hasColumn('workshops', 'phone');
        $hasAddress = Schema::hasColumn('workshops', 'address');

        if (! $hasPhone || ! $hasAddress) {
            Schema::table('workshops', function (Blueprint $table) use ($hasPhone, $hasAddress): void {
                if (! $hasPhone) {
                    $table->string('phone', 30)->nullable()->after('code');
                }

                if (! $hasAddress) {
                    $table->string('address', 255)->nullable()->after('phone');
                }
            });
        }

        if (Schema::hasColumn('workshops', 'phone')) {
            $this->safeSchema('workshops', fn (Blueprint $table) => $table->index('phone', 'workshops_phone_idx'));
        }

        if (Schema::hasColumn('workshops', 'address')) {
            $this->safeSchema('workshops', fn (Blueprint $table) => $table->index('address', 'workshops_address_idx'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('workshops')) {
            return;
        }

        $this->safeSchema('workshops', fn (Blueprint $table) => $table->dropIndex('workshops_phone_idx'));
        $this->safeSchema('workshops', fn (Blueprint $table) => $table->dropIndex('workshops_address_idx'));

        $hasPhone = Schema::hasColumn('workshops', 'phone');
        $hasAddress = Schema::hasColumn('workshops', 'address');

        if (! $hasPhone && ! $hasAddress) {
            return;
        }

        Schema::table('workshops', function (Blueprint $table) use ($hasPhone, $hasAddress): void {
            if ($hasAddress) {
                $table->dropColumn('address');
            }

            if ($hasPhone) {
                $table->dropColumn('phone');
            }
        });
    }

    private function safeSchema(string $table, callable $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Throwable) {
            // Keep migration idempotent across local schemas.
        }
    }
};
