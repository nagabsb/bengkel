<?php

namespace App\Services\Owner;

use App\Models\Warehouse;
use App\Models\Workshop;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerWarehouseService
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
        $warehouseSearch = trim((string) $request->query('warehouse_search', ''));
        $warehouseSortBy = $this->resolveSortBy((string) $request->query('warehouse_sort_by', 'created_at'));
        $warehouseSortDir = $this->resolveSortDirection((string) $request->query('warehouse_sort_dir', 'desc'));
        $warehousePerPage = $this->resolvePerPage((int) $request->query('warehouse_per_page', 10));
        $warehouseCursor = trim((string) $request->query('warehouse_cursor', ''));

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

        $warehousePayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $warehousePerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $warehouseSummary = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
        ];

        if (Schema::hasTable('warehouses')) {
            $summaryQuery = Warehouse::query()
                ->where('tenant_id', $tenantId)
                ->when($this->shouldApplyWorkshopScope($activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                    $query->where('workshop_id', $activeWorkshopId);
                });

            $totalWarehouses = (int) (clone $summaryQuery)->count();
            $activeWarehouses = (int) (clone $summaryQuery)
                ->where('is_active', true)
                ->count();

            $warehouseSummary = [
                'total' => $totalWarehouses,
                'active' => $activeWarehouses,
                'inactive' => max($totalWarehouses - $activeWarehouses, 0),
            ];

            $sortableColumn = [
                'name' => 'warehouses.name',
                'code' => 'warehouses.code',
                'is_active' => 'warehouses.is_active',
                'created_at' => 'warehouses.created_at',
            ][$warehouseSortBy] ?? 'warehouses.created_at';

            $warehousePaginator = $this->cursorPaginateWithFallback(
                Warehouse::query()
                    ->where('tenant_id', $tenantId)
                    ->when($this->shouldApplyWorkshopScope($activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                        $query->where('workshop_id', $activeWorkshopId);
                    })
                    ->with(['workshop:id,name,code'])
                    ->when($warehouseSearch !== '', function (Builder $query) use ($warehouseSearch): void {
                        $query->where(function (Builder $nestedQuery) use ($warehouseSearch): void {
                            $nestedQuery
                                ->where('name', 'like', "%{$warehouseSearch}%")
                                ->orWhere('code', 'like', "%{$warehouseSearch}%")
                                ->orWhere('address', 'like', "%{$warehouseSearch}%");
                        });
                    })
                    ->orderBy($sortableColumn, $warehouseSortDir)
                    ->orderBy('warehouses.id', $warehouseSortDir),
                $warehousePerPage,
                [
                    'warehouses.id',
                    'warehouses.name',
                    'warehouses.code',
                    'warehouses.address',
                    'warehouses.notes',
                    'warehouses.is_active',
                    'warehouses.created_at',
                    'warehouses.updated_at',
                ],
                $warehouseCursor,
            );

            $warehouseRows = collect($warehousePaginator->items())
                ->map(function (Warehouse $warehouse): array {
                    $workshopName = trim((string) ($warehouse->workshop?->name ?? ''));
                    $workshopCode = trim((string) ($warehouse->workshop?->code ?? ''));

                    return [
                        'id' => (string) $warehouse->id,
                        'workshop_id' => (string) ($warehouse->workshop_id ?? ''),
                        'workshop_name' => $workshopName,
                        'workshop_code' => $workshopCode,
                        'name' => (string) $warehouse->name,
                        'code' => (string) ($warehouse->code ?? ''),
                        'address' => (string) ($warehouse->address ?? ''),
                        'notes' => (string) ($warehouse->notes ?? ''),
                        'is_active' => (bool) $warehouse->is_active,
                        'created_at' => $warehouse->created_at,
                        'updated_at' => $warehouse->updated_at,
                    ];
                })
                ->values();

            $warehousePayload = [
                'mode' => 'cursor',
                'data' => $warehouseRows->all(),
                'per_page' => $warehousePaginator->perPage(),
                'total' => $totalWarehouses,
                'from' => $warehouseRows->isEmpty() ? 0 : 1,
                'to' => $warehouseRows->count(),
                'current_cursor' => $warehousePaginator->cursor()?->encode(),
                'next_cursor' => $warehousePaginator->nextCursor()?->encode(),
                'prev_cursor' => $warehousePaginator->previousCursor()?->encode(),
                'has_more_pages' => $warehousePaginator->hasMorePages(),
            ];
        }

        $activeWorkshopPayload = $this->resolveActiveWorkshopPayload($request, $tenantId, $activeWorkshopId);

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'warehouses' => $warehousePayload,
            'warehouseFilters' => [
                'search' => $warehouseSearch,
                'sort_by' => $warehouseSortBy,
                'sort_dir' => $warehouseSortDir,
                'per_page' => $warehousePerPage,
                'cursor' => $warehousePayload['current_cursor'],
            ],
            'warehouseSummary' => $warehouseSummary,
            'activeWorkshop' => $activeWorkshopPayload,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createWarehouse(string $tenantId, string $activeWorkshopId, array $validated): void
    {
        $this->assertWarehousesTableReady('create_warehouse', 'Tabel gudang belum siap.');
        $targetWorkshopId = $this->resolveTargetWorkshopId($tenantId, $activeWorkshopId, $validated, 'workshop_id');

        DB::transaction(function () use ($tenantId, $targetWorkshopId, $validated): void {
            Warehouse::query()->create($this->normalizeWarehousePayload($tenantId, $targetWorkshopId, $validated));
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateWarehouse(string $tenantId, string $activeWorkshopId, string $warehouseId, array $validated): void
    {
        $this->assertWarehousesTableReady('update_warehouse', 'Tabel gudang belum siap.');

        $warehouse = $this->findTenantWarehouseOrFail($tenantId, $activeWorkshopId, $warehouseId, 'update_warehouse');
        $targetWorkshopId = $this->resolveTargetWorkshopId(
            $tenantId,
            $activeWorkshopId,
            $validated,
            'workshop_id',
            fallbackWorkshopId: (string) ($warehouse->workshop_id ?? ''),
        );

        DB::transaction(function () use ($tenantId, $targetWorkshopId, $validated, $warehouse): void {
            $warehouse->forceFill($this->normalizeWarehousePayload($tenantId, $targetWorkshopId, $validated))
                ->save();
        });
    }

    public function deleteWarehouse(string $tenantId, string $activeWorkshopId, string $warehouseId): void
    {
        $this->assertWarehousesTableReady('delete_warehouse', 'Tabel gudang belum siap.');

        $warehouse = $this->findTenantWarehouseOrFail($tenantId, $activeWorkshopId, $warehouseId, 'delete_warehouse');

        DB::transaction(function () use ($warehouse): void {
            $warehouse->delete();
        });
    }

    private function resolveCurrentUri(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeWarehousePayload(string $tenantId, string $activeWorkshopId, array $validated): array
    {
        return [
            'tenant_id' => $tenantId,
            'workshop_id' => $activeWorkshopId,
            'name' => trim((string) ($validated['name'] ?? '')),
            'code' => $this->normalizeNullableString($validated['code'] ?? null),
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

    private function assertWarehousesTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('warehouses')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function findTenantWarehouseOrFail(
        string $tenantId,
        string $activeWorkshopId,
        string $warehouseId,
        string $errorKey,
    ): Warehouse {
        $warehouse = Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyWorkshopScope($activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->where('id', $warehouseId)
            ->first();

        if (! $warehouse) {
            throw ValidationException::withMessages([
                $errorKey => 'Gudang tidak ditemukan di cabang aktif.',
            ]);
        }

        return $warehouse;
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['name', 'code', 'is_active', 'created_at'], true)
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
        if ($requestedWorkshopId === '') {
            $requestedWorkshopId = $this->shouldApplyWorkshopScope($activeWorkshopId)
                ? trim($activeWorkshopId)
                : trim($fallbackWorkshopId);
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

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'warehouse_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'warehouse_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
