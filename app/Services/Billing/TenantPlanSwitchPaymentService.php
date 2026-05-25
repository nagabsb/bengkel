<?php

namespace App\Services\Billing;

use App\Models\PlanPrice;
use App\Models\TenantPlanSwitchPayment;
use App\Models\WorkshopSubscription;
use App\Services\Platform\PlatformPaymentSettingService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantPlanSwitchPaymentService
{
    public function __construct(
        private readonly PlatformPaymentSettingService $platformPaymentSettingService,
    ) {}

    /**
     * @return array{order_id: string, redirect_url: string, token: string}
     */
    public function createMidtransCheckout(TenantPlanSwitchPayment $payment, ?Authenticatable $user): array
    {
        $gatewayConfig = $this->platformPaymentSettingService->resolveMidtransGatewayConfig();

        if (! (bool) ($gatewayConfig['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Pembayaran otomatis Midtrans belum aktif.',
            ]);
        }

        $serverKey = trim((string) ($gatewayConfig['server_key'] ?? ''));
        if ($serverKey === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Konfigurasi Midtrans belum lengkap. Hubungi admin platform.',
            ]);
        }

        $grossAmount = (int) round(max(0, (float) $payment->amount));
        if ($grossAmount <= 0) {
            throw ValidationException::withMessages([
                'plan_price_id' => 'Nominal pembayaran tidak valid.',
            ]);
        }

        $orderId = $this->resolveGatewayOrderId($payment);
        $targetPlanPrice = PlanPrice::query()
            ->with('plan')
            ->where('id', (int) $payment->target_plan_price_id)
            ->first();

        $itemName = trim((string) ($targetPlanPrice?->label ?: 'Upgrade Paket'));
        $itemName = Str::limit($itemName !== '' ? $itemName : 'Upgrade Paket', 50, '');

        $customerName = trim(strip_tags((string) data_get($user, 'name', '')));
        $customerEmail = trim(strip_tags((string) data_get($user, 'email', '')));

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'item_details' => [
                [
                    'id' => 'PLAN-'.(int) $payment->target_plan_price_id,
                    'price' => $grossAmount,
                    'quantity' => 1,
                    'name' => $itemName,
                ],
            ],
            'custom_field1' => (string) $payment->id,
            'custom_field2' => (string) $payment->tenant_id,
            'callbacks' => [
                'finish' => route('owner.workshops.index', ['tenant' => (string) $payment->tenant_id], true),
                'pending' => route('owner.workshops.index', ['tenant' => (string) $payment->tenant_id], true),
                'error' => route('owner.workshops.index', ['tenant' => (string) $payment->tenant_id], true),
            ],
        ];

        if ($customerName !== '' || $customerEmail !== '') {
            $payload['customer_details'] = array_filter([
                'first_name' => $customerName !== '' ? $customerName : 'Owner',
                'email' => $customerEmail !== '' ? $customerEmail : null,
            ], fn (mixed $value): bool => $value !== null && $value !== '');
        }

        $baseUrl = rtrim((string) ($gatewayConfig['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Konfigurasi endpoint Midtrans tidak valid.',
            ]);
        }

        $notificationUrl = route('webhooks.midtrans.notification', [], true);

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($serverKey, '')
            ->withHeaders([
                'X-Override-Notification' => $notificationUrl,
            ])
            ->post('/snap/v1/transactions', $payload)
            ->throw();

        $responseData = $response->json();
        $token = trim((string) data_get($responseData, 'token', ''));
        $redirectUrl = trim((string) data_get($responseData, 'redirect_url', ''));

        if ($token === '' || $redirectUrl === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Gagal membuat transaksi Midtrans. Silakan coba lagi.',
            ]);
        }

        $payment->forceFill([
            'payment_gateway_reference' => $orderId,
            'payment_url' => $redirectUrl,
            'status' => 'pending',
            'notes' => $this->mergeNoteFragments(
                (string) ($payment->notes ?? ''),
                'midtrans_snap_token:'.$token,
            ),
        ])->save();

        return [
            'order_id' => $orderId,
            'redirect_url' => $redirectUrl,
            'token' => $token,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleMidtransNotification(array $payload): void
    {
        $orderId = trim((string) ($payload['order_id'] ?? ''));
        if ($orderId === '') {
            return;
        }

        $payment = TenantPlanSwitchPayment::query()
            ->where('payment_gateway', 'midtrans')
            ->where('payment_gateway_reference', $orderId)
            ->latest('created_at')
            ->first();

        if (! $payment) {
            return;
        }

        $gatewayConfig = $this->platformPaymentSettingService->resolveMidtransGatewayConfig();
        $serverKey = trim((string) ($gatewayConfig['server_key'] ?? ''));
        if ($serverKey === '') {
            throw ValidationException::withMessages([
                'signature_key' => 'Konfigurasi server key Midtrans belum lengkap.',
            ]);
        }

        $statusCode = trim((string) ($payload['status_code'] ?? ''));
        $grossAmount = trim((string) ($payload['gross_amount'] ?? ''));
        $signatureKey = trim((string) ($payload['signature_key'] ?? ''));

        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
        if ($signatureKey === '' || ! hash_equals($expectedSignature, $signatureKey)) {
            throw ValidationException::withMessages([
                'signature_key' => 'Signature Midtrans tidak valid.',
            ]);
        }

        $this->syncPaymentFromGatewayPayload($payment, $payload);
    }

    public function syncMidtransPaymentByOrderId(string $tenantId, string $orderId): ?TenantPlanSwitchPayment
    {
        $normalizedOrderId = trim($orderId);
        if ($normalizedOrderId === '') {
            return null;
        }

        $payment = TenantPlanSwitchPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_gateway', 'midtrans')
            ->where('payment_gateway_reference', $normalizedOrderId)
            ->latest('created_at')
            ->first();

        if (! $payment) {
            return null;
        }

        if ((string) ($payment->status ?? '') !== 'pending') {
            return $payment;
        }

        if (! $this->acquireMidtransSyncLock($payment)) {
            return TenantPlanSwitchPayment::query()
                ->where('id', (string) $payment->id)
                ->first();
        }

        $gatewayConfig = $this->platformPaymentSettingService->resolveMidtransGatewayConfig();
        $serverKey = trim((string) ($gatewayConfig['server_key'] ?? ''));
        if ($serverKey === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Konfigurasi Midtrans belum lengkap. Hubungi admin platform.',
            ]);
        }

        $apiBaseUrl = $this->resolveMidtransApiBaseUrl((string) ($gatewayConfig['environment'] ?? 'sandbox'));

        $response = Http::baseUrl($apiBaseUrl)
            ->acceptJson()
            ->withBasicAuth($serverKey, '')
            ->get('/v2/'.$normalizedOrderId.'/status')
            ->throw();

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $payloadOrderId = trim((string) data_get($payload, 'order_id', ''));
        if ($payloadOrderId === '') {
            $payload['order_id'] = $normalizedOrderId;
        }

        $this->syncPaymentFromGatewayPayload($payment, $payload);

        return TenantPlanSwitchPayment::query()
            ->where('id', (string) $payment->id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncPaymentFromGatewayPayload(TenantPlanSwitchPayment $payment, array $payload): void
    {
        $transactionStatus = strtolower(trim((string) ($payload['transaction_status'] ?? '')));
        $fraudStatus = strtolower(trim((string) ($payload['fraud_status'] ?? '')));

        $nextStatus = match ($transactionStatus) {
            'settlement' => 'paid',
            'capture' => $fraudStatus === '' || $fraudStatus === 'accept' ? 'paid' : 'pending',
            'pending' => 'pending',
            'expire' => 'expired',
            'cancel' => 'cancelled',
            'deny', 'failure' => 'failed',
            default => 'pending',
        };

        DB::transaction(function () use ($payment, $payload, $nextStatus): void {
            $lockedPayment = TenantPlanSwitchPayment::query()
                ->where('id', (string) $payment->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPayment) {
                return;
            }

            $currentStatus = (string) ($lockedPayment->status ?? '');
            if ($currentStatus === 'paid') {
                return;
            }

            $gatewayReference = trim((string) ($payload['order_id'] ?? ''));
            $transactionId = trim((string) ($payload['transaction_id'] ?? ''));
            $paymentType = trim((string) ($payload['payment_type'] ?? ''));

            $noteFragments = collect([
                $lockedPayment->notes,
                $transactionId !== '' ? 'midtrans_tx_id:'.$transactionId : null,
                $paymentType !== '' ? 'payment_type:'.$paymentType : null,
            ])
                ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                ->unique()
                ->values()
                ->all();

            $lockedPayment->forceFill([
                'status' => $nextStatus,
                'payment_gateway_reference' => $gatewayReference !== '' ? $gatewayReference : $lockedPayment->payment_gateway_reference,
                'paid_at' => $nextStatus === 'paid' ? now() : $lockedPayment->paid_at,
                'notes' => count($noteFragments) > 0 ? implode(' | ', $noteFragments) : null,
            ])->save();

            if ($nextStatus !== 'paid') {
                return;
            }

            $this->activateTenantSubscriptionFromPayment($lockedPayment);

            TenantPlanSwitchPayment::query()
                ->where('tenant_id', (string) $lockedPayment->tenant_id)
                ->where('id', '!=', (string) $lockedPayment->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'notes' => 'Dibatalkan otomatis karena pembayaran lain sukses.',
                ]);
        });
    }

    private function activateTenantSubscriptionFromPayment(TenantPlanSwitchPayment $payment): void
    {
        $planPrice = PlanPrice::query()
            ->with('plan')
            ->where('id', (int) $payment->target_plan_price_id)
            ->where('is_active', true)
            ->first();

        if (! $planPrice || ! $planPrice->plan || ! $planPrice->plan->is_active) {
            Log::warning('Plan target untuk switch payment tidak valid.', [
                'tenant_id' => (string) $payment->tenant_id,
                'payment_id' => (string) $payment->id,
                'target_plan_price_id' => (int) $payment->target_plan_price_id,
            ]);

            return;
        }

        $currentSubscription = WorkshopSubscription::query()
            ->where('tenant_id', (string) $payment->tenant_id)
            ->whereIn('status', ['trial', 'active'])
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->first();

        $durationMonths = max(1, (int) $planPrice->duration_months);
        $extensionBaseAt = $this->resolveSubscriptionExtensionBaseAt($currentSubscription);
        $extendedExpiredAt = $extensionBaseAt->copy()->addMonthsNoOverflow($durationMonths);

        if ($currentSubscription) {
            $currentStartedAt = $currentSubscription->started_at?->copy() ?: now();

            $currentSubscription->forceFill([
                'plan_price_id' => (int) $planPrice->id,
                'status' => 'active',
                'started_at' => $currentStartedAt,
                'expired_at' => $extendedExpiredAt,
                'trial_ends_at' => null,
            ])->save();

            return;
        }

        WorkshopSubscription::query()->create([
            'tenant_id' => (string) $payment->tenant_id,
            'plan_price_id' => (int) $planPrice->id,
            'status' => 'active',
            'started_at' => now(),
            'expired_at' => now()->addMonthsNoOverflow($durationMonths),
            'trial_ends_at' => null,
        ]);
    }

    private function resolveSubscriptionExtensionBaseAt(?WorkshopSubscription $currentSubscription): Carbon
    {
        $now = now();
        if (! $currentSubscription) {
            return $now;
        }

        $candidates = [
            $currentSubscription->expired_at?->copy(),
            $currentSubscription->trial_ends_at?->copy(),
            $now,
        ];

        $latest = $now;
        foreach ($candidates as $candidate) {
            if (! $candidate instanceof Carbon) {
                continue;
            }

            if ($candidate->gt($latest)) {
                $latest = $candidate;
            }
        }

        return $latest->copy();
    }

    private function resolveGatewayOrderId(TenantPlanSwitchPayment $payment): string
    {
        $existingReference = trim((string) ($payment->payment_gateway_reference ?? ''));
        if ($existingReference !== '') {
            return $existingReference;
        }

        $candidate = Str::upper('SWITCH-'.$payment->id);
        $candidate = preg_replace('/[^A-Z0-9\-]/', '-', $candidate);
        $normalizedCandidate = is_string($candidate) ? trim($candidate, '-') : '';
        if ($normalizedCandidate === '') {
            $normalizedCandidate = 'SWITCH-'.Str::upper(Str::random(12));
        }

        return Str::limit($normalizedCandidate, 50, '');
    }

    private function resolveMidtransApiBaseUrl(string $environment): string
    {
        return strtolower(trim($environment)) === 'production'
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    private function acquireMidtransSyncLock(TenantPlanSwitchPayment $payment): bool
    {
        $cacheKey = 'tenant_plan_switch_payment.midtrans.sync_lock.'.$payment->id;

        return Cache::add($cacheKey, now()->getTimestamp(), now()->addSeconds(20));
    }

    private function mergeNoteFragments(string $existingNotes, string $fragment): string
    {
        $parts = collect([
            $existingNotes,
            $fragment,
        ])
            ->flatMap(function (mixed $value): array {
                if (! is_string($value) || trim($value) === '') {
                    return [];
                }

                return preg_split('/\s*\|\s*/', $value) ?: [];
            })
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        return Str::limit(implode(' | ', $parts), 500, '');
    }
}
