<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_settings', 'midtrans_enabled')) {
                $table->boolean('midtrans_enabled')->default(false)->after('logo_background_color');
            }

            if (! Schema::hasColumn('platform_settings', 'midtrans_environment')) {
                $table->string('midtrans_environment', 20)->default('sandbox')->after('midtrans_enabled');
            }

            if (! Schema::hasColumn('platform_settings', 'midtrans_merchant_id')) {
                $table->string('midtrans_merchant_id', 100)->nullable()->after('midtrans_environment');
            }

            if (! Schema::hasColumn('platform_settings', 'midtrans_server_key')) {
                $table->text('midtrans_server_key')->nullable()->after('midtrans_merchant_id');
            }

            if (! Schema::hasColumn('platform_settings', 'midtrans_client_key')) {
                $table->text('midtrans_client_key')->nullable()->after('midtrans_server_key');
            }

            if (! Schema::hasColumn('platform_settings', 'manual_payment_enabled')) {
                $table->boolean('manual_payment_enabled')->default(false)->after('midtrans_client_key');
            }

            if (! Schema::hasColumn('platform_settings', 'manual_provider_name')) {
                $table->string('manual_provider_name', 100)->nullable()->after('manual_payment_enabled');
            }

            if (! Schema::hasColumn('platform_settings', 'manual_account_name')) {
                $table->string('manual_account_name', 100)->nullable()->after('manual_provider_name');
            }

            if (! Schema::hasColumn('platform_settings', 'manual_account_number')) {
                $table->string('manual_account_number', 100)->nullable()->after('manual_account_name');
            }

            if (! Schema::hasColumn('platform_settings', 'manual_payment_notes')) {
                $table->string('manual_payment_notes', 500)->nullable()->after('manual_account_number');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_settings', 'manual_payment_notes')) {
                $table->dropColumn('manual_payment_notes');
            }

            if (Schema::hasColumn('platform_settings', 'manual_account_number')) {
                $table->dropColumn('manual_account_number');
            }

            if (Schema::hasColumn('platform_settings', 'manual_account_name')) {
                $table->dropColumn('manual_account_name');
            }

            if (Schema::hasColumn('platform_settings', 'manual_provider_name')) {
                $table->dropColumn('manual_provider_name');
            }

            if (Schema::hasColumn('platform_settings', 'manual_payment_enabled')) {
                $table->dropColumn('manual_payment_enabled');
            }

            if (Schema::hasColumn('platform_settings', 'midtrans_client_key')) {
                $table->dropColumn('midtrans_client_key');
            }

            if (Schema::hasColumn('platform_settings', 'midtrans_server_key')) {
                $table->dropColumn('midtrans_server_key');
            }

            if (Schema::hasColumn('platform_settings', 'midtrans_merchant_id')) {
                $table->dropColumn('midtrans_merchant_id');
            }

            if (Schema::hasColumn('platform_settings', 'midtrans_environment')) {
                $table->dropColumn('midtrans_environment');
            }

            if (Schema::hasColumn('platform_settings', 'midtrans_enabled')) {
                $table->dropColumn('midtrans_enabled');
            }
        });
    }
};
