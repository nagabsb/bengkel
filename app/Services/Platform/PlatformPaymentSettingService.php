<?php

namespace App\Services\Platform;

use App\Models\PlatformManualPaymentProvider;
use App\Models\PlatformPaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PlatformPaymentSettingService
{
    private const CACHE_KEY = 'platform.payment.settings.v2';

    /**
     * @return array{paymentSettings: array<string, mixed>}
     */
    public function buildPageData(): array
    {
        return [
            'paymentSettings' => $this->resolvePaymentSettingsForPage(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOwnerPaymentOptions(): array
    {
        $settings = $this->resolvePaymentSettings();

        $availableMethods = [];
        if ((bool) ($settings['midtrans']['enabled'] ?? false)) {
            $availableMethods[] = 'midtrans';
        }

        $activeManualProviders = collect($settings['manual']['providers'] ?? [])
            ->filter(fn (mixed $provider): bool => (bool) data_get($provider, 'is_active', false))
            ->values()
            ->all();
        if ((bool) ($settings['manual']['enabled'] ?? false) && count($activeManualProviders) > 0) {
            $availableMethods[] = 'manual';
        }

        return [
            'available_methods' => $availableMethods,
            'midtrans' => [
                'enabled' => (bool) ($settings['midtrans']['enabled'] ?? false),
                'environment' => (string) ($settings['midtrans']['environment'] ?? 'sandbox'),
                'client_key' => (string) ($settings['midtrans']['client_key'] ?? ''),
            ],
            'manual' => [
                'enabled' => (bool) ($settings['manual']['enabled'] ?? false),
                'providers' => $activeManualProviders,
            ],
        ];
    }

    /**
     * @return array{enabled: bool, environment: string, merchant_id: string, server_key: string, client_key: string, base_url: string}
     */
    public function resolveMidtransGatewayConfig(): array
    {
        $settings = $this->resolvePaymentSettings();
        $environment = strtolower(trim((string) ($settings['midtrans']['environment'] ?? 'sandbox')));
        $isProduction = $environment === 'production';

        return [
            'enabled' => (bool) ($settings['midtrans']['enabled'] ?? false),
            'environment' => $isProduction ? 'production' : 'sandbox',
            'merchant_id' => trim((string) ($settings['midtrans']['merchant_id'] ?? '')),
            'server_key' => trim((string) ($settings['midtrans']['server_key'] ?? '')),
            'client_key' => trim((string) ($settings['midtrans']['client_key'] ?? '')),
            'base_url' => $isProduction
                ? 'https://app.midtrans.com'
                : 'https://app.sandbox.midtrans.com',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updatePaymentSettings(array $validated): void
    {
        if (! Schema::hasTable('platform_payment_methods') || ! Schema::hasTable('platform_manual_payment_providers')) {
            throw ValidationException::withMessages([
                'midtrans_enabled' => 'Tabel pengaturan platform belum siap.',
            ]);
        }

        $midtransMethod = $this->firstOrCreatePaymentMethod('midtrans', 'Midtrans', 10);
        $manualMethod = $this->firstOrCreatePaymentMethod('manual', 'Manual Transfer', 20);
        $midtransEnabled = (bool) ($validated['midtrans_enabled'] ?? false);
        $manualEnabled = (bool) ($validated['manual_payment_enabled'] ?? false);

        $merchantIdInput = $this->sanitizeText((string) ($validated['midtrans_merchant_id'] ?? ''));
        $serverKeyInput = $this->sanitizeText((string) ($validated['midtrans_server_key'] ?? ''));
        $clientKeyInput = $this->sanitizeText((string) ($validated['midtrans_client_key'] ?? ''));

        $merchantId = $merchantIdInput !== '' ? $merchantIdInput : $this->sanitizeText((string) ($midtransMethod->midtrans_merchant_id ?? ''));
        $serverKey = $serverKeyInput !== '' ? $serverKeyInput : $this->sanitizeText((string) ($midtransMethod->midtrans_server_key ?? ''));
        $clientKey = $clientKeyInput !== '' ? $clientKeyInput : $this->sanitizeText((string) ($midtransMethod->midtrans_client_key ?? ''));

        if ($midtransEnabled) {
            $errors = [];
            if ($merchantId === '') {
                $errors['midtrans_merchant_id'] = 'Merchant ID Midtrans wajib diisi.';
            }
            if ($serverKey === '') {
                $errors['midtrans_server_key'] = 'Server key Midtrans wajib diisi.';
            }
            if ($clientKey === '') {
                $errors['midtrans_client_key'] = 'Client key Midtrans wajib diisi.';
            }
            if (count($errors) > 0) {
                throw ValidationException::withMessages($errors);
            }
        }

        $midtransMethod->forceFill([
            'is_enabled' => $midtransEnabled,
            'midtrans_environment' => (string) ($validated['midtrans_environment'] ?? 'sandbox'),
            'midtrans_merchant_id' => $merchantId !== '' ? $merchantId : null,
            'midtrans_server_key' => $serverKey !== '' ? $serverKey : null,
            'midtrans_client_key' => $clientKey !== '' ? $clientKey : null,
        ])->save();

        $manualMethod->forceFill([
            'is_enabled' => $manualEnabled,
        ])->save();

        $sanitizedProviders = $this->sanitizeManualProviders((array) ($validated['manual_providers'] ?? []));
        if ($manualEnabled) {
            $activeProviderCount = collect($sanitizedProviders)
                ->filter(fn (array $provider): bool => (bool) ($provider['is_active'] ?? false))
                ->count();

            if ($activeProviderCount <= 0) {
                throw ValidationException::withMessages([
                    'manual_providers' => 'Minimal harus ada satu provider manual yang aktif.',
                ]);
            }
        }

        $this->syncManualProviders($manualMethod, $sanitizedProviders);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<int, mixed>  $providers
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeManualProviders(array $providers): array
    {
        return collect($providers)
            ->map(function (mixed $provider, int $index): array {
                $providerId = (int) data_get($provider, 'id', 0);

                return [
                    'id' => $providerId > 0 ? $providerId : null,
                    'provider_name' => $this->sanitizeText((string) data_get($provider, 'provider_name', '')),
                    'account_name' => $this->sanitizeText((string) data_get($provider, 'account_name', '')),
                    'account_number' => $this->sanitizeText((string) data_get($provider, 'account_number', '')),
                    'notes' => $this->sanitizeText((string) data_get($provider, 'notes', '')),
                    'is_active' => (bool) data_get($provider, 'is_active', true),
                    'sort_order' => $index + 1,
                ];
            })
            ->filter(fn (array $provider): bool => $provider['provider_name'] !== ''
                && $provider['account_name'] !== ''
                && $provider['account_number'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sanitizedProviders
     */
    private function syncManualProviders(PlatformPaymentMethod $manualMethod, array $sanitizedProviders): void
    {
        $existingProviders = PlatformManualPaymentProvider::query()
            ->where('payment_method_id', (int) $manualMethod->id)
            ->get()
            ->keyBy('id');

        $keptProviderIds = [];

        foreach ($sanitizedProviders as $providerPayload) {
            $providerId = (int) ($providerPayload['id'] ?? 0);

            if ($providerId > 0 && ! $existingProviders->has($providerId)) {
                throw ValidationException::withMessages([
                    'manual_providers' => 'Provider manual tidak valid.',
                ]);
            }

            if ($providerId > 0 && $existingProviders->has($providerId)) {
                /** @var PlatformManualPaymentProvider $existingProvider */
                $existingProvider = $existingProviders->get($providerId);
                $existingProvider->forceFill([
                    'provider_name' => $providerPayload['provider_name'],
                    'account_name' => $providerPayload['account_name'],
                    'account_number' => $providerPayload['account_number'],
                    'notes' => $providerPayload['notes'] !== '' ? $providerPayload['notes'] : null,
                    'is_active' => (bool) ($providerPayload['is_active'] ?? true),
                    'sort_order' => (int) ($providerPayload['sort_order'] ?? 100),
                ])->save();
                $keptProviderIds[] = (int) $existingProvider->id;
                continue;
            }

            $createdProvider = PlatformManualPaymentProvider::query()->create([
                'payment_method_id' => (int) $manualMethod->id,
                'provider_name' => $providerPayload['provider_name'],
                'account_name' => $providerPayload['account_name'],
                'account_number' => $providerPayload['account_number'],
                'notes' => $providerPayload['notes'] !== '' ? $providerPayload['notes'] : null,
                'is_active' => (bool) ($providerPayload['is_active'] ?? true),
                'sort_order' => (int) ($providerPayload['sort_order'] ?? 100),
            ]);
            $keptProviderIds[] = (int) $createdProvider->id;
        }

        PlatformManualPaymentProvider::query()
            ->where('payment_method_id', (int) $manualMethod->id)
            ->when(
                count($keptProviderIds) > 0,
                fn (Builder $query): Builder => $query->whereNotIn('id', $keptProviderIds),
            )
            ->update(['is_active' => false]);
    }

    private function firstOrCreatePaymentMethod(string $code, string $label, int $sortOrder): PlatformPaymentMethod
    {
        $method = PlatformPaymentMethod::query()
            ->where('code', $code)
            ->first();

        if ($method) {
            return $method;
        }

        return PlatformPaymentMethod::query()->create([
            'code' => $code,
            'label' => $label,
            'is_enabled' => false,
            'midtrans_environment' => $code === 'midtrans' ? 'sandbox' : null,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePaymentSettingsForPage(): array
    {
        $settings = $this->resolvePaymentSettings();

        return [
            'midtrans_enabled' => (bool) ($settings['midtrans']['enabled'] ?? false),
            'midtrans_environment' => (string) ($settings['midtrans']['environment'] ?? 'sandbox'),
            'midtrans_merchant_id' => (string) ($settings['midtrans']['merchant_id'] ?? ''),
            'midtrans_server_key' => (string) ($settings['midtrans']['server_key'] ?? ''),
            'midtrans_client_key' => (string) ($settings['midtrans']['client_key'] ?? ''),
            'midtrans_has_server_key' => (bool) ($settings['midtrans']['has_server_key'] ?? false),
            'midtrans_has_client_key' => (bool) ($settings['midtrans']['has_client_key'] ?? false),
            'manual_payment_enabled' => (bool) ($settings['manual']['enabled'] ?? false),
            'manual_providers' => $settings['manual']['providers'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePaymentSettings(): array
    {
        if (! Schema::hasTable('platform_payment_methods') || ! Schema::hasTable('platform_manual_payment_providers')) {
            return $this->defaultPaymentSettings();
        }

        return Cache::remember(self::CACHE_KEY, 3600, function (): array {
            $midtransMethod = $this->firstOrCreatePaymentMethod('midtrans', 'Midtrans', 10);
            $manualMethod = $this->firstOrCreatePaymentMethod('manual', 'Manual Transfer', 20);

            $manualProviders = PlatformManualPaymentProvider::query()
                ->where('payment_method_id', (int) $manualMethod->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'provider_name', 'account_name', 'account_number', 'notes', 'is_active', 'sort_order'])
                ->map(function (PlatformManualPaymentProvider $provider): array {
                    return [
                        'id' => (int) $provider->id,
                        'provider_name' => (string) $provider->provider_name,
                        'account_name' => (string) $provider->account_name,
                        'account_number' => (string) $provider->account_number,
                        'notes' => (string) ($provider->notes ?? ''),
                        'is_active' => (bool) $provider->is_active,
                        'sort_order' => (int) $provider->sort_order,
                    ];
                })
                ->values()
                ->all();

            return [
                'midtrans' => [
                    'enabled' => (bool) $midtransMethod->is_enabled,
                    'environment' => (string) ($midtransMethod->midtrans_environment ?: 'sandbox'),
                    'merchant_id' => (string) ($midtransMethod->midtrans_merchant_id ?? ''),
                    'server_key' => (string) ($midtransMethod->midtrans_server_key ?? ''),
                    'client_key' => (string) ($midtransMethod->midtrans_client_key ?? ''),
                    'has_server_key' => trim((string) ($midtransMethod->midtrans_server_key ?? '')) !== '',
                    'has_client_key' => trim((string) ($midtransMethod->midtrans_client_key ?? '')) !== '',
                ],
                'manual' => [
                    'enabled' => (bool) $manualMethod->is_enabled,
                    'providers' => $manualProviders,
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPaymentSettings(): array
    {
        return [
            'midtrans' => [
                'enabled' => false,
                'environment' => 'sandbox',
                'merchant_id' => '',
                'server_key' => '',
                'client_key' => '',
                'has_server_key' => false,
                'has_client_key' => false,
            ],
            'manual' => [
                'enabled' => false,
                'providers' => [],
            ],
        ];
    }

    private function sanitizeText(string $value): string
    {
        return trim(strip_tags($value));
    }
}
