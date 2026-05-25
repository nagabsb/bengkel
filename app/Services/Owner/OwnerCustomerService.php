<?php

namespace App\Services\Owner;

use App\Models\Customer;
use App\Models\Workshop;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerCustomerService
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
        $customerSearch = trim((string) $request->query('customer_search', ''));
        $customerSortBy = $this->resolveSortBy((string) $request->query('customer_sort_by', 'created_at'));
        $customerSortDir = $this->resolveSortDirection((string) $request->query('customer_sort_dir', 'desc'));
        $customerPerPage = $this->resolvePerPage((int) $request->query('customer_per_page', 10));
        $customerCursor = trim((string) $request->query('customer_cursor', ''));

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

        $customerPayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $customerPerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $customerSummary = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
        ];

        if (Schema::hasTable('customers')) {
            $summaryQuery = Customer::query()
                ->where('tenant_id', $tenantId)
                ->when($this->shouldApplyWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                    $query->where(function (Builder $scopedQuery) use ($activeWorkshopId): void {
                        $scopedQuery
                            ->where('workshop_id', $activeWorkshopId)
                            ->orWhereNull('workshop_id');
                    });
                });

            $totalCustomers = (int) (clone $summaryQuery)->count();
            $activeCustomers = (int) (clone $summaryQuery)
                ->where('is_active', true)
                ->count();

            $customerSummary = [
                'total' => $totalCustomers,
                'active' => $activeCustomers,
                'inactive' => max($totalCustomers - $activeCustomers, 0),
            ];

            $sortableColumn = [
                'name' => 'customers.name',
                'phone' => 'customers.phone',
                'email' => 'customers.email',
                'is_active' => 'customers.is_active',
                'created_at' => 'customers.created_at',
            ][$customerSortBy] ?? 'customers.created_at';

            $customerPaginator = $this->cursorPaginateWithFallback(
                Customer::query()
                    ->where('tenant_id', $tenantId)
                    ->when($this->shouldApplyWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                        $query->where(function (Builder $scopedQuery) use ($activeWorkshopId): void {
                            $scopedQuery
                                ->where('workshop_id', $activeWorkshopId)
                                ->orWhereNull('workshop_id');
                        });
                    })
                    ->with(['workshop:id,name,code'])
                    ->when($customerSearch !== '', function (Builder $query) use ($customerSearch): void {
                        $query->where(function (Builder $nestedQuery) use ($customerSearch): void {
                            $nestedQuery
                                ->where('name', 'like', "%{$customerSearch}%")
                                ->orWhere('phone', 'like', "%{$customerSearch}%")
                                ->orWhere('email', 'like', "%{$customerSearch}%");
                        });
                    })
                    ->orderBy($sortableColumn, $customerSortDir)
                    ->orderBy('customers.id', $customerSortDir),
                $customerPerPage,
                ['customers.id', 'customers.name', 'customers.phone', 'customers.email', 'customers.address', 'customers.notes', 'customers.is_active', 'customers.created_at', 'customers.updated_at'],
                $customerCursor,
            );

            $customerRows = collect($customerPaginator->items())
                ->map(function (Customer $customer): array {
                    $workshopName = trim((string) ($customer->workshop?->name ?? ''));
                    $workshopCode = trim((string) ($customer->workshop?->code ?? ''));

                    return [
                        'id' => (string) $customer->id,
                        'workshop_id' => (string) ($customer->workshop_id ?? ''),
                        'workshop_name' => $workshopName,
                        'workshop_code' => $workshopCode,
                        'name' => (string) $customer->name,
                        'phone' => (string) ($customer->phone ?? ''),
                        'email' => (string) ($customer->email ?? ''),
                        'address' => (string) ($customer->address ?? ''),
                        'notes' => (string) ($customer->notes ?? ''),
                        'is_active' => (bool) $customer->is_active,
                        'created_at' => $customer->created_at,
                        'updated_at' => $customer->updated_at,
                    ];
                })
                ->values();

            $customerPayload = [
                'mode' => 'cursor',
                'data' => $customerRows->all(),
                'per_page' => $customerPaginator->perPage(),
                'total' => $totalCustomers,
                'from' => $customerRows->isEmpty() ? 0 : 1,
                'to' => $customerRows->count(),
                'current_cursor' => $customerPaginator->cursor()?->encode(),
                'next_cursor' => $customerPaginator->nextCursor()?->encode(),
                'prev_cursor' => $customerPaginator->previousCursor()?->encode(),
                'has_more_pages' => $customerPaginator->hasMorePages(),
            ];
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'customers' => $customerPayload,
            'customerFilters' => [
                'search' => $customerSearch,
                'sort_by' => $customerSortBy,
                'sort_dir' => $customerSortDir,
                'per_page' => $customerPerPage,
                'cursor' => $customerPayload['current_cursor'],
            ],
            'customerSummary' => $customerSummary,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createCustomer(string $tenantId, string $activeWorkshopId, array $validated): void
    {
        $this->assertCustomersTableReady('create_customer', 'Tabel customer belum siap.');
        $targetWorkshopId = $this->resolveTargetWorkshopId($tenantId, $activeWorkshopId, $validated, 'workshop_id');

        DB::transaction(function () use ($tenantId, $targetWorkshopId, $validated): void {
            Customer::query()->create($this->normalizeCustomerPayload($tenantId, $targetWorkshopId, $validated));
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateCustomer(string $tenantId, string $activeWorkshopId, string $customerId, array $validated): void
    {
        $this->assertCustomersTableReady('update_customer', 'Tabel customer belum siap.');

        $customer = $this->findTenantCustomerOrFail($tenantId, $activeWorkshopId, $customerId, 'update_customer');
        $targetWorkshopId = $this->resolveTargetWorkshopId(
            $tenantId,
            $activeWorkshopId,
            $validated,
            'workshop_id',
            fallbackWorkshopId: (string) ($customer->workshop_id ?? ''),
        );

        DB::transaction(function () use ($tenantId, $targetWorkshopId, $validated, $customer): void {
            $customer->forceFill($this->normalizeCustomerPayload($tenantId, $targetWorkshopId, $validated))
                ->save();
        });
    }

    public function deleteCustomer(string $tenantId, string $activeWorkshopId, string $customerId): void
    {
        $this->assertCustomersTableReady('delete_customer', 'Tabel customer belum siap.');

        $customer = $this->findTenantCustomerOrFail($tenantId, $activeWorkshopId, $customerId, 'delete_customer');

        DB::transaction(function () use ($customer): void {
            $customer->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeCustomerPayload(string $tenantId, string $activeWorkshopId, array $validated): array
    {
        return [
            'tenant_id' => $tenantId,
            'workshop_id' => $activeWorkshopId !== '' ? $activeWorkshopId : null,
            'name' => trim((string) ($validated['name'] ?? '')),
            'phone' => $this->normalizeNullableString($validated['phone'] ?? null),
            'email' => $this->normalizeNullableString($validated['email'] ?? null),
            'address' => $this->normalizeNullableString($validated['address'] ?? null),
            'notes' => $this->normalizeNullableString($validated['notes'] ?? null),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function assertCustomersTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('customers')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function findTenantCustomerOrFail(
        string $tenantId,
        string $activeWorkshopId,
        string $customerId,
        string $errorKey,
    ): Customer
    {
        $customer = Customer::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where(function (Builder $scopedQuery) use ($activeWorkshopId): void {
                    $scopedQuery
                        ->where('workshop_id', $activeWorkshopId)
                        ->orWhereNull('workshop_id');
                });
            })
            ->where('id', $customerId)
            ->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                $errorKey => 'Customer tidak ditemukan.',
            ]);
        }

        return $customer;
    }

    private function resolveCurrentUri(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['name', 'phone', 'email', 'is_active', 'created_at'], true)
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

    private function shouldApplyWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId)
            && $this->hasCustomerWorkshopScope()
            && $this->hasActiveWorkshops($tenantId);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveTargetWorkshopId(
        string $tenantId,
        string $activeWorkshopId,
        array $validated,
        string $errorKey,
        string $fallbackWorkshopId = '',
    ): string {
        $requestedWorkshopId = trim((string) ($validated['workshop_id'] ?? ''));
        $hasActiveWorkshops = $this->hasActiveWorkshops($tenantId);
        if ($requestedWorkshopId === '') {
            $requestedWorkshopId = $this->shouldApplyWorkshopScope($tenantId, $activeWorkshopId)
                ? trim($activeWorkshopId)
                : trim($fallbackWorkshopId);
        }

        if ($requestedWorkshopId === '') {
            if (! $hasActiveWorkshops) {
                return trim($fallbackWorkshopId);
            }

            throw ValidationException::withMessages([
                $errorKey => 'Pilih bengkel tujuan terlebih dahulu.',
            ]);
        }

        if (! Schema::hasTable('workshops') || ! $hasActiveWorkshops) {
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

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'customer_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'customer_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
