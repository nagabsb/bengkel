<?php

use App\Models\PlatformManualPaymentProvider;
use App\Models\PlatformPaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('superadmin can view payment settings page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.billing.manage', 'web'));

    $this->actingAs($user)
        ->get('/platform/settings/payments')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/PaymentSettings')
            ->has('paymentSettings'),
        );
});

test('superadmin can update payment settings', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.billing.manage', 'web'));

    $this->actingAs($user)
        ->post('/platform/settings/payments', [
            'midtrans_enabled' => true,
            'midtrans_environment' => 'sandbox',
            'midtrans_merchant_id' => 'G123456789',
            'midtrans_server_key' => 'SB-MID-SERVER-KEY',
            'midtrans_client_key' => 'SB-MID-CLIENT-KEY',
            'manual_payment_enabled' => true,
            'manual_providers' => [
                [
                    'provider_name' => 'BCA',
                    'account_name' => 'PT AutoServ Nusantara',
                    'account_number' => '1234567890',
                    'notes' => 'Kirim bukti transfer ke admin.',
                    'is_active' => true,
                ],
                [
                    'provider_name' => 'OVO',
                    'account_name' => 'AutoServ OVO',
                    'account_number' => '081234567890',
                    'notes' => 'Pembayaran via e-wallet.',
                    'is_active' => true,
                ],
            ],
        ])
        ->assertRedirect();

    $midtransMethod = PlatformPaymentMethod::query()->where('code', 'midtrans')->first();
    $manualMethod = PlatformPaymentMethod::query()->where('code', 'manual')->first();
    $manualProviders = PlatformManualPaymentProvider::query()
        ->where('payment_method_id', (int) ($manualMethod?->id ?? 0))
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();

    expect($midtransMethod)->not->toBeNull();
    expect((bool) $midtransMethod?->is_enabled)->toBeTrue();
    expect((string) $midtransMethod?->midtrans_environment)->toBe('sandbox');
    expect((string) $midtransMethod?->midtrans_merchant_id)->toBe('G123456789');
    expect((string) $midtransMethod?->midtrans_server_key)->toBe('SB-MID-SERVER-KEY');
    expect((string) $midtransMethod?->midtrans_client_key)->toBe('SB-MID-CLIENT-KEY');

    expect($manualMethod)->not->toBeNull();
    expect((bool) $manualMethod?->is_enabled)->toBeTrue();
    expect($manualProviders->count())->toBe(2);
    expect((string) ($manualProviders->first()?->provider_name ?? ''))->toBe('BCA');
    expect((string) ($manualProviders->first()?->account_name ?? ''))->toBe('PT AutoServ Nusantara');
    expect((string) ($manualProviders->first()?->account_number ?? ''))->toBe('1234567890');
});

test('non privileged user cannot update payment settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/platform/settings/payments', [
            'midtrans_enabled' => true,
            'midtrans_environment' => 'sandbox',
            'midtrans_merchant_id' => 'G123456789',
        ])
        ->assertForbidden();
});
