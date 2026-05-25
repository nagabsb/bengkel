<?php

namespace App\Services\Owner;

use App\Models\TenantVehicleMaster;
use App\Models\VehicleMasterModel;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerVehicleService
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
        TenantPlanResolver $planResolver,
        ?Authenticatable $user,
    ): array {
        $vehicleSearch = trim((string) $request->query('vehicle_search', ''));
        $vehicleTypeFilter = $this->resolveVehicleTypeFilter((string) $request->query('vehicle_type', ''));
        $vehicleBrandFilter = trim((string) $request->query('vehicle_brand', ''));
        $vehicleSortBy = $this->resolveSortBy((string) $request->query('vehicle_sort_by', 'created_at'));
        $vehicleSortDir = $this->resolveSortDirection((string) $request->query('vehicle_sort_dir', 'desc'));
        $vehiclePerPage = $this->resolvePerPage((int) $request->query('vehicle_per_page', 10));
        $vehicleCursor = trim((string) $request->query('vehicle_cursor', ''));

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

        $vehiclePayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $vehiclePerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $vehicleSummary = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'motor' => 0,
            'mobil' => 0,
        ];
        $vehicleBrandOptions = [];

        if (Schema::hasTable('tenant_vehicle_masters')) {
            $summaryQuery = TenantVehicleMaster::query()
                ->where('tenant_id', $tenantId);

            $totalVehicles = (int) (clone $summaryQuery)->count();
            $activeVehicles = (int) (clone $summaryQuery)
                ->where('is_active', true)
                ->count();

            $vehicleSummary = [
                'total' => $totalVehicles,
                'active' => $activeVehicles,
                'inactive' => max($totalVehicles - $activeVehicles, 0),
                'motor' => (int) (clone $summaryQuery)->where('vehicle_type', 'motor')->count(),
                'mobil' => (int) (clone $summaryQuery)->where('vehicle_type', 'mobil')->count(),
            ];

            $vehicleBrandOptions = $this->resolveVehicleBrandOptions($tenantId, $vehicleTypeFilter);

            $sortableColumn = [
                'vehicle_type' => 'tenant_vehicle_masters.vehicle_type',
                'brand' => 'tenant_vehicle_masters.brand',
                'model' => 'tenant_vehicle_masters.model',
                'source' => 'tenant_vehicle_masters.source',
                'is_active' => 'tenant_vehicle_masters.is_active',
                'created_at' => 'tenant_vehicle_masters.created_at',
            ][$vehicleSortBy] ?? 'tenant_vehicle_masters.created_at';

            $vehiclePaginator = $this->cursorPaginateWithFallback(
                TenantVehicleMaster::query()
                    ->where('tenant_id', $tenantId)
                    ->when($vehicleTypeFilter !== null, function (Builder $query) use ($vehicleTypeFilter): void {
                        $query->where('vehicle_type', $vehicleTypeFilter);
                    })
                    ->when($vehicleBrandFilter !== '', function (Builder $query) use ($vehicleBrandFilter): void {
                        $query->whereRaw('LOWER(TRIM(brand)) = ?', [strtolower($vehicleBrandFilter)]);
                    })
                    ->when($vehicleSearch !== '', function (Builder $query) use ($vehicleSearch): void {
                        $query->where(function (Builder $nestedQuery) use ($vehicleSearch): void {
                            $nestedQuery
                                ->where('brand', 'like', "%{$vehicleSearch}%")
                                ->orWhere('model', 'like', "%{$vehicleSearch}%")
                                ->orWhere('source', 'like', "%{$vehicleSearch}%");
                        });
                    })
                    ->orderBy($sortableColumn, $vehicleSortDir)
                    ->orderBy('tenant_vehicle_masters.id', $vehicleSortDir),
                $vehiclePerPage,
                [
                    'tenant_vehicle_masters.id',
                    'tenant_vehicle_masters.vehicle_type',
                    'tenant_vehicle_masters.brand',
                    'tenant_vehicle_masters.model',
                    'tenant_vehicle_masters.source',
                    'tenant_vehicle_masters.is_active',
                    'tenant_vehicle_masters.created_at',
                    'tenant_vehicle_masters.updated_at',
                ],
                $vehicleCursor,
            );

            $vehicleRows = collect($vehiclePaginator->items())
                ->map(function (TenantVehicleMaster $vehicle): array {
                    return [
                        'id' => (string) $vehicle->id,
                        'vehicle_type' => (string) $vehicle->vehicle_type,
                        'brand' => (string) $vehicle->brand,
                        'model' => (string) $vehicle->model,
                        'source' => (string) ($vehicle->source ?? 'manual'),
                        'is_active' => (bool) $vehicle->is_active,
                        'created_at' => $vehicle->created_at,
                        'updated_at' => $vehicle->updated_at,
                    ];
                })
                ->values();

            $vehiclePayload = [
                'mode' => 'cursor',
                'data' => $vehicleRows->all(),
                'per_page' => $vehiclePaginator->perPage(),
                'total' => $totalVehicles,
                'from' => $vehicleRows->isEmpty() ? 0 : 1,
                'to' => $vehicleRows->count(),
                'current_cursor' => $vehiclePaginator->cursor()?->encode(),
                'next_cursor' => $vehiclePaginator->nextCursor()?->encode(),
                'prev_cursor' => $vehiclePaginator->previousCursor()?->encode(),
                'has_more_pages' => $vehiclePaginator->hasMorePages(),
            ];
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'vehicles' => $vehiclePayload,
            'vehicleFilters' => [
                'search' => $vehicleSearch,
                'vehicle_type' => $vehicleTypeFilter,
                'vehicle_brand' => $vehicleBrandFilter,
                'sort_by' => $vehicleSortBy,
                'sort_dir' => $vehicleSortDir,
                'per_page' => $vehiclePerPage,
                'cursor' => $vehiclePayload['current_cursor'],
            ],
            'vehicleSummary' => $vehicleSummary,
            'vehicleBrandOptions' => $vehicleBrandOptions,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createVehicle(string $tenantId, array $validated): void
    {
        $this->assertTenantVehiclesTableReady('create_vehicle', 'Tabel master kendaraan belum siap.');

        $payload = $this->normalizeVehiclePayload($tenantId, $validated);

        DB::transaction(function () use ($payload): void {
            $this->assertVehicleCombinationIsUnique(
                (string) $payload['tenant_id'],
                (string) $payload['vehicle_type'],
                (string) $payload['brand'],
                (string) $payload['model'],
            );

            TenantVehicleMaster::query()->create($payload);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateVehicle(string $tenantId, string $vehicleId, array $validated): void
    {
        $this->assertTenantVehiclesTableReady('update_vehicle', 'Tabel master kendaraan belum siap.');

        $vehicle = $this->findTenantVehicleOrFail($tenantId, $vehicleId, 'update_vehicle');
        $payload = $this->normalizeVehiclePayload($tenantId, $validated);

        DB::transaction(function () use ($vehicle, $payload): void {
            $this->assertVehicleCombinationIsUnique(
                (string) $payload['tenant_id'],
                (string) $payload['vehicle_type'],
                (string) $payload['brand'],
                (string) $payload['model'],
                (string) $vehicle->id,
            );

            $vehicle->forceFill($payload)->save();
        });
    }

    public function deleteVehicle(string $tenantId, string $vehicleId): void
    {
        $this->assertTenantVehiclesTableReady('delete_vehicle', 'Tabel master kendaraan belum siap.');

        $vehicle = $this->findTenantVehicleOrFail($tenantId, $vehicleId, 'delete_vehicle');

        DB::transaction(function () use ($vehicle): void {
            $vehicle->delete();
        });
    }

    /**
     * @return array<string, int>
     */
    public function syncFromPlatform(string $tenantId): array
    {
        $this->assertTenantVehiclesTableReady('sync_vehicle', 'Tabel master kendaraan belum siap.');

        if (! Schema::hasTable('vehicle_master_models') || ! Schema::hasTable('vehicle_master_brands')) {
            throw ValidationException::withMessages([
                'sync_vehicle' => 'Master kendaraan platform belum tersedia.',
            ]);
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'reactivated' => 0,
        ];

        DB::transaction(function () use ($tenantId, &$summary): void {
            $platformModels = VehicleMasterModel::query()
                ->with('brand:id,name,is_active')
                ->where('is_active', true)
                ->whereHas('brand', fn (Builder $query): Builder => $query->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'vehicle_master_brand_id', 'name', 'vehicle_type']);

            foreach ($platformModels as $platformModel) {
                $brandName = $this->normalizeVehicleLabel((string) ($platformModel->brand?->name ?? ''));
                $modelName = $this->normalizeVehicleLabel((string) $platformModel->name);
                $vehicleType = $this->normalizeVehicleType((string) $platformModel->vehicle_type);

                if ($brandName === '' || $modelName === '') {
                    continue;
                }

                $existing = TenantVehicleMaster::query()
                    ->withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('vehicle_type', $vehicleType)
                    ->whereRaw('LOWER(TRIM(brand)) = ?', [strtolower($brandName)])
                    ->whereRaw('LOWER(TRIM(model)) = ?', [strtolower($modelName)])
                    ->first();

                if (! $existing) {
                    TenantVehicleMaster::query()->create([
                        'tenant_id' => $tenantId,
                        'vehicle_type' => $vehicleType,
                        'brand' => $brandName,
                        'model' => $modelName,
                        'source' => 'platform_sync',
                        'is_active' => true,
                    ]);
                    $summary['created']++;

                    continue;
                }

                $wasDeleted = $existing->trashed();
                $wasInactive = ! (bool) $existing->is_active;

                $existing->forceFill([
                    'vehicle_type' => $vehicleType,
                    'brand' => $brandName,
                    'model' => $modelName,
                    'source' => 'platform_sync',
                    'is_active' => true,
                ])->save();

                if ($wasDeleted) {
                    $existing->restore();
                    $summary['reactivated']++;
                } elseif ($wasInactive) {
                    $summary['reactivated']++;
                } else {
                    $summary['updated']++;
                }
            }
        });

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeVehiclePayload(string $tenantId, array $validated): array
    {
        return [
            'tenant_id' => $tenantId,
            'vehicle_type' => $this->normalizeVehicleType((string) ($validated['vehicle_type'] ?? 'motor')),
            'brand' => $this->normalizeVehicleLabel((string) ($validated['brand'] ?? '')),
            'model' => $this->normalizeVehicleLabel((string) ($validated['model'] ?? '')),
            'source' => array_key_exists('source', $validated)
                ? $this->normalizeSource((string) $validated['source'])
                : 'manual',
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ];
    }

    private function normalizeVehicleLabel(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function normalizeVehicleType(string $value): string
    {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['motor', 'mobil'], true) ? $normalized : 'motor';
    }

    private function normalizeSource(string $value): string
    {
        $normalized = strtolower(trim($value));
        return $normalized !== '' ? $normalized : 'manual';
    }

    private function assertTenantVehiclesTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('tenant_vehicle_masters')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function findTenantVehicleOrFail(string $tenantId, string $vehicleId, string $errorKey): TenantVehicleMaster
    {
        $vehicle = TenantVehicleMaster::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $vehicleId)
            ->first();

        if (! $vehicle) {
            throw ValidationException::withMessages([
                $errorKey => 'Data kendaraan tidak ditemukan.',
            ]);
        }

        return $vehicle;
    }

    private function assertVehicleCombinationIsUnique(
        string $tenantId,
        string $vehicleType,
        string $brand,
        string $model,
        ?string $ignoreVehicleId = null,
    ): void {
        $query = TenantVehicleMaster::query()
            ->where('tenant_id', $tenantId)
            ->where('vehicle_type', $vehicleType)
            ->whereRaw('LOWER(TRIM(brand)) = ?', [strtolower($brand)])
            ->whereRaw('LOWER(TRIM(model)) = ?', [strtolower($model)]);

        if (is_string($ignoreVehicleId) && $ignoreVehicleId !== '') {
            $query->where('id', '!=', $ignoreVehicleId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'brand' => 'Kombinasi merek dan model kendaraan sudah ada untuk tenant ini.',
            ]);
        }
    }

    private function resolveCurrentUri(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['vehicle_type', 'brand', 'model', 'source', 'is_active', 'created_at'], true)
            ? $sortBy
            : 'created_at';
    }

    private function resolveVehicleTypeFilter(string $vehicleType): ?string
    {
        $normalizedVehicleType = strtolower(trim($vehicleType));

        return in_array($normalizedVehicleType, ['motor', 'mobil'], true) ? $normalizedVehicleType : null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveVehicleBrandOptions(string $tenantId, ?string $vehicleTypeFilter): array
    {
        return TenantVehicleMaster::query()
            ->where('tenant_id', $tenantId)
            ->when($vehicleTypeFilter !== null, function (Builder $query) use ($vehicleTypeFilter): void {
                $query->where('vehicle_type', $vehicleTypeFilter);
            })
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->map(function (mixed $brand): string {
                return trim((string) $brand);
            })
            ->filter(function (string $brand): bool {
                return $brand !== '';
            })
            ->values()
            ->all();
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
                ->cursorPaginate($perPage, $columns, 'vehicle_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'vehicle_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
