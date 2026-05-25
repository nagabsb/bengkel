<?php

namespace App\Services\Owner;

use App\Models\PlanPrice;
use App\Models\Tenant;
use App\Models\TenantPlanSwitchPayment;
use App\Models\Workshop;
use App\Models\WorkshopSubscription;
use App\Services\Billing\TenantPlanSwitchPaymentService;
use App\Services\Platform\PlatformPaymentSettingService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerWorkshopService
{
    public function __construct(
        private readonly OwnerMenuService $ownerMenuService,
        private readonly PlatformPaymentSettingService $platformPaymentSettingService,
        private readonly TenantPlanSwitchPaymentService $tenantPlanSwitchPaymentService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(
        Request $request,
        string $tenantId,
        TenantPlanResolver $planResolver,
        ?Authenticatable $user,
    ): array {
        $workshopSearch = trim((string) $request->query('workshop_search', ''));
        $workshopSortBy = $this->resolveSortBy((string) $request->query('workshop_sort_by', 'created_at'));
        $workshopSortDir = $this->resolveSortDirection((string) $request->query('workshop_sort_dir', 'desc'));
        $workshopPerPage = $this->resolvePerPage((int) $request->query('workshop_per_page', 10));
        $workshopCursor = trim((string) $request->query('workshop_cursor', ''));

        $package = $planResolver->forTenantId($tenantId);
        $planId = data_get($package, 'plan.id');

        $menuTree = $this->ownerMenuService->buildOwnerMenuTree(
            $tenantId,
            $planId,
            hasPlanMenuTable: Schema::hasTable('plan_menu'),
        );

        $menuItems = $this->ownerMenuService->buildSidebarMenuItems(
            $menuTree,
            $tenantId,
            $user,
            $this->resolveCurrentUri($request),
        );

        $workshopPayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $workshopPerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $workshopSummary = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'limit' => null,
            'remaining' => null,
        ];
        $planOptions = $this->resolvePlanOptions();
        $paymentOptions = $this->platformPaymentSettingService->resolveOwnerPaymentOptions();
        $pendingMidtransPayment = $this->resolvePendingMidtransPayment($tenantId);

        if (Schema::hasTable('workshops')) {
            $hasWorkshopPhoneColumn = Schema::hasColumn('workshops', 'phone');
            $hasWorkshopAddressColumn = Schema::hasColumn('workshops', 'address');

            $summaryQuery = Workshop::query()
                ->where('tenant_id', $tenantId);

            $totalWorkshops = (int) (clone $summaryQuery)->count();
            $activeWorkshops = (int) (clone $summaryQuery)
                ->where('is_active', true)
                ->count();

            $planWorkshopLimit = data_get($package, 'plan.max_workshops');
            $normalizedLimit = is_numeric($planWorkshopLimit) && (int) $planWorkshopLimit > 0
                ? (int) $planWorkshopLimit
                : null;

            $workshopSummary = [
                'total' => $totalWorkshops,
                'active' => $activeWorkshops,
                'inactive' => max($totalWorkshops - $activeWorkshops, 0),
                'limit' => $normalizedLimit,
                'remaining' => $normalizedLimit === null
                    ? null
                    : max($normalizedLimit - $totalWorkshops, 0),
            ];

            $sortableColumnMap = [
                'name' => 'workshops.name',
                'code' => 'workshops.code',
                'is_active' => 'workshops.is_active',
                'created_at' => 'workshops.created_at',
            ];

            if ($hasWorkshopPhoneColumn) {
                $sortableColumnMap['phone'] = 'workshops.phone';
            }

            if ($hasWorkshopAddressColumn) {
                $sortableColumnMap['address'] = 'workshops.address';
            }

            $sortableColumn = $sortableColumnMap[$workshopSortBy] ?? 'workshops.created_at';
            $selectedColumns = ['workshops.id', 'workshops.name', 'workshops.code', 'workshops.is_active', 'workshops.created_at', 'workshops.updated_at'];

            if ($hasWorkshopPhoneColumn) {
                $selectedColumns[] = 'workshops.phone';
            }

            if ($hasWorkshopAddressColumn) {
                $selectedColumns[] = 'workshops.address';
            }

            $workshopPaginator = $this->cursorPaginateWithFallback(
                Workshop::query()
                    ->where('tenant_id', $tenantId)
                    ->when($workshopSearch !== '', function (Builder $query) use ($workshopSearch, $hasWorkshopPhoneColumn, $hasWorkshopAddressColumn): void {
                        $query->where(function (Builder $nestedQuery) use ($workshopSearch, $hasWorkshopPhoneColumn, $hasWorkshopAddressColumn): void {
                            $nestedQuery
                                ->where('name', 'like', "%{$workshopSearch}%")
                                ->orWhere('code', 'like', "%{$workshopSearch}%");

                            if ($hasWorkshopPhoneColumn) {
                                $nestedQuery->orWhere('phone', 'like', "%{$workshopSearch}%");
                            }

                            if ($hasWorkshopAddressColumn) {
                                $nestedQuery->orWhere('address', 'like', "%{$workshopSearch}%");
                            }
                        });
                    })
                    ->orderByRaw('CASE WHEN workshops.id = ? THEN 0 ELSE 1 END', [$tenantId])
                    ->orderBy($sortableColumn, $workshopSortDir)
                    ->orderBy('workshops.id', $workshopSortDir),
                $workshopPerPage,
                $selectedColumns,
                $workshopCursor,
            );

            $workshopRows = collect($workshopPaginator->items())
                ->map(function (Workshop $workshop) use ($tenantId, $hasWorkshopPhoneColumn, $hasWorkshopAddressColumn): array {
                    return [
                        'id' => (string) $workshop->id,
                        'name' => (string) $workshop->name,
                        'code' => (string) $workshop->code,
                        'phone' => $hasWorkshopPhoneColumn ? (string) ($workshop->getAttribute('phone') ?? '') : '',
                        'address' => $hasWorkshopAddressColumn ? (string) ($workshop->getAttribute('address') ?? '') : '',
                        'is_active' => (bool) $workshop->is_active,
                        'is_primary' => (string) $workshop->id === $tenantId,
                        'created_at' => $workshop->created_at,
                        'updated_at' => $workshop->updated_at,
                    ];
                })
                ->values();

            $workshopPayload = [
                'mode' => 'cursor',
                'data' => $workshopRows->all(),
                'per_page' => $workshopPaginator->perPage(),
                'total' => $totalWorkshops,
                'from' => $workshopRows->isEmpty() ? 0 : 1,
                'to' => $workshopRows->count(),
                'current_cursor' => $workshopPaginator->cursor()?->encode(),
                'next_cursor' => $workshopPaginator->nextCursor()?->encode(),
                'prev_cursor' => $workshopPaginator->previousCursor()?->encode(),
                'has_more_pages' => $workshopPaginator->hasMorePages(),
            ];
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'planOptions' => $planOptions,
            'paymentOptions' => $paymentOptions,
            'pendingMidtransPayment' => $pendingMidtransPayment,
            'workshops' => $workshopPayload,
            'workshopFilters' => [
                'search' => $workshopSearch,
                'sort_by' => $workshopSortBy,
                'sort_dir' => $workshopSortDir,
                'per_page' => $workshopPerPage,
                'cursor' => $workshopPayload['current_cursor'],
            ],
            'workshopSummary' => $workshopSummary,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createWorkshop(string $tenantId, array $validated, TenantPlanResolver $planResolver): void
    {
        $this->assertWorkshopsTableReady('create_workshop', 'Tabel bengkel belum siap.');
        $this->assertCreateLimit($tenantId, $planResolver);

        DB::transaction(function () use ($tenantId, $validated): void {
            $payload = $this->normalizeWorkshopPayload($tenantId, $validated);
            $this->assertWorkshopNameAvailable($tenantId, (string) ($payload['name'] ?? ''));
            $this->createWorkshopWithUniqueCodeRetry($tenantId, $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateWorkshop(string $tenantId, string $workshopId, array $validated): void
    {
        $this->assertWorkshopsTableReady('update_workshop', 'Tabel bengkel belum siap.');

        $workshop = $this->findTenantWorkshopOrFail($tenantId, $workshopId, 'update_workshop');

        DB::transaction(function () use ($tenantId, $workshop, $validated): void {
            $name = trim((string) $validated['name']);
            $requestedCode = $this->normalizeWorkshopCode((string) ($validated['code'] ?? ''));
            $phone = $this->normalizeRequiredString($validated['phone'] ?? '');
            $address = $this->normalizeNullableString($validated['address'] ?? null);
            $isActive = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;
            $this->assertWorkshopNameAvailable($tenantId, $name, (string) $workshop->id);

            $this->updateWorkshopWithUniqueCodeRetry(
                $tenantId,
                $workshop,
                $name,
                $requestedCode,
                $phone,
                $address,
                $isActive,
            );
        });
    }

    public function deleteWorkshop(string $tenantId, string $workshopId): void
    {
        $this->assertWorkshopsTableReady('delete_workshop', 'Tabel bengkel belum siap.');

        $workshop = $this->findTenantWorkshopOrFail($tenantId, $workshopId, 'delete_workshop');

        if ((string) $workshop->id === $tenantId) {
            throw ValidationException::withMessages([
                'delete_workshop' => 'Bengkel utama tenant tidak bisa dihapus.',
            ]);
        }

        $totalWorkshops = (int) Workshop::query()
            ->where('tenant_id', $tenantId)
            ->count();

        if ($totalWorkshops <= 1) {
            throw ValidationException::withMessages([
                'delete_workshop' => 'Minimal harus ada satu bengkel aktif untuk tenant.',
            ]);
        }

        DB::transaction(function () use ($workshop): void {
            $workshop->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function switchPlan(string $tenantId, array $validated, ?Authenticatable $user): array
    {
        $this->assertPlanTablesReady('switch_plan', 'Data pricing plan belum siap.');

        $planPriceId = (int) ($validated['plan_price_id'] ?? 0);
        $paymentMethod = $this->normalizePaymentMethod((string) ($validated['payment_method'] ?? ''));
        $manualProviderId = (int) ($validated['manual_provider_id'] ?? 0);
        if ($planPriceId <= 0) {
            throw ValidationException::withMessages([
                'plan_price_id' => 'Plan price tidak valid.',
            ]);
        }
        if ($paymentMethod === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran tidak valid.',
            ]);
        }

        $result = DB::transaction(function () use ($tenantId, $planPriceId, $paymentMethod, $manualProviderId, $user): array {
            $result = $this->createPlanSwitchPaymentRequest(
                $tenantId,
                $planPriceId,
                $paymentMethod,
                $manualProviderId,
                $user,
            );

            if ($result['status'] === 'exists') {
                return [
                    'status' => 'exists',
                    'status_message' => 'Anda sudah memiliki tagihan upgrade plan yang masih pending. Silakan selesaikan pembayaran terlebih dahulu.',
                    'payment' => $result['payment'],
                ];
            }

            if ($paymentMethod === 'manual') {
                return [
                    'status' => 'created',
                    'status_message' => 'Permintaan upgrade plan dibuat. Silakan transfer manual sesuai instruksi lalu tunggu verifikasi admin.',
                    'payment_redirect_url' => null,
                    'payment_snap_token' => null,
                    'payment_open_mode' => null,
                    'payment_order_id' => null,
                ];
            }

            return [
                'status' => 'created',
                'status_message' => 'Permintaan upgrade plan berhasil dibuat. Lanjutkan pembayaran melalui Midtrans.',
                'payment_redirect_url' => null,
                'payment_snap_token' => null,
                'payment_open_mode' => null,
                'payment_order_id' => null,
                'payment' => $result['payment'],
            ];
        });

        if ($paymentMethod !== 'midtrans') {
            return $result;
        }

        /** @var TenantPlanSwitchPayment|null $payment */
        $payment = $result['payment'] ?? null;
        if (! $payment) {
            return $result;
        }

        if ((string) $payment->payment_method !== 'midtrans') {
            return [
                'status_message' => 'Anda sudah memiliki tagihan pending dengan metode lain. Selesaikan atau batalkan dulu tagihan tersebut.',
                'payment_redirect_url' => (string) ($payment->payment_url ?? ''),
                'payment_snap_token' => null,
                'payment_open_mode' => null,
                'payment_order_id' => null,
            ];
        }

        $existingRedirectUrl = trim((string) ($payment->payment_url ?? ''));
        $existingSnapToken = $this->resolveMidtransSnapTokenFromPayment($payment);
        $existingOrderId = trim((string) ($payment->payment_gateway_reference ?? ''));
        if ((string) ($result['status'] ?? '') === 'exists' && $existingRedirectUrl !== '') {
            return [
                'status_message' => 'Tagihan Midtrans pending ditemukan. Lanjutkan pembayaran.',
                'payment_redirect_url' => $existingRedirectUrl,
                'payment_snap_token' => $existingSnapToken !== '' ? $existingSnapToken : null,
                'payment_open_mode' => 'redirect',
                'payment_order_id' => $existingOrderId !== '' ? $existingOrderId : null,
            ];
        }

        try {
            $checkout = $this->tenantPlanSwitchPaymentService->createMidtransCheckout($payment, $user);
        } catch (ValidationException $exception) {
            $payment->forceFill([
                'status' => 'failed',
                'notes' => 'Gagal membuat checkout Midtrans: '.$exception->getMessage(),
            ])->save();

            throw $exception;
        } catch (\Throwable $exception) {
            $payment->forceFill([
                'status' => 'failed',
                'notes' => 'Gagal membuat checkout Midtrans.',
            ])->save();

            throw ValidationException::withMessages([
                'payment_method' => 'Gagal membuat transaksi Midtrans. Silakan coba lagi.',
            ]);
        }

        return [
            'status_message' => 'Transaksi Midtrans berhasil dibuat. Lanjutkan pembayaran sekarang.',
            'payment_redirect_url' => (string) ($checkout['redirect_url'] ?? ''),
            'payment_snap_token' => (string) ($checkout['token'] ?? ''),
            'payment_open_mode' => 'modal',
            'payment_order_id' => (string) ($checkout['order_id'] ?? ''),
        ];
    }

    public function resolvePendingMidtransRedirectUrl(string $tenantId): string
    {
        $pendingPayment = $this->resolvePendingMidtransPayment($tenantId);

        return trim((string) data_get($pendingPayment, 'redirect_url', ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolvePendingMidtransPaymentData(string $tenantId): ?array
    {
        return $this->resolvePendingMidtransPayment($tenantId);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeWorkshopPayload(string $tenantId, array $validated): array
    {
        return [
            'tenant_id' => $tenantId,
            'name' => trim((string) $validated['name']),
            'code' => $this->normalizeWorkshopCode((string) ($validated['code'] ?? '')),
            'phone' => $this->normalizeRequiredString($validated['phone'] ?? ''),
            'address' => $this->normalizeNullableString($validated['address'] ?? null),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ];
    }

    private function normalizeWorkshopCode(string $code): string
    {
        $normalized = strtoupper(trim($code));
        $normalized = preg_replace('/[^A-Z0-9-]/', '', $normalized);

        return is_string($normalized) ? $normalized : '';
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeRequiredString(mixed $value): string
    {
        return trim((string) $value);
    }

    private function resolveUniqueWorkshopCode(
        string $tenantId,
        string $name,
        string $requestedCode,
        ?string $ignoreWorkshopId = null,
        string $fallbackCode = '',
    ): string {
        $normalizedRequestedCode = $this->normalizeWorkshopCode($requestedCode);
        if ($normalizedRequestedCode !== '' && ! $this->isWorkshopCodeTaken($normalizedRequestedCode, $ignoreWorkshopId)) {
            return $normalizedRequestedCode;
        }

        $normalizedFallbackCode = $this->normalizeWorkshopCode($fallbackCode);
        if ($normalizedFallbackCode !== '' && ! $this->isWorkshopCodeTaken($normalizedFallbackCode, $ignoreWorkshopId)) {
            return $normalizedFallbackCode;
        }

        $baseCode = $this->buildWorkshopCodeBase($name);
        if (! $this->isWorkshopCodeTaken($baseCode, $ignoreWorkshopId)) {
            return $baseCode;
        }

        for ($sequence = 2; $sequence <= 99; $sequence++) {
            $candidateCode = $this->appendWorkshopCodeSuffix($baseCode, (string) $sequence);
            if (! $this->isWorkshopCodeTaken($candidateCode, $ignoreWorkshopId)) {
                return $candidateCode;
            }
        }

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $candidateCode = $this->appendWorkshopCodeSuffix($baseCode, Str::upper(Str::random(4)));
            if (! $this->isWorkshopCodeTaken($candidateCode, $ignoreWorkshopId)) {
                return $candidateCode;
            }
        }

        throw ValidationException::withMessages([
            'code' => 'Gagal membuat kode bengkel unik. Silakan coba lagi.',
        ]);
    }

    private function buildWorkshopCodeBase(string $name): string
    {
        $normalizedName = (string) Str::of($name)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9\s-]+/', ' ')
            ->replaceMatches('/[-_]+/', ' ')
            ->squish();

        $segments = collect(explode(' ', $normalizedName))
            ->filter(fn (string $word): bool => $word !== '')
            ->values()
            ->take(4)
            ->map(fn (string $word, int $index): string => substr($word, 0, $index === 0 ? 4 : 3))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->values();

        $baseCode = $segments->implode('-');

        if ($baseCode === '') {
            return 'WS';
        }

        $baseCode = trim(substr($baseCode, 0, 20), '-');

        return $baseCode !== '' ? $baseCode : 'WS';
    }

    private function appendWorkshopCodeSuffix(string $baseCode, string $suffix): string
    {
        $normalizedSuffix = $this->normalizeWorkshopCode($suffix);
        if ($normalizedSuffix === '') {
            $normalizedSuffix = 'X';
        }

        $availableBaseLength = max(1, 20 - strlen($normalizedSuffix) - 1);
        $truncatedBaseCode = substr($baseCode, 0, $availableBaseLength);

        return $truncatedBaseCode.'-'.$normalizedSuffix;
    }

    private function isWorkshopCodeTaken(string $code, ?string $ignoreWorkshopId = null): bool
    {
        return Workshop::query()
            ->when(
                is_string($ignoreWorkshopId) && $ignoreWorkshopId !== '',
                fn (Builder $builder): Builder => $builder->where('id', '!=', $ignoreWorkshopId),
            )
            ->where('code', $code)
            ->exists();
    }

    private function assertWorkshopNameAvailable(string $tenantId, string $name, ?string $ignoreWorkshopId = null): void
    {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            return;
        }

        if (! $this->isWorkshopNameTaken($tenantId, $normalizedName, $ignoreWorkshopId)) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => 'Nama bengkel sudah digunakan di tenant ini.',
        ]);
    }

    private function isWorkshopNameTaken(string $tenantId, string $name, ?string $ignoreWorkshopId = null): bool
    {
        return Workshop::query()
            ->where('tenant_id', $tenantId)
            ->when(
                is_string($ignoreWorkshopId) && $ignoreWorkshopId !== '',
                fn (Builder $builder): Builder => $builder->where('id', '!=', $ignoreWorkshopId),
            )
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($name))])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createWorkshopWithUniqueCodeRetry(string $tenantId, array $payload): void
    {
        $name = (string) ($payload['name'] ?? '');
        $requestedCode = $this->normalizeWorkshopCode((string) ($payload['code'] ?? ''));

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $payload['code'] = $this->resolveUniqueWorkshopCode(
                $tenantId,
                $name,
                $attempt === 0 ? $requestedCode : '',
            );

            try {
                Workshop::query()->create($payload);

                return;
            } catch (QueryException $exception) {
                if (! $this->isDuplicateWorkshopCodeViolation($exception)) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'code' => 'Kode bengkel bentrok dengan data lain. Silakan coba lagi.',
        ]);
    }

    private function updateWorkshopWithUniqueCodeRetry(
        string $tenantId,
        Workshop $workshop,
        string $name,
        string $requestedCode,
        string $phone,
        ?string $address,
        bool $isActive,
    ): void {
        $fallbackCode = (string) $workshop->code;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $resolvedCode = $this->resolveUniqueWorkshopCode(
                $tenantId,
                $name,
                $attempt === 0 ? $requestedCode : '',
                (string) $workshop->id,
                $attempt === 0 ? $fallbackCode : '',
            );

            try {
                $workshop->forceFill([
                    'name' => $name,
                    'code' => $resolvedCode,
                    'phone' => $phone,
                    'address' => $address,
                    'is_active' => $isActive,
                ])->save();

                return;
            } catch (QueryException $exception) {
                if (! $this->isDuplicateWorkshopCodeViolation($exception)) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'code' => 'Kode bengkel bentrok dengan data lain. Silakan coba lagi.',
        ]);
    }

    private function isDuplicateWorkshopCodeViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        if ($sqlState !== '23000') {
            return false;
        }

        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'workshops.code')
            || str_contains($message, 'workshops_code_unique')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint failed');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolvePlanOptions(): array
    {
        if (! Schema::hasTable('plan_prices') || ! Schema::hasTable('plans')) {
            return [];
        }

        return PlanPrice::query()
            ->with('plan')
            ->where('is_active', true)
            ->whereHas('plan', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('price')
            ->orderBy('duration_months')
            ->orderBy('id')
            ->get(['id', 'plan_id', 'label', 'duration_months', 'price', 'discount_pct'])
            ->map(function (PlanPrice $planPrice): array {
                return [
                    'id' => (int) $planPrice->id,
                    'label' => (string) $planPrice->label,
                    'duration_months' => (int) $planPrice->duration_months,
                    'amount' => (float) $planPrice->price,
                    'discount_pct' => (int) $planPrice->discount_pct,
                    'plan' => [
                        'id' => (int) ($planPrice->plan?->id ?? 0),
                        'name' => (string) ($planPrice->plan?->name ?? '-'),
                        'slug' => (string) ($planPrice->plan?->slug ?? '-'),
                        'max_workshops' => (int) ($planPrice->plan?->max_workshops ?? 0),
                        'max_users_per_ws' => (int) ($planPrice->plan?->max_users_per_ws ?? 0),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function normalizePaymentMethod(string $paymentMethod): string
    {
        $normalized = strtolower(trim($paymentMethod));

        return in_array($normalized, ['midtrans', 'manual'], true)
            ? $normalized
            : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePendingMidtransPayment(string $tenantId): ?array
    {
        if (! Schema::hasTable('tenant_plan_switch_payments')) {
            return null;
        }

        $payment = TenantPlanSwitchPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_method', 'midtrans')
            ->where('status', 'pending')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->latest('created_at')
            ->first();

        if (! $payment) {
            return null;
        }

        $redirectUrl = trim((string) ($payment->payment_url ?? ''));
        if ($redirectUrl === '') {
            return null;
        }

        return [
            'id' => (string) $payment->id,
            'redirect_url' => $redirectUrl,
            'order_id' => trim((string) ($payment->payment_gateway_reference ?? '')),
            'expires_at' => $payment->expires_at,
        ];
    }

    private function resolveMidtransSnapTokenFromPayment(TenantPlanSwitchPayment $payment): string
    {
        if ((string) ($payment->payment_method ?? '') !== 'midtrans') {
            return '';
        }

        $tokenFromNotes = $this->extractMidtransSnapTokenFromNotes((string) ($payment->notes ?? ''));
        if ($tokenFromNotes !== '') {
            return $tokenFromNotes;
        }

        return $this->extractMidtransSnapTokenFromRedirectUrl((string) ($payment->payment_url ?? ''));
    }

    private function extractMidtransSnapTokenFromNotes(string $notes): string
    {
        if ($notes === '') {
            return '';
        }

        if (preg_match('/midtrans_snap_token:([A-Za-z0-9._-]+)/', $notes, $matches) !== 1) {
            return '';
        }

        return trim((string) ($matches[1] ?? ''));
    }

    private function extractMidtransSnapTokenFromRedirectUrl(string $redirectUrl): string
    {
        $normalizedUrl = trim($redirectUrl);
        if ($normalizedUrl === '') {
            return '';
        }

        $path = (string) parse_url($normalizedUrl, PHP_URL_PATH);
        if ($path === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (count($segments) < 1) {
            return '';
        }

        $token = trim((string) end($segments));
        if ($token === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $token)) {
            return '';
        }

        return $token;
    }

    /**
     * @return array{status: string, payment: TenantPlanSwitchPayment}
     */
    private function createPlanSwitchPaymentRequest(
        string $tenantId,
        int $planPriceId,
        string $paymentMethod,
        int $manualProviderId,
        ?Authenticatable $user,
    ): array {
        $tenantExists = Tenant::query()
            ->where('id', $tenantId)
            ->where('is_active', true)
            ->exists();

        if (! $tenantExists) {
            throw ValidationException::withMessages([
                'switch_plan' => 'Tenant tidak aktif atau tidak ditemukan.',
            ]);
        }

        $paymentOptions = $this->platformPaymentSettingService->resolveOwnerPaymentOptions();
        $availableMethods = collect($paymentOptions['available_methods'] ?? [])
            ->map(fn (mixed $method): string => strtolower(trim((string) $method)))
            ->filter(fn (string $method): bool => in_array($method, ['midtrans', 'manual'], true))
            ->values()
            ->all();

        if (! in_array($paymentMethod, $availableMethods, true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran belum aktif. Hubungi admin platform.',
            ]);
        }

        $planPrice = PlanPrice::query()
            ->with('plan')
            ->where('id', $planPriceId)
            ->where('is_active', true)
            ->first();

        if (! $planPrice || ! $planPrice->plan || ! $planPrice->plan->is_active) {
            throw ValidationException::withMessages([
                'plan_price_id' => 'Plan price tidak valid atau sudah nonaktif.',
            ]);
        }

        $maxWorkshops = (int) ($planPrice->plan->max_workshops ?? 0);
        if ($maxWorkshops > 0) {
            $currentWorkshopCount = (int) Workshop::query()
                ->where('tenant_id', $tenantId)
                ->count();

            if ($currentWorkshopCount > $maxWorkshops) {
                $excessWorkshopCount = $currentWorkshopCount - $maxWorkshops;
                throw ValidationException::withMessages([
                    'plan_price_id' => "Anda saat ini memiliki {$currentWorkshopCount} bengkel. Untuk downgrade ke paket ini (maksimal {$maxWorkshops} bengkel), silakan hapus {$excessWorkshopCount} bengkel terlebih dahulu.",
                ]);
            }
        }

        $currentSubscription = WorkshopSubscription::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['trial', 'active'])
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->first();

        if ($currentSubscription && (int) $currentSubscription->plan_price_id === $planPriceId) {
            throw ValidationException::withMessages([
                'plan_price_id' => 'Plan ini sudah menjadi paket aktif tenant.',
            ]);
        }

        $pendingPayment = TenantPlanSwitchPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->latest('created_at')
            ->first();

        if ($pendingPayment) {
            return [
                'status' => 'exists',
                'payment' => $pendingPayment,
            ];
        }

        $manualProviders = collect(data_get($paymentOptions, 'manual.providers', []))
            ->map(function (mixed $provider): array {
                return [
                    'id' => (int) data_get($provider, 'id'),
                    'provider_name' => trim((string) data_get($provider, 'provider_name', '')),
                    'account_name' => trim((string) data_get($provider, 'account_name', '')),
                    'account_number' => trim((string) data_get($provider, 'account_number', '')),
                    'notes' => trim((string) data_get($provider, 'notes', '')),
                    'is_active' => (bool) data_get($provider, 'is_active', false),
                ];
            })
            ->filter(fn (array $provider): bool => $provider['id'] > 0 && $provider['is_active'])
            ->values();

        $selectedManualProvider = null;
        if ($paymentMethod === 'manual') {
            if ($manualProviderId <= 0) {
                throw ValidationException::withMessages([
                    'manual_provider_id' => 'Provider pembayaran manual wajib dipilih.',
                ]);
            }

            $selectedManualProvider = $manualProviders
                ->first(fn (array $provider): bool => (int) $provider['id'] === $manualProviderId);

            if (! is_array($selectedManualProvider)) {
                throw ValidationException::withMessages([
                    'manual_provider_id' => 'Provider manual tidak valid atau tidak aktif.',
                ]);
            }
        }

        $amount = $this->calculatePlanPriceAmount($planPrice);
        $requestedByUserId = is_object($user) ? trim((string) data_get($user, 'id')) : '';

        $payment = TenantPlanSwitchPayment::query()->create([
            'tenant_id' => $tenantId,
            'requested_by_user_id' => $requestedByUserId !== '' ? $requestedByUserId : null,
            'current_plan_price_id' => $currentSubscription ? (int) $currentSubscription->plan_price_id : null,
            'target_plan_price_id' => (int) $planPrice->id,
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'amount' => $amount,
            'currency' => 'IDR',
            'payment_gateway' => $paymentMethod === 'midtrans' ? 'midtrans' : null,
            'payment_gateway_reference' => null,
            'payment_url' => null,
            'manual_provider_id' => $paymentMethod === 'manual' ? (int) ($selectedManualProvider['id'] ?? 0) : null,
            'manual_provider_name' => $paymentMethod === 'manual' ? (string) ($selectedManualProvider['provider_name'] ?? '') : null,
            'manual_account_name' => $paymentMethod === 'manual' ? (string) ($selectedManualProvider['account_name'] ?? '') : null,
            'manual_account_number' => $paymentMethod === 'manual' ? (string) ($selectedManualProvider['account_number'] ?? '') : null,
            'notes' => $paymentMethod === 'manual' ? (string) ($selectedManualProvider['notes'] ?? '') : null,
            'paid_at' => null,
            'expires_at' => now()->addDay(),
        ]);

        return [
            'status' => 'created',
            'payment' => $payment,
        ];
    }

    private function calculatePlanPriceAmount(PlanPrice $planPrice): float
    {
        $amount = max(0, (float) ($planPrice->price ?? 0));
        $discountPercent = max(0, min(100, (int) ($planPrice->discount_pct ?? 0)));
        $discountedAmount = $amount - ($amount * $discountPercent / 100);

        return round(max(0, $discountedAmount), 2);
    }

    private function assertCreateLimit(string $tenantId, TenantPlanResolver $planResolver): void
    {
        $package = $planResolver->forTenantId($tenantId);
        $maxWorkshops = data_get($package, 'plan.max_workshops');

        if (! is_numeric($maxWorkshops) || (int) $maxWorkshops <= 0) {
            return;
        }

        $currentCount = (int) Workshop::query()
            ->where('tenant_id', $tenantId)
            ->count();

        if ($currentCount >= (int) $maxWorkshops) {
            throw ValidationException::withMessages([
                'create_workshop' => 'Limit bengkel pada paket saat ini sudah tercapai.',
            ]);
        }
    }

    private function assertWorkshopsTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('workshops')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function assertPlanTablesReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('workshop_subscriptions')
            || ! Schema::hasTable('plan_prices')
            || ! Schema::hasTable('plans')
            || ! Schema::hasTable('tenants')
            || ! Schema::hasTable('tenant_plan_switch_payments')
            || ! Schema::hasTable('platform_payment_methods')
            || ! Schema::hasTable('platform_manual_payment_providers')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function findTenantWorkshopOrFail(string $tenantId, string $workshopId, string $errorKey): Workshop
    {
        $workshop = Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $workshopId)
            ->first();

        if (! $workshop) {
            throw ValidationException::withMessages([
                $errorKey => 'Bengkel tidak ditemukan.',
            ]);
        }

        return $workshop;
    }

    private function resolveCurrentUri(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['name', 'code', 'phone', 'address', 'is_active', 'created_at'], true)
            ? $sortBy
            : 'created_at';
    }

    private function resolveSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
    }

    private function resolvePerPage(int $perPage): int
    {
        return in_array($perPage, [10, 20, 50], true) ? $perPage : 10;
    }

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'workshop_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'workshop_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
