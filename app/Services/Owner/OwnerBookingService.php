<?php

namespace App\Services\Owner;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\ServiceOrder;
use App\Models\TenantVehicleMaster;
use App\Models\Workshop;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerBookingService
{
    public function __construct(
        private readonly OwnerMenuService $ownerMenuService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(
        Request $request,
        string $tenantId,
        string $activeWorkshopId,
        TenantPlanResolver $planResolver,
        ?Authenticatable $user,
    ): array {
        $bookingSearch = trim((string) $request->query('booking_search', ''));
        $bookingStatus = $this->resolveStatusFilter((string) $request->query('booking_status', 'active'));
        $bookingSortBy = $this->resolveSortBy((string) $request->query('booking_sort_by', 'created_at'));
        $bookingSortDir = $this->resolveSortDirection((string) $request->query('booking_sort_dir', 'desc'));
        $bookingPerPage = $this->resolvePerPage((int) $request->query('booking_per_page', 10));
        $bookingCursor = trim((string) $request->query('booking_cursor', ''));
        $bookingWorkshopFilterInput = trim((string) $request->query('booking_workshop_id', ''));
        $bookingWorkshopFilter = $this->resolveWorkshopFilter(
            $tenantId,
            $activeWorkshopId,
            $bookingWorkshopFilterInput,
        );

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

        $bookingsPayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $bookingPerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $bookingSummary = [
            'total' => 0,
            'queued' => 0,
            'in_service' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];
        $customerOptions = $this->resolveCustomerOptions($tenantId, $activeWorkshopId);
        $customerVehicleOptions = $this->resolveCustomerVehicleOptions($tenantId, $activeWorkshopId);
        $vehicleMasterOptions = $this->resolveTenantVehicleMasterOptions($tenantId);
        $hasBookingCustomerVehicleColumn = Schema::hasTable('bookings')
            && Schema::hasColumn('bookings', 'customer_vehicle_id');

        if (Schema::hasTable('bookings')) {
            $summaryQuery = Booking::query();
            $this->applyBookingScope($summaryQuery, $tenantId, $activeWorkshopId, $bookingWorkshopFilter);

            $bookingSummary = [
                'total' => (int) (clone $summaryQuery)->count(),
                'queued' => (int) (clone $summaryQuery)->where('status', 'queued')->count(),
                'in_service' => (int) (clone $summaryQuery)->where('status', 'in_service')->count(),
                'completed' => (int) (clone $summaryQuery)->where('status', 'completed')->count(),
                'cancelled' => (int) (clone $summaryQuery)->where('status', 'cancelled')->count(),
            ];

            $sortableColumn = [
                'booking_date' => 'bookings.booking_date',
                'queue_number' => 'bookings.queue_number',
                'customer_name' => 'bookings.customer_name',
                'status' => 'bookings.status',
                'created_at' => 'bookings.created_at',
            ][$bookingSortBy] ?? 'bookings.created_at';

            $bookingListQuery = Booking::query();
            $this->applyBookingScope($bookingListQuery, $tenantId, $activeWorkshopId, $bookingWorkshopFilter);

            $bookingRelations = ['workshop:id,name,code'];
            if ($hasBookingCustomerVehicleColumn) {
                $bookingRelations[] = 'customerVehicle:id,tenant_id,customer_id,brand,model,variant,plate_number,vehicle_type';
            }

            $bookingListQuery
                ->with($bookingRelations)
                ->when($bookingSearch !== '', function (Builder $query) use ($bookingSearch): void {
                    $query->where(function (Builder $nestedQuery) use ($bookingSearch): void {
                        $nestedQuery
                            ->where('bookings.code', 'like', "%{$bookingSearch}%")
                            ->orWhere('bookings.customer_name', 'like', "%{$bookingSearch}%")
                            ->orWhere('bookings.customer_phone', 'like', "%{$bookingSearch}%")
                            ->orWhere('bookings.complaint', 'like', "%{$bookingSearch}%");

                        if (is_numeric($bookingSearch)) {
                            $nestedQuery->orWhere('bookings.queue_number', (int) $bookingSearch);
                        }
                    });
                })
                ->when($bookingStatus !== '', function (Builder $query) use ($bookingStatus): void {
                    if ($bookingStatus === 'active') {
                        $query->whereIn('bookings.status', ['queued', 'in_service']);

                        return;
                    }

                    $query->where('bookings.status', $bookingStatus);
                });

            $filteredTotal = (int) (clone $bookingListQuery)->count();

            $bookingSelectColumns = [
                'bookings.id',
                'bookings.tenant_id',
                'bookings.workshop_id',
                'bookings.code',
                'bookings.booking_date',
                'bookings.booking_time',
                'bookings.queue_number',
                'bookings.customer_name',
                'bookings.customer_phone',
                'bookings.complaint',
                'bookings.notes',
                'bookings.status',
                'bookings.created_at',
                'bookings.updated_at',
            ];

            if ($hasBookingCustomerVehicleColumn) {
                $bookingSelectColumns[] = 'bookings.customer_vehicle_id';
            }

            $bookingsPaginator = $this->cursorPaginateWithFallback(
                (clone $bookingListQuery)
                    ->orderBy($sortableColumn, $bookingSortDir)
                    ->orderBy('bookings.id', $bookingSortDir),
                $bookingPerPage,
                $bookingSelectColumns,
                $bookingCursor,
            );

            $bookingRows = collect($bookingsPaginator->items())
                ->map(function (Booking $booking) use ($hasBookingCustomerVehicleColumn): array {
                    $vehicleDisplayName = trim(implode(' ', array_filter([
                        (string) ($booking->customerVehicle?->brand ?? ''),
                        (string) ($booking->customerVehicle?->model ?? ''),
                        (string) ($booking->customerVehicle?->variant ?? ''),
                    ])));

                    return [
                        'id' => (string) $booking->id,
                        'workshop_id' => (string) ($booking->workshop_id ?? ''),
                        'workshop_name' => trim((string) ($booking->workshop?->name ?? '')),
                        'workshop_code' => trim((string) ($booking->workshop?->code ?? '')),
                        'code' => (string) ($booking->code ?? ''),
                        'booking_date' => $booking->booking_date,
                        'booking_time' => $booking->booking_time ? substr((string) $booking->booking_time, 0, 5) : null,
                        'queue_number' => (int) ($booking->queue_number ?? 0),
                        'customer_name' => (string) ($booking->customer_name ?? ''),
                        'customer_phone' => (string) ($booking->customer_phone ?? ''),
                        'customer_vehicle_id' => $hasBookingCustomerVehicleColumn ? (string) ($booking->customer_vehicle_id ?? '') : '',
                        'customer_vehicle_name' => $vehicleDisplayName !== '' ? $vehicleDisplayName : null,
                        'customer_vehicle_plate_number' => trim((string) ($booking->customerVehicle?->plate_number ?? '')) ?: null,
                        'customer_vehicle_type' => trim((string) ($booking->customerVehicle?->vehicle_type ?? '')) ?: null,
                        'complaint' => (string) ($booking->complaint ?? ''),
                        'notes' => (string) ($booking->notes ?? ''),
                        'status' => (string) ($booking->status ?? 'queued'),
                        'status_label' => $this->resolveStatusLabel((string) ($booking->status ?? 'queued')),
                        'created_at' => $booking->created_at,
                        'updated_at' => $booking->updated_at,
                    ];
                })
                ->values();

            $bookingsPayload = [
                'mode' => 'cursor',
                'data' => $bookingRows->all(),
                'per_page' => $bookingsPaginator->perPage(),
                'total' => $filteredTotal,
                'from' => $bookingRows->isEmpty() ? 0 : 1,
                'to' => $bookingRows->count(),
                'current_cursor' => $bookingsPaginator->cursor()?->encode(),
                'next_cursor' => $bookingsPaginator->nextCursor()?->encode(),
                'prev_cursor' => $bookingsPaginator->previousCursor()?->encode(),
                'has_more_pages' => $bookingsPaginator->hasMorePages(),
            ];
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'bookings' => $bookingsPayload,
            'bookingFilters' => [
                'search' => $bookingSearch,
                'status' => $bookingStatus,
                'sort_by' => $bookingSortBy,
                'sort_dir' => $bookingSortDir,
                'per_page' => $bookingPerPage,
                'cursor' => $bookingsPayload['current_cursor'],
                'workshop_id' => $bookingWorkshopFilter,
            ],
            'bookingSummary' => $bookingSummary,
            'customerOptions' => $customerOptions,
            'customerVehicleOptions' => $customerVehicleOptions,
            'vehicleMasterOptions' => $vehicleMasterOptions,
            'activeWorkshop' => $this->resolveActiveWorkshopPayload($request, $tenantId, $activeWorkshopId),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createBooking(
        string $tenantId,
        string $activeWorkshopId,
        array $validated,
        ?Authenticatable $actor = null,
    ): void {
        $this->assertBookingsTableReady('create_booking', 'Tabel booking belum siap.');
        $targetWorkshopId = $this->resolveTargetWorkshopId($tenantId, $activeWorkshopId, $validated, 'workshop_id');
        $bookingDate = (string) ($validated['booking_date'] ?? now()->toDateString());

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                DB::transaction(function () use ($tenantId, $targetWorkshopId, $bookingDate, $validated, $actor): void {
                    $queueNumber = $this->resolveNextQueueNumber($tenantId, $targetWorkshopId, $bookingDate);
                    $resolvedCustomerVehicleId = $this->normalizeNullableString($validated['customer_vehicle_id'] ?? null);

                    if (Schema::hasColumn('bookings', 'customer_vehicle_id')) {
                        $resolvedCustomerVehicleId = $this->resolveCustomerVehicleIdForBookingCreation(
                            $tenantId,
                            $targetWorkshopId,
                            $validated,
                            $resolvedCustomerVehicleId,
                        );
                    }

                    $bookingPayload = [
                        'tenant_id' => $tenantId,
                        'workshop_id' => $targetWorkshopId,
                        'code' => $this->generateBookingCode($tenantId),
                        'booking_date' => $bookingDate,
                        'booking_time' => $this->normalizeTimeInput($validated['booking_time'] ?? null),
                        'queue_number' => $queueNumber,
                        'customer_name' => trim((string) ($validated['customer_name'] ?? '')),
                        'customer_phone' => $this->normalizeNullableString($validated['customer_phone'] ?? null),
                        'complaint' => trim((string) ($validated['complaint'] ?? '')),
                        'notes' => $this->normalizeNullableString($validated['notes'] ?? null),
                        'status' => 'queued',
                        'created_by_user_id' => $this->resolveActorUserId($actor),
                    ];

                    if (Schema::hasColumn('bookings', 'customer_vehicle_id')) {
                        $bookingPayload['customer_vehicle_id'] = $resolvedCustomerVehicleId;
                    }

                    Booking::query()->create($bookingPayload);
                });

                return;
            } catch (QueryException $queryException) {
                if (! $this->isDuplicateBookingConstraintViolation($queryException)) {
                    throw $queryException;
                }

                if ($attempt === 4) {
                    throw ValidationException::withMessages([
                        'create_booking' => 'Kode booking bentrok dengan data lain. Silakan simpan ulang.',
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{message: string, redirect_to_orders?: bool, service_order_code?: string}
     */
    public function updateBookingStatus(
        string $tenantId,
        string $activeWorkshopId,
        string $bookingId,
        array $validated,
        ?Authenticatable $actor = null,
    ): array {
        $this->assertBookingsTableReady('update_booking_status', 'Tabel booking belum siap.');

        $nextStatus = strtolower(trim((string) ($validated['status'] ?? '')));
        if (! in_array($nextStatus, ['in_service', 'completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status booking tidak valid.',
            ]);
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($tenantId, $activeWorkshopId, $bookingId, $nextStatus, $actor): array {
                    $targetBooking = $this->findTenantBookingOrFail(
                        $tenantId,
                        $activeWorkshopId,
                        $bookingId,
                        'update_booking_status',
                        lockForUpdate: true,
                    );

                    $currentStatus = strtolower(trim((string) $targetBooking->status));
                    if ($currentStatus === $nextStatus) {
                        return [
                            'message' => 'Status booking sudah sesuai.',
                        ];
                    }

                    if (! $this->isAllowedStatusTransition($currentStatus, $nextStatus)) {
                        throw ValidationException::withMessages([
                            'update_booking_status' => 'Perpindahan status booking tidak diizinkan.',
                        ]);
                    }

                    $createdOrder = null;
                    if ($nextStatus === 'in_service') {
                        $createdOrder = $this->createServiceOrderFromBooking(
                            $tenantId,
                            $targetBooking,
                            $actor,
                        );
                    }

                    $targetBooking->status = $nextStatus;
                    $targetBooking->save();

                    $message = 'Status booking diperbarui menjadi '.$this->resolveStatusLabel((string) $targetBooking->status).'.';
                    if ($createdOrder instanceof ServiceOrder) {
                        $message = 'Booking dipindahkan ke servis dengan kode '.$createdOrder->code.'.';

                        return [
                            'message' => $message,
                            'redirect_to_orders' => true,
                            'service_order_code' => (string) $createdOrder->code,
                        ];
                    }

                    return [
                        'message' => $message,
                    ];
                });
            } catch (QueryException $queryException) {
                if (! $this->isDuplicateServiceOrderCodeViolation($queryException)) {
                    throw $queryException;
                }

                if ($attempt === 4) {
                    throw ValidationException::withMessages([
                        'update_booking_status' => 'Kode servis bentrok dengan data lain. Silakan klik Mulai lagi.',
                    ]);
                }
            }
        }

        return [
            'message' => 'Status booking berhasil diperbarui.',
        ];
    }

    private function applyBookingScope(
        Builder $query,
        string $tenantId,
        string $activeWorkshopId,
        string $workshopFilterId = '',
    ): void {
        $query->where('tenant_id', $tenantId);

        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $query->where('workshop_id', $activeWorkshopId);
        }

        if (trim($workshopFilterId) !== '') {
            $query->where('workshop_id', trim($workshopFilterId));
        }
    }

    private function findTenantBookingOrFail(
        string $tenantId,
        string $activeWorkshopId,
        string $bookingId,
        string $errorKey,
        bool $lockForUpdate = false,
    ): Booking {
        $query = Booking::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $bookingId);

        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $query->where('workshop_id', $activeWorkshopId);
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $booking = $query->first();
        if (! $booking) {
            throw ValidationException::withMessages([
                $errorKey => 'Data booking tidak ditemukan di cabang aktif.',
            ]);
        }

        return $booking;
    }

    private function resolveNextQueueNumber(string $tenantId, string $workshopId, string $bookingDate): int
    {
        $latestQueueNumber = Booking::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('workshop_id', $workshopId)
            ->whereDate('booking_date', $bookingDate)
            ->lockForUpdate()
            ->max('queue_number');

        $nextQueueNumber = ((int) $latestQueueNumber) + 1;

        return max($nextQueueNumber, 1);
    }

    private function generateBookingCode(string $tenantId): string
    {
        $prefix = 'BK-'.now()->format('Ymd');

        for ($sequence = 1; $sequence <= 999; $sequence++) {
            $candidateCode = $prefix.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            $exists = Booking::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', $candidateCode)
                ->exists();

            if (! $exists) {
                return $candidateCode;
            }
        }

        return $prefix.'-'.Str::upper(Str::random(4));
    }

    private function isDuplicateBookingConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        if ($sqlState !== '23000') {
            return false;
        }

        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'bookings_tenant_code_unique')
            || str_contains($message, 'bookings_tenant_workshop_date_queue_unique')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint failed');
    }

    private function createServiceOrderFromBooking(
        string $tenantId,
        Booking $booking,
        ?Authenticatable $actor = null,
    ): ServiceOrder {
        $this->assertServiceOrderTablesReadyForBookingStart();

        $customer = $this->resolveOrCreateCustomerForBookingService($tenantId, $booking);
        $customerVehicleId = $this->resolveCustomerVehicleIdForBookingService($tenantId, $booking, $customer);
        $serviceDate = $booking->booking_date instanceof \DateTimeInterface
            ? $booking->booking_date->format('Y-m-d')
            : Carbon::parse((string) ($booking->booking_date ?? now()->toDateString()))->format('Y-m-d');

        $payload = [
            'tenant_id' => $tenantId,
            'customer_id' => (string) $customer->id,
            'code' => $this->generateServiceOrderCodeFromBooking(),
            'service_date' => $serviceDate,
            'status' => 'in_progress',
            'complaint' => $this->normalizeNullableString($booking->complaint),
            'vehicle_condition' => null,
            'estimated_days' => null,
            'estimated_finish_date' => null,
            'odometer' => null,
            'total_amount' => 0,
            'created_by_user_id' => $this->resolveActorUserId($actor) ?? $this->normalizeNullableString((string) ($booking->created_by_user_id ?? '')),
        ];

        if (Schema::hasColumn('service_orders', 'customer_vehicle_id')) {
            $payload['customer_vehicle_id'] = $customerVehicleId;
        }

        if (Schema::hasColumn('service_orders', 'started_at')) {
            $payload['started_at'] = now();
        }

        if (Schema::hasColumn('service_orders', 'service_fee')) {
            $payload['service_fee'] = 0;
        }

        return ServiceOrder::query()->create($payload);
    }

    private function resolveCustomerVehicleIdForBookingService(
        string $tenantId,
        Booking $booking,
        Customer $customer,
    ): ?string
    {
        if (! Schema::hasTable('customer_vehicles')) {
            return null;
        }

        $customerId = trim((string) $customer->id);
        if ($customerId === '') {
            return null;
        }

        $vehicleQuery = CustomerVehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->when($this->hasSoftDeleteColumn('customer_vehicles'), function (Builder $query): void {
                $query->whereNull('deleted_at');
            });

        $selectedVehicleId = trim((string) ($booking->customer_vehicle_id ?? ''));
        if ($selectedVehicleId !== '' && (clone $vehicleQuery)->where('id', $selectedVehicleId)->exists()) {
            return $selectedVehicleId;
        }

        $latestUsedVehicleId = trim((string) ServiceOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereNotNull('customer_vehicle_id')
            ->orderByDesc('service_date')
            ->orderByDesc('created_at')
            ->value('customer_vehicle_id'));

        if ($latestUsedVehicleId !== '' && (clone $vehicleQuery)->where('id', $latestUsedVehicleId)->exists()) {
            return $latestUsedVehicleId;
        }

        $fallbackVehicleId = trim((string) (clone $vehicleQuery)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->value('id'));

        return $fallbackVehicleId !== '' ? $fallbackVehicleId : null;
    }

    private function resolveOrCreateCustomerForBookingService(string $tenantId, Booking $booking): Customer
    {
        $selectedVehicleId = trim((string) ($booking->customer_vehicle_id ?? ''));
        $workshopId = trim((string) ($booking->workshop_id ?? ''));

        if ($selectedVehicleId !== '' && Schema::hasTable('customer_vehicles')) {
            $vehicle = CustomerVehicle::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $selectedVehicleId)
                ->where('is_active', true)
                ->when($this->hasSoftDeleteColumn('customer_vehicles'), function (Builder $query): void {
                    $query->whereNull('deleted_at');
                })
                ->first(['id', 'customer_id']);

            if ($vehicle) {
                $customerFromVehicle = Customer::query()
                    ->where('tenant_id', $tenantId)
                    ->where('id', (string) $vehicle->customer_id)
                    ->where('is_active', true)
                    ->when($this->hasSoftDeleteColumn('customers'), function (Builder $query): void {
                        $query->whereNull('deleted_at');
                    })
                    ->when(
                        $this->hasCustomerWorkshopScope() && $workshopId !== '',
                        fn (Builder $query) => $query->where('workshop_id', $workshopId),
                    )
                    ->first();

                if ($customerFromVehicle instanceof Customer) {
                    return $customerFromVehicle;
                }
            }
        }

        $customerName = trim((string) $booking->customer_name);
        if ($customerName === '') {
            throw ValidationException::withMessages([
                'update_booking_status' => 'Nama pelanggan booking tidak boleh kosong saat memulai servis.',
            ]);
        }

        $normalizedPhone = $this->normalizePhoneForLookup($booking->customer_phone);

        $customerQuery = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($customerName)])
            ->when($this->hasSoftDeleteColumn('customers'), function (Builder $query): void {
                $query->whereNull('deleted_at');
            });

        if ($this->hasCustomerWorkshopScope() && $workshopId !== '') {
            $customerQuery->where('workshop_id', $workshopId);
        }

        if ($normalizedPhone !== '') {
            $customerQuery->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '-', ''), '.', ''), '(', ''), ')', ''), '+', '') = ?",
                [$normalizedPhone],
            );
        }

        $existingCustomer = $customerQuery->first();
        if ($existingCustomer instanceof Customer) {
            return $existingCustomer;
        }

        $customerPayload = [
            'tenant_id' => $tenantId,
            'name' => $customerName,
            'phone' => $this->normalizeNullableString($booking->customer_phone),
            'email' => null,
            'address' => null,
            'notes' => null,
            'is_active' => true,
        ];

        if ($this->hasCustomerWorkshopScope() && $workshopId !== '') {
            $customerPayload['workshop_id'] = $workshopId;
        }

        return Customer::query()->create($customerPayload);
    }

    private function generateServiceOrderCodeFromBooking(): string
    {
        $prefix = 'SO-'.now()->format('Ymd');

        for ($sequence = 1; $sequence <= 999; $sequence++) {
            $candidateCode = $prefix.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            $exists = ServiceOrder::query()
                ->withoutGlobalScopes()
                ->where('code', $candidateCode)
                ->exists();

            if (! $exists) {
                return $candidateCode;
            }
        }

        return $prefix.'-'.Str::upper(Str::random(4));
    }

    private function isDuplicateServiceOrderCodeViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        if ($sqlState !== '23000') {
            return false;
        }

        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'service_orders_code_unique')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint failed');
    }

    private function isAllowedStatusTransition(string $fromStatus, string $toStatus): bool
    {
        $allowedTransitions = [
            'queued' => ['in_service', 'cancelled'],
            'in_service' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        return in_array($toStatus, $allowedTransitions[$fromStatus] ?? [], true);
    }

    private function resolveStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'in_service' => 'Sedang Dikerjakan',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => 'Dalam Antrian',
        };
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['booking_date', 'queue_number', 'customer_name', 'status', 'created_at'], true)
            ? $sortBy
            : 'booking_date';
    }

    private function resolveSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
    }

    private function resolvePerPage(int $perPage): int
    {
        return in_array($perPage, [10, 20, 50], true) ? $perPage : 10;
    }

    private function resolveStatusFilter(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, ['active', 'queued', 'in_service', 'completed', 'cancelled'], true)
            ? $normalized
            : '';
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function resolveCustomerOptions(string $tenantId, string $activeWorkshopId): array
    {
        if (! Schema::hasTable('customers')) {
            return [];
        }

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->with(['workshop:id,name,code'])
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'workshop_id', 'name', 'phone', 'email', 'address'])
            ->map(function (Customer $customer): array {
                $workshopName = trim((string) ($customer->workshop?->name ?? ''));
                $workshopCode = trim((string) ($customer->workshop?->code ?? ''));
                $subtitleParts = array_filter([
                    trim((string) ($customer->phone ?? '')),
                    trim((string) ($customer->email ?? '')),
                    $workshopName !== '' ? $workshopName.($workshopCode !== '' ? " ({$workshopCode})" : '') : '',
                ]);

                return [
                    'id' => (string) $customer->id,
                    'workshop_id' => (string) ($customer->workshop_id ?? ''),
                    'name' => (string) ($customer->name ?? ''),
                    'phone' => (string) ($customer->phone ?? ''),
                    'address' => (string) ($customer->address ?? ''),
                    'subtitle' => implode(' | ', $subtitleParts),
                ];
            })
            ->filter(fn (array $customer): bool => trim((string) ($customer['id'] ?? '')) !== '' && trim((string) ($customer['name'] ?? '')) !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCustomerVehicleOptions(string $tenantId, string $activeWorkshopId): array
    {
        if (! Schema::hasTable('customer_vehicles')) {
            return [];
        }

        return CustomerVehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($this->hasSoftDeleteColumn('customer_vehicles'), function (Builder $query): void {
                $query->whereNull('deleted_at');
            })
            ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->whereHas('customer', function (Builder $customerQuery) use ($activeWorkshopId): void {
                    $customerQuery
                        ->where('workshop_id', $activeWorkshopId)
                        ->where('is_active', true);
                });
            })
            ->with(['customer:id,workshop_id'])
            ->orderBy('brand')
            ->orderBy('model')
            ->orderBy('plate_number')
            ->limit(600)
            ->get(['id', 'customer_id', 'vehicle_type', 'brand', 'model', 'variant', 'plate_number', 'year'])
            ->map(function (CustomerVehicle $vehicle): array {
                $vehicleType = strtolower(trim((string) $vehicle->vehicle_type));
                $normalizedVehicleType = in_array($vehicleType, ['motor', 'mobil'], true) ? $vehicleType : 'motor';
                $displayName = trim(implode(' ', array_filter([
                    trim((string) ($vehicle->brand ?? '')),
                    trim((string) ($vehicle->model ?? '')),
                    trim((string) ($vehicle->variant ?? '')),
                ])));

                return [
                    'id' => (string) $vehicle->id,
                    'customer_id' => (string) ($vehicle->customer_id ?? ''),
                    'workshop_id' => (string) ($vehicle->customer?->workshop_id ?? ''),
                    'vehicle_type' => $normalizedVehicleType,
                    'vehicle_type_label' => $normalizedVehicleType === 'mobil' ? 'Mobil' : 'Motor',
                    'brand' => (string) ($vehicle->brand ?? ''),
                    'model' => (string) ($vehicle->model ?? ''),
                    'variant' => (string) ($vehicle->variant ?? ''),
                    'plate_number' => (string) ($vehicle->plate_number ?? ''),
                    'year' => $vehicle->year !== null ? (int) $vehicle->year : null,
                    'display_name' => $displayName !== '' ? $displayName : 'Kendaraan',
                ];
            })
            ->filter(fn (array $vehicle): bool => trim((string) ($vehicle['id'] ?? '')) !== '' && trim((string) ($vehicle['customer_id'] ?? '')) !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveTenantVehicleMasterOptions(string $tenantId): array
    {
        if (! Schema::hasTable('tenant_vehicle_masters')) {
            return [];
        }

        return TenantVehicleMaster::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('vehicle_type')
            ->orderBy('brand')
            ->orderBy('model')
            ->limit(1000)
            ->get(['id', 'vehicle_type', 'brand', 'model', 'source'])
            ->map(function (TenantVehicleMaster $master): array {
                $vehicleType = strtolower(trim((string) $master->vehicle_type));

                return [
                    'id' => (string) $master->id,
                    'vehicle_type' => in_array($vehicleType, ['motor', 'mobil'], true) ? $vehicleType : 'motor',
                    'brand' => (string) $master->brand,
                    'model' => (string) $master->model,
                    'source' => (string) ($master->source ?? 'manual'),
                    'label' => trim((string) $master->brand).' - '.trim((string) $master->model),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCustomerVehicleIdForBookingCreation(
        string $tenantId,
        string $targetWorkshopId,
        array $validated,
        ?string $selectedCustomerVehicleId,
    ): ?string {
        if ($selectedCustomerVehicleId !== null && $selectedCustomerVehicleId !== '') {
            return $selectedCustomerVehicleId;
        }

        if (! $this->shouldCreateVehicleForBookingCreation($validated)) {
            return null;
        }

        if (! Schema::hasTable('customers') || ! Schema::hasTable('customer_vehicles')) {
            throw ValidationException::withMessages([
                'create_booking' => 'Data kendaraan belum dapat disimpan. Jalankan migrasi terbaru.',
            ]);
        }

        $customer = $this->resolveCustomerForBookingCreation($tenantId, $targetWorkshopId, $validated);
        $vehicle = $this->resolveVehicleForBookingCreation($tenantId, (string) $customer->id, $validated);

        return (string) $vehicle->id;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function shouldCreateVehicleForBookingCreation(array $validated): bool
    {
        $fields = [
            'vehicle_master_id',
            'vehicle_type',
            'vehicle_brand',
            'vehicle_model',
            'vehicle_plate_number',
        ];

        foreach ($fields as $field) {
            if ($this->normalizeNullableString($validated[$field] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCustomerForBookingCreation(string $tenantId, string $targetWorkshopId, array $validated): Customer
    {
        $customerId = trim((string) ($validated['customer_id'] ?? ''));
        if ($customerId !== '') {
            $customer = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->when($this->hasSoftDeleteColumn('customers'), function (Builder $query): void {
                    $query->whereNull('deleted_at');
                })
                ->when($this->hasCustomerWorkshopScope() && $targetWorkshopId !== '', function (Builder $query) use ($targetWorkshopId): void {
                    $query->where('workshop_id', $targetWorkshopId);
                })
                ->where('id', $customerId)
                ->first();

            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pelanggan tidak ditemukan untuk cabang terpilih.',
                ]);
            }

            return $customer;
        }

        $customerName = trim((string) ($validated['customer_name'] ?? ''));
        if ($customerName === '') {
            throw ValidationException::withMessages([
                'customer_name' => 'Nama pelanggan wajib diisi saat menambah kendaraan baru.',
            ]);
        }

        $customerPhone = $this->normalizeNullableString($validated['customer_phone'] ?? null);
        $normalizedPhone = $this->normalizePhoneForLookup($customerPhone);

        $customerQuery = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($customerName)])
            ->when($this->hasSoftDeleteColumn('customers'), function (Builder $query): void {
                $query->whereNull('deleted_at');
            })
            ->when($this->hasCustomerWorkshopScope() && $targetWorkshopId !== '', function (Builder $query) use ($targetWorkshopId): void {
                $query->where('workshop_id', $targetWorkshopId);
            });

        if ($normalizedPhone !== '') {
            $customerQuery->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '-', ''), '.', ''), '(', ''), ')', ''), '+', '') = ?",
                [$normalizedPhone],
            );
        }

        $existingCustomer = $customerQuery->first();
        if ($existingCustomer instanceof Customer) {
            if ($customerPhone !== null && trim((string) ($existingCustomer->phone ?? '')) === '') {
                $existingCustomer->phone = $customerPhone;
                $existingCustomer->save();
            }

            return $existingCustomer;
        }

        $customerPayload = [
            'tenant_id' => $tenantId,
            'name' => $customerName,
            'phone' => $customerPhone,
            'email' => null,
            'address' => null,
            'notes' => null,
            'is_active' => true,
        ];

        if ($this->hasCustomerWorkshopScope() && $targetWorkshopId !== '') {
            $customerPayload['workshop_id'] = $targetWorkshopId;
        }

        return Customer::query()->create($customerPayload);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveVehicleForBookingCreation(string $tenantId, string $customerId, array $validated): CustomerVehicle
    {
        [$vehicleType, $brand, $model] = $this->resolveVehicleIdentityFromTenantMasterForBooking($tenantId, $validated);
        $plateNumber = $this->normalizePlateNumber($validated['vehicle_plate_number'] ?? null);

        if ($brand === '' || $model === '' || $plateNumber === '') {
            throw ValidationException::withMessages([
                'vehicle_master_id' => 'Model kendaraan dan nomor polisi wajib diisi saat menambah kendaraan baru.',
            ]);
        }

        $existingVehicle = CustomerVehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('vehicle_type', $vehicleType)
            ->whereRaw('LOWER(TRIM(brand)) = ?', [Str::lower($brand)])
            ->whereRaw('LOWER(TRIM(model)) = ?', [Str::lower($model)])
            ->whereRaw("UPPER(REPLACE(TRIM(COALESCE(plate_number, '')), ' ', '')) = ?", [$plateNumber])
            ->first();

        if ($existingVehicle) {
            $existingVehicle->forceFill([
                'vehicle_type' => $vehicleType,
                'is_active' => true,
            ])->save();

            return $existingVehicle;
        }

        return CustomerVehicle::query()->create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'vehicle_type' => $vehicleType,
            'brand' => $brand,
            'model' => $model,
            'plate_number' => $plateNumber,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveVehicleIdentityFromTenantMasterForBooking(string $tenantId, array $validated): array
    {
        $vehicleType = $this->normalizeVehicleType((string) ($validated['vehicle_type'] ?? ''));
        $brand = trim((string) ($validated['vehicle_brand'] ?? ''));
        $model = trim((string) ($validated['vehicle_model'] ?? ''));

        $vehicleMasterId = trim((string) ($validated['vehicle_master_id'] ?? ''));
        if ($vehicleMasterId !== '') {
            $master = TenantVehicleMaster::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $vehicleMasterId)
                ->where('is_active', true)
                ->first();

            if (! $master) {
                throw ValidationException::withMessages([
                    'vehicle_master_id' => 'Master kendaraan tidak ditemukan atau sudah nonaktif.',
                ]);
            }

            $vehicleType = $this->normalizeVehicleType((string) $master->vehicle_type);
            $brand = trim((string) $master->brand);
            $model = trim((string) $master->model);
        }

        return [$vehicleType, $brand, $model];
    }

    private function shouldApplyCustomerWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return $this->hasCustomerWorkshopScope()
            && $this->hasActiveWorkshops($tenantId)
            && $this->shouldApplyWorkshopScope($activeWorkshopId);
    }

    private function hasCustomerWorkshopScope(): bool
    {
        return Schema::hasTable('customers')
            && Schema::hasColumn('customers', 'workshop_id');
    }

    private function hasActiveWorkshops(string $tenantId): bool
    {
        if ($tenantId === '' || ! Schema::hasTable('workshops')) {
            return false;
        }

        return Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();
    }

    private function resolveWorkshopFilter(
        string $tenantId,
        string $activeWorkshopId,
        string $requestedWorkshopId,
    ): string {
        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            return trim($activeWorkshopId);
        }

        $normalizedWorkshopId = trim($requestedWorkshopId);
        if ($normalizedWorkshopId === '' || OwnerWorkshopSwitcherService::isAllWorkshopsId($normalizedWorkshopId)) {
            return '';
        }

        if (! Schema::hasTable('workshops')) {
            return '';
        }

        $exists = Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $normalizedWorkshopId)
            ->where('is_active', true)
            ->exists();

        return $exists ? $normalizedWorkshopId : '';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveTargetWorkshopId(
        string $tenantId,
        string $activeWorkshopId,
        array $validated,
        string $errorKey,
    ): string {
        $requestedWorkshopId = trim((string) ($validated['workshop_id'] ?? ''));
        if ($requestedWorkshopId === '' && $this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $requestedWorkshopId = trim($activeWorkshopId);
        }

        if ($requestedWorkshopId === '') {
            throw ValidationException::withMessages([
                $errorKey => 'Pilih bengkel tujuan terlebih dahulu.',
            ]);
        }

        if (! Schema::hasTable('workshops')) {
            return $requestedWorkshopId;
        }

        $exists = Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $requestedWorkshopId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                $errorKey => 'Bengkel tujuan tidak valid atau tidak aktif.',
            ]);
        }

        return $requestedWorkshopId;
    }

    /**
     * @return array{id: string, name: string, code: string}
     */
    private function resolveActiveWorkshopPayload(Request $request, string $tenantId, string $activeWorkshopId): array
    {
        $fallback = [
            'id' => $activeWorkshopId,
            'name' => 'Cabang Aktif',
            'code' => '-',
        ];

        if (! $this->shouldApplyWorkshopScope($activeWorkshopId)) {
            return [
                'id' => OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID,
                'name' => 'Semua Bengkel',
                'code' => 'GLOBAL',
            ];
        }

        $switcher = $request->attributes->get('owner_workshop_switcher');
        if (is_array($switcher)) {
            $activeId = trim((string) ($switcher['active_workshop_id'] ?? ''));
            if ($activeId !== '' && $activeId === $activeWorkshopId) {
                return [
                    'id' => $activeId,
                    'name' => trim((string) ($switcher['active_workshop_name'] ?? '')) ?: $fallback['name'],
                    'code' => trim((string) ($switcher['active_workshop_code'] ?? '')) ?: $fallback['code'],
                ];
            }
        }

        if (! Schema::hasTable('workshops')) {
            return $fallback;
        }

        $workshop = Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $activeWorkshopId)
            ->first(['id', 'name', 'code']);

        if (! $workshop) {
            return $fallback;
        }

        return [
            'id' => (string) $workshop->id,
            'name' => trim((string) $workshop->name) ?: $fallback['name'],
            'code' => trim((string) $workshop->code) ?: $fallback['code'],
        ];
    }

    private function shouldApplyWorkshopScope(string $activeWorkshopId): bool
    {
        return ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizePlateNumber(mixed $value): string
    {
        $normalized = Str::upper(trim((string) $value));
        $normalized = str_replace(' ', '', $normalized);
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);

        return is_string($normalized) ? $normalized : '';
    }

    private function normalizeVehicleType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['motor', 'mobil'], true) ? $normalized : 'motor';
    }

    private function normalizeTimeInput(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $normalized) === 1) {
            return $normalized;
        }

        try {
            return Carbon::parse($normalized)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveActorUserId(?Authenticatable $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        $actorId = trim((string) $actor->getAuthIdentifier());

        return $actorId !== '' ? $actorId : null;
    }

    private function assertBookingsTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('bookings')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function assertServiceOrderTablesReadyForBookingStart(): void
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasTable('customers')) {
            throw ValidationException::withMessages([
                'update_booking_status' => 'Modul servis belum siap. Jalankan migrasi terbaru.',
            ]);
        }
    }

    private function hasSoftDeleteColumn(string $table): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'deleted_at');
    }

    private function normalizePhoneForLookup(mixed $value): string
    {
        $normalized = preg_replace('/\D+/', '', (string) $value);

        return is_string($normalized) ? $normalized : '';
    }

    private function resolveCurrentUri(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());

        return $queryString !== '' ? $path.'?'.$queryString : $path;
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
                ->cursorPaginate($perPage, $columns, 'booking_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'booking_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
