<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_payment_methods')
            || ! Schema::hasTable('platform_manual_payment_providers')
            || ! Schema::hasTable('platform_settings')) {
            return;
        }

        $setting = DB::table('platform_settings')->orderBy('id')->first();
        if (! $setting) {
            return;
        }

        $midtransEnabled = (bool) ($setting->midtrans_enabled ?? false);
        $manualEnabled = (bool) ($setting->manual_payment_enabled ?? false);

        $midtransMethodId = DB::table('platform_payment_methods')
            ->where('code', 'midtrans')
            ->value('id');

        if (! $midtransMethodId) {
            $midtransMethodId = DB::table('platform_payment_methods')->insertGetId([
                'code' => 'midtrans',
                'label' => 'Midtrans',
                'is_enabled' => $midtransEnabled,
                'midtrans_environment' => (string) ($setting->midtrans_environment ?? 'sandbox'),
                'midtrans_merchant_id' => $setting->midtrans_merchant_id,
                'midtrans_server_key' => $setting->midtrans_server_key,
                'midtrans_client_key' => $setting->midtrans_client_key,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $manualMethodId = DB::table('platform_payment_methods')
            ->where('code', 'manual')
            ->value('id');

        if (! $manualMethodId) {
            $manualMethodId = DB::table('platform_payment_methods')->insertGetId([
                'code' => 'manual',
                'label' => 'Manual Transfer',
                'is_enabled' => $manualEnabled,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $providerName = trim((string) ($setting->manual_provider_name ?? ''));
        $accountName = trim((string) ($setting->manual_account_name ?? ''));
        $accountNumber = trim((string) ($setting->manual_account_number ?? ''));

        if ($providerName === '' || $accountName === '' || $accountNumber === '') {
            return;
        }

        $existingProvider = DB::table('platform_manual_payment_providers')
            ->where('payment_method_id', $manualMethodId)
            ->where('provider_name', $providerName)
            ->where('account_name', $accountName)
            ->where('account_number', $accountNumber)
            ->first();

        if ($existingProvider) {
            return;
        }

        DB::table('platform_manual_payment_providers')->insert([
            'payment_method_id' => $manualMethodId,
            'provider_name' => $providerName,
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'notes' => trim((string) ($setting->manual_payment_notes ?? '')) ?: null,
            'is_active' => $manualEnabled,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Data backfill only.
    }
};
