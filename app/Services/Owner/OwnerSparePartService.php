<?php

namespace App\Services\Owner;

use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\SparePartUnit;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WarehouseSparePartStock;
use App\Models\Workshop;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerSparePartService
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
        $sparePartSearch = trim((string) $request->query('sparepart_search', ''));
        $sparePartSupplierId = trim((string) $request->query('sparepart_supplier_id', ''));
        $sparePartSortBy = $this->resolveSortBy((string) $request->query('sparepart_sort_by', 'created_at'));
        $sparePartSortDir = $this->resolveSortDirection((string) $request->query('sparepart_sort_dir', 'desc'));
        $sparePartPerPage = $this->resolvePerPage((int) $request->query('sparepart_per_page', 10));
        $sparePartCursor = trim((string) $request->query('sparepart_cursor', ''));
        $requestedWarehouseId = trim((string) $request->query('sparepart_warehouse_id', ''));
        $applyWorkshopScope = $this->shouldApplyWorkshopScope($activeWorkshopId);

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

        $sparePartPayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $sparePartPerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $sparePartSummary = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'low_stock' => 0,
        ];

        $supplierOptions = [];
        $warehouseOptions = [];
        $sparePartCategoryOptions = [];
        $sparePartUnitOptions = [];

        if (Schema::hasTable('suppliers')) {
            $supplierOptions = Supplier::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Supplier $supplier): array => [
                    'id' => (string) $supplier->id,
                    'name' => (string) $supplier->name,
                ])
                ->values()
                ->all();
        }

        if (Schema::hasTable('warehouses')) {
            $warehouseOptions = Warehouse::query()
                ->where('tenant_id', $tenantId)
                ->when($applyWorkshopScope, function (Builder $query) use ($activeWorkshopId): void {
                    $query->where('workshop_id', $activeWorkshopId);
                })
                ->with(['workshop:id,name,code'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'workshop_id', 'name', 'code'])
                ->map(fn (Warehouse $warehouse): array => [
                    'id' => (string) $warehouse->id,
                    'workshop_id' => (string) ($warehouse->workshop_id ?? ''),
                    'name' => (string) $warehouse->name,
                    'code' => (string) ($warehouse->code ?? ''),
                    'workshop_name' => trim((string) ($warehouse->workshop?->name ?? '')),
                    'workshop_code' => trim((string) ($warehouse->workshop?->code ?? '')),
                ])
                ->values()
                ->all();
        }

        if (Schema::hasTable('spare_part_categories')) {
            $sparePartCategoryOptions = SparePartCategory::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (SparePartCategory $category): array => [
                    'id' => (string) $category->id,
                    'name' => (string) $category->name,
                ])
                ->values()
                ->all();
        }

        if (Schema::hasTable('spare_part_units')) {
            $sparePartUnitOptions = SparePartUnit::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'symbol'])
                ->map(fn (SparePartUnit $unit): array => [
                    'id' => (string) $unit->id,
                    'name' => (string) $unit->name,
                    'symbol' => (string) ($unit->symbol ?? ''),
                ])
                ->values()
                ->all();
        }

        $availableWarehouseIds = collect($warehouseOptions)->pluck('id');
        $selectedWarehouseId = $availableWarehouseIds->contains($requestedWarehouseId)
            ? $requestedWarehouseId
            : '';
        $defaultWorkshopWarehouseId = (string) ($availableWarehouseIds->first() ?? '');

        if (Schema::hasTable('spare_parts')) {
            $hasWarehouseStockTable = Schema::hasTable('warehouse_spare_part_stocks');

            if ($hasWarehouseStockTable && $applyWorkshopScope && $defaultWorkshopWarehouseId !== '') {
                $this->ensureWorkshopStocksFromLegacySpareParts(
                    $tenantId,
                    $activeWorkshopId,
                    $defaultWorkshopWarehouseId,
                );
            }

            $summaryQuery = SparePart::query()
                ->where('spare_parts.tenant_id', $tenantId);

            if ($hasWarehouseStockTable) {
                $this->applyWorkshopStockScope(
                    $summaryQuery,
                    $tenantId,
                    $activeWorkshopId,
                    $selectedWarehouseId,
                );
            }

            $totalSpareParts = (int) (clone $summaryQuery)->count();
            $activeSpareParts = (int) (clone $summaryQuery)
                ->where('spare_parts.is_active', true)
                ->count();

            $sparePartBaseQuery = SparePart::query()
                ->with(['supplier:id,name'])
                ->where('spare_parts.tenant_id', $tenantId)
                ->when($sparePartSupplierId !== '', function (Builder $query) use ($sparePartSupplierId): void {
                    $query->where('spare_parts.supplier_id', $sparePartSupplierId);
                })
                ->when($sparePartSearch !== '', function (Builder $query) use ($sparePartSearch): void {
                    $query->where(function (Builder $nestedQuery) use ($sparePartSearch): void {
                        $nestedQuery
                            ->where('spare_parts.name', 'like', "%{$sparePartSearch}%")
                            ->orWhere('spare_parts.sku', 'like', "%{$sparePartSearch}%")
                            ->orWhere('spare_parts.category', 'like', "%{$sparePartSearch}%")
                            ->orWhere('spare_parts.unit', 'like', "%{$sparePartSearch}%")
                            ->orWhereHas('supplier', function (Builder $supplierQuery) use ($sparePartSearch): void {
                                $supplierQuery->where('name', 'like', "%{$sparePartSearch}%");
                            });
                    });
                })
                ->select([
                    'spare_parts.id',
                    'spare_parts.supplier_id',
                    'spare_parts.name',
                    'spare_parts.sku',
                    'spare_parts.category',
                    'spare_parts.unit',
                    'spare_parts.purchase_price',
                    'spare_parts.selling_price',
                    'spare_parts.notes',
                    'spare_parts.is_active',
                    'spare_parts.created_at',
                    'spare_parts.updated_at',
                ]);

            if ($hasWarehouseStockTable) {
                $this->applyWorkshopStockScope(
                    $sparePartBaseQuery,
                    $tenantId,
                    $activeWorkshopId,
                    $selectedWarehouseId,
                );
            }

            if ($hasWarehouseStockTable) {
                $stockAggregateQuery = $this->buildStockAggregateQuery($tenantId, $activeWorkshopId, $selectedWarehouseId);

                $sparePartBaseQuery
                    ->leftJoinSub($stockAggregateQuery, 'stock_agg', function ($join): void {
                        $join->on('stock_agg.spare_part_id', '=', 'spare_parts.id');
                    })
                    ->selectRaw('COALESCE(stock_agg.total_stock, spare_parts.stock, 0) as stock_total')
                    ->selectRaw('COALESCE(stock_agg.total_minimum_stock, spare_parts.minimum_stock, 0) as minimum_stock_total')
                    ->selectRaw('stock_agg.any_warehouse_id as stock_warehouse_id')
                    ->selectRaw('stock_agg.any_workshop_id as stock_workshop_id');
            } else {
                $sparePartBaseQuery
                    ->selectRaw('COALESCE(spare_parts.stock, 0) as stock_total')
                    ->selectRaw('COALESCE(spare_parts.minimum_stock, 0) as minimum_stock_total')
                    ->selectRaw('NULL as stock_warehouse_id')
                    ->selectRaw('NULL as stock_workshop_id');
            }

            $lowStockExpression = $hasWarehouseStockTable
                ? 'COALESCE(stock_agg.total_minimum_stock, spare_parts.minimum_stock, 0) > 0 AND COALESCE(stock_agg.total_stock, spare_parts.stock, 0) <= COALESCE(stock_agg.total_minimum_stock, spare_parts.minimum_stock, 0)'
                : 'COALESCE(spare_parts.minimum_stock, 0) > 0 AND COALESCE(spare_parts.stock, 0) <= COALESCE(spare_parts.minimum_stock, 0)';

            $lowStockCount = (int) (clone $sparePartBaseQuery)
                ->whereRaw($lowStockExpression)
                ->count();

            $sparePartSummary = [
                'total' => $totalSpareParts,
                'active' => $activeSpareParts,
                'inactive' => max($totalSpareParts - $activeSpareParts, 0),
                'low_stock' => $lowStockCount,
            ];

            $sortableColumn = [
                'name' => 'spare_parts.name',
                'sku' => 'spare_parts.sku',
                'stock' => 'stock_total',
                'selling_price' => 'spare_parts.selling_price',
                'is_active' => 'spare_parts.is_active',
                'created_at' => 'spare_parts.created_at',
            ][$sparePartSortBy] ?? 'spare_parts.created_at';

            $sparePartPaginator = $this->cursorPaginateWithFallback(
                (clone $sparePartBaseQuery)
                    ->orderBy($sortableColumn, $sparePartSortDir)
                    ->orderBy('spare_parts.id', $sparePartSortDir),
                $sparePartPerPage,
                $sparePartCursor,
            );

            $warehouseOptionById = collect($warehouseOptions)
                ->mapWithKeys(fn (array $warehouse): array => [(string) ($warehouse['id'] ?? '') => $warehouse]);

            $sparePartRows = collect($sparePartPaginator->items())
                ->map(function (SparePart $sparePart) use ($selectedWarehouseId, $warehouseOptionById): array {
                    $stockWarehouseId = trim((string) ($sparePart->getAttribute('stock_warehouse_id') ?? ''));
                    $effectiveWarehouseId = $selectedWarehouseId !== '' ? $selectedWarehouseId : $stockWarehouseId;
                    $effectiveWorkshopId = trim((string) ($sparePart->getAttribute('stock_workshop_id') ?? ''));
                    if ($effectiveWarehouseId !== '' && $warehouseOptionById->has($effectiveWarehouseId)) {
                        $effectiveWorkshopId = trim((string) ($warehouseOptionById->get($effectiveWarehouseId)['workshop_id'] ?? $effectiveWorkshopId));
                    }

                    return [
                        'id' => (string) $sparePart->id,
                        'workshop_id' => $effectiveWorkshopId,
                        'supplier_id' => (string) ($sparePart->supplier_id ?? ''),
                        'supplier_name' => (string) ($sparePart->supplier?->name ?? ''),
                        'name' => (string) $sparePart->name,
                        'sku' => (string) ($sparePart->sku ?? ''),
                        'category' => (string) ($sparePart->category ?? ''),
                        'unit' => (string) ($sparePart->unit ?? ''),
                        'purchase_price' => $sparePart->purchase_price !== null ? (int) $sparePart->purchase_price : null,
                        'selling_price' => $sparePart->selling_price !== null ? (int) $sparePart->selling_price : null,
                        'stock' => (int) ($sparePart->getAttribute('stock_total') ?? 0),
                        'minimum_stock' => (int) ($sparePart->getAttribute('minimum_stock_total') ?? 0),
                        'stock_warehouse_id' => $effectiveWarehouseId,
                        'notes' => (string) ($sparePart->notes ?? ''),
                        'is_active' => (bool) $sparePart->is_active,
                        'created_at' => $sparePart->created_at,
                        'updated_at' => $sparePart->updated_at,
                    ];
                })
                ->values();

            $sparePartPayload = [
                'mode' => 'cursor',
                'data' => $sparePartRows->all(),
                'per_page' => $sparePartPaginator->perPage(),
                'total' => $totalSpareParts,
                'from' => $sparePartRows->isEmpty() ? 0 : 1,
                'to' => $sparePartRows->count(),
                'current_cursor' => $sparePartPaginator->cursor()?->encode(),
                'next_cursor' => $sparePartPaginator->nextCursor()?->encode(),
                'prev_cursor' => $sparePartPaginator->previousCursor()?->encode(),
                'has_more_pages' => $sparePartPaginator->hasMorePages(),
            ];
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'spareparts' => $sparePartPayload,
            'sparePartFilters' => [
                'search' => $sparePartSearch,
                'supplier_id' => $sparePartSupplierId !== '' ? $sparePartSupplierId : null,
                'warehouse_id' => $selectedWarehouseId !== '' ? $selectedWarehouseId : null,
                'sort_by' => $sparePartSortBy,
                'sort_dir' => $sparePartSortDir,
                'per_page' => $sparePartPerPage,
                'cursor' => $sparePartPayload['current_cursor'],
            ],
            'sparePartSummary' => $sparePartSummary,
            'supplierOptions' => $supplierOptions,
            'warehouseOptions' => $warehouseOptions,
            'sparePartCategoryOptions' => $sparePartCategoryOptions,
            'sparePartUnitOptions' => $sparePartUnitOptions,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createSparePart(string $tenantId, string $activeWorkshopId, array $validated): void
    {
        $this->assertSparePartsTableReady('create_sparepart', 'Tabel sparepart belum siap.');
        $targetWorkshopId = $this->resolveTargetWorkshopId(
            $tenantId,
            $activeWorkshopId,
            $validated,
            'workshop_id',
        );

        DB::transaction(function () use ($tenantId, $targetWorkshopId, $validated): void {
            $sparePart = SparePart::query()->create($this->normalizeSparePartPayload($tenantId, $validated));
            $this->syncWarehouseStockForSparePart($tenantId, $targetWorkshopId, $sparePart, $validated);
            $this->syncSparePartTotalStock($tenantId, $sparePart);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateSparePart(string $tenantId, string $activeWorkshopId, string $sparePartId, array $validated): void
    {
        $this->assertSparePartsTableReady('update_sparepart', 'Tabel sparepart belum siap.');

        DB::transaction(function () use ($tenantId, $activeWorkshopId, $sparePartId, $validated): void {
            $sparePart = $this->findTenantSparePartOrFail(
                $tenantId,
                $sparePartId,
                'update_sparepart',
                lockForUpdate: true,
            );
            $targetWorkshopId = $this->resolveTargetWorkshopId(
                $tenantId,
                $activeWorkshopId,
                $validated,
                'workshop_id',
                fallbackWorkshopId: $this->resolveSparePartWorkshopId($tenantId, (string) $sparePart->id),
            );

            $sparePart->forceFill($this->normalizeSparePartPayload($tenantId, $validated))
                ->save();
            $this->syncWarehouseStockForSparePart($tenantId, $targetWorkshopId, $sparePart, $validated);
            $this->syncSparePartTotalStock($tenantId, $sparePart);
        });
    }

    public function deleteSparePart(string $tenantId, string $activeWorkshopId, string $sparePartId): void
    {
        $this->assertSparePartsTableReady('delete_sparepart', 'Tabel sparepart belum siap.');

        DB::transaction(function () use ($tenantId, $sparePartId): void {
            $sparePart = $this->findTenantSparePartOrFail(
                $tenantId,
                $sparePartId,
                'delete_sparepart',
                lockForUpdate: true,
            );

            $sparePart->delete();
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
    private function normalizeSparePartPayload(string $tenantId, array $validated): array
    {
        return [
            'tenant_id' => $tenantId,
            'supplier_id' => $this->normalizeNullableString($validated['supplier_id'] ?? null),
            'name' => trim((string) ($validated['name'] ?? '')),
            'sku' => $this->normalizeNullableString($validated['sku'] ?? null),
            'category' => $this->normalizeNullableString($validated['category'] ?? null),
            'unit' => $this->normalizeNullableString($validated['unit'] ?? null),
            'purchase_price' => $this->normalizeNullableInteger($validated['purchase_price'] ?? null),
            'selling_price' => $this->normalizeNullableInteger($validated['selling_price'] ?? null),
            'stock' => $this->normalizeNullableInteger($validated['stock'] ?? null) ?? 0,
            'minimum_stock' => $this->normalizeNullableInteger($validated['minimum_stock'] ?? null) ?? 0,
            'notes' => $this->normalizeNullableString($validated['notes'] ?? null),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncWarehouseStockForSparePart(
        string $tenantId,
        string $activeWorkshopId,
        SparePart $sparePart,
        array $validated,
    ): void {
        if (! Schema::hasTable('warehouse_spare_part_stocks') || ! Schema::hasTable('warehouses')) {
            return;
        }

        $warehouseId = $this->normalizeNullableString($validated['warehouse_id'] ?? null);
        if ($warehouseId === null || $warehouseId === '') {
            $warehouseId = $this->resolveDefaultWarehouseId($tenantId, $activeWorkshopId);
        }

        if ($warehouseId === null || $warehouseId === '') {
            return;
        }

        $warehouse = Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->where('workshop_id', $activeWorkshopId)
            ->where('id', $warehouseId)
            ->first();

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang tidak ditemukan di bengkel tujuan.',
            ]);
        }

        $stock = $this->normalizeNullableInteger($validated['stock'] ?? null) ?? 0;
        $minimumStock = $this->normalizeNullableInteger($validated['minimum_stock'] ?? null) ?? 0;

        $stockQuery = WarehouseSparePartStock::query()
            ->where('tenant_id', $tenantId)
            ->where('workshop_id', $activeWorkshopId)
            ->where('warehouse_id', (string) $warehouse->id)
            ->where('spare_part_id', (string) $sparePart->id);

        $stockRow = (clone $stockQuery)
            ->lockForUpdate()
            ->first();

        if ($stockRow) {
            $stockRow->forceFill([
                'stock' => $stock,
                'minimum_stock' => $minimumStock,
            ])->save();

            return;
        }

        try {
            WarehouseSparePartStock::query()->create([
                'tenant_id' => $tenantId,
                'workshop_id' => $activeWorkshopId,
                'warehouse_id' => (string) $warehouse->id,
                'spare_part_id' => (string) $sparePart->id,
                'stock' => $stock,
                'minimum_stock' => $minimumStock,
            ]);
        } catch (QueryException $queryException) {
            if (! $this->isUniqueConstraintViolation($queryException)) {
                throw $queryException;
            }

            $stockRow = (clone $stockQuery)
                ->lockForUpdate()
                ->first();

            if (! $stockRow) {
                throw $queryException;
            }

            $stockRow->forceFill([
                'stock' => $stock,
                'minimum_stock' => $minimumStock,
            ])->save();
        }
    }

    private function syncSparePartTotalStock(string $tenantId, SparePart $sparePart): void
    {
        if (! Schema::hasTable('warehouse_spare_part_stocks')) {
            return;
        }

        $aggregate = WarehouseSparePartStock::query()
            ->where('tenant_id', $tenantId)
            ->where('spare_part_id', (string) $sparePart->id)
            ->selectRaw('COUNT(*) as stock_row_count')
            ->selectRaw('COALESCE(SUM(stock), 0) as total_stock')
            ->selectRaw('COALESCE(SUM(minimum_stock), 0) as total_minimum_stock')
            ->first();

        if ((int) ($aggregate?->getAttribute('stock_row_count') ?? 0) < 1) {
            return;
        }

        $lockedSparePart = SparePart::query()
            ->where('tenant_id', $tenantId)
            ->where('id', (string) $sparePart->id)
            ->lockForUpdate()
            ->first();

        if (! $lockedSparePart) {
            return;
        }

        $lockedSparePart->forceFill([
            'stock' => (int) ($aggregate?->getAttribute('total_stock') ?? 0),
            'minimum_stock' => (int) ($aggregate?->getAttribute('total_minimum_stock') ?? 0),
        ])->save();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return max((int) $normalized, 0);
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

        if ($requestedWorkshopId === '' && ! $this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $warehouseId = trim((string) ($validated['warehouse_id'] ?? ''));
            if ($warehouseId !== '' && Schema::hasTable('warehouses')) {
                $warehouse = Warehouse::query()
                    ->where('tenant_id', $tenantId)
                    ->where('id', $warehouseId)
                    ->first(['workshop_id']);
                $requestedWorkshopId = trim((string) ($warehouse?->workshop_id ?? ''));
            }
        }

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

    private function assertSparePartsTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('spare_parts')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function findTenantSparePartOrFail(
        string $tenantId,
        string $sparePartId,
        string $errorKey,
        bool $lockForUpdate = false,
    ): SparePart
    {
        $query = SparePart::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $sparePartId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $sparePart = $query->first();

        if (! $sparePart) {
            throw ValidationException::withMessages([
                $errorKey => 'Sparepart tidak ditemukan.',
            ]);
        }

        return $sparePart;
    }

    private function isUniqueConstraintViolation(QueryException $queryException): bool
    {
        $sqlState = (string) ($queryException->errorInfo[0] ?? '');

        return in_array($sqlState, ['23000', '23505'], true);
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['name', 'sku', 'stock', 'selling_price', 'is_active', 'created_at'], true)
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

    private function buildStockAggregateQuery(string $tenantId, string $activeWorkshopId, string $warehouseId): Builder
    {
        return WarehouseSparePartStock::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyWorkshopScope($activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->when($warehouseId !== '', function (Builder $query) use ($warehouseId): void {
                $query->where('warehouse_id', $warehouseId);
            })
            ->selectRaw('spare_part_id')
            ->selectRaw('SUM(stock) as total_stock')
            ->selectRaw('SUM(minimum_stock) as total_minimum_stock')
            ->selectRaw('MAX(warehouse_id) as any_warehouse_id')
            ->selectRaw('MAX(workshop_id) as any_workshop_id')
            ->groupBy('spare_part_id');
    }

    private function ensureWorkshopStocksFromLegacySpareParts(
        string $tenantId,
        string $activeWorkshopId,
        string $defaultWarehouseId,
    ): void {
        if ($defaultWarehouseId === '') {
            return;
        }

        $legacySpareParts = SparePart::query()
            ->where('tenant_id', $tenantId)
            ->whereDoesntHave('warehouseStocks')
            ->select(['id', 'stock', 'minimum_stock'])
            ->orderBy('id')
            ->get();

        if ($legacySpareParts->isEmpty()) {
            return;
        }

        foreach ($legacySpareParts as $legacySparePart) {
            WarehouseSparePartStock::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'workshop_id' => $activeWorkshopId,
                    'warehouse_id' => $defaultWarehouseId,
                    'spare_part_id' => (string) $legacySparePart->id,
                ],
                [
                    'stock' => max((int) ($legacySparePart->stock ?? 0), 0),
                    'minimum_stock' => max((int) ($legacySparePart->minimum_stock ?? 0), 0),
                ],
            );
        }
    }

    private function resolveDefaultWarehouseId(string $tenantId, string $activeWorkshopId): ?string
    {
        if (! Schema::hasTable('warehouses')) {
            return null;
        }

        $warehouse = Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->where('workshop_id', $activeWorkshopId)
            ->where('is_active', true)
            ->orderBy('name')
            ->first(['id']);

        return $warehouse?->id ? (string) $warehouse->id : null;
    }

    private function resolveSparePartWorkshopId(string $tenantId, string $sparePartId): string
    {
        if (! Schema::hasTable('warehouse_spare_part_stocks')) {
            return '';
        }

        $workshopId = WarehouseSparePartStock::query()
            ->where('tenant_id', $tenantId)
            ->where('spare_part_id', $sparePartId)
            ->orderByDesc('stock')
            ->value('workshop_id');

        return trim((string) $workshopId);
    }

    private function applyWorkshopStockScope(
        Builder $query,
        string $tenantId,
        string $activeWorkshopId,
        string $warehouseId,
    ): Builder {
        return $query->whereExists(function ($stockQuery) use ($tenantId, $activeWorkshopId, $warehouseId): void {
            $stockQuery
                ->selectRaw('1')
                ->from('warehouse_spare_part_stocks as workshop_stock')
                ->whereColumn('workshop_stock.spare_part_id', 'spare_parts.id')
                ->where('workshop_stock.tenant_id', $tenantId)
                ->when($this->shouldApplyWorkshopScope($activeWorkshopId), function ($scopedQuery) use ($activeWorkshopId): void {
                    $scopedQuery->where('workshop_stock.workshop_id', $activeWorkshopId);
                })
                ->when($warehouseId !== '', function ($scopedQuery) use ($warehouseId): void {
                    $scopedQuery->where('workshop_stock.warehouse_id', $warehouseId);
                });
        });
    }

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        string $cursor,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, ['*'], 'sparepart_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, ['*'], 'sparepart_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
