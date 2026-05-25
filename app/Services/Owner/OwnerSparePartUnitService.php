<?php

namespace App\Services\Owner;

use App\Models\SparePartUnit;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerSparePartUnitService
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
        $search = trim((string) $request->query('sparepart_unit_search', ''));
        $sortBy = $this->resolveSortBy((string) $request->query('sparepart_unit_sort_by', 'created_at'));
        $sortDir = $this->resolveSortDirection((string) $request->query('sparepart_unit_sort_dir', 'desc'));
        $perPage = $this->resolvePerPage((int) $request->query('sparepart_unit_per_page', 10));
        $cursor = trim((string) $request->query('sparepart_unit_cursor', ''));

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

        $payload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $perPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $summary = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
        ];

        if (Schema::hasTable('spare_part_units')) {
            $summaryQuery = SparePartUnit::query()
                ->where('tenant_id', $tenantId);

            $total = (int) (clone $summaryQuery)->count();
            $active = (int) (clone $summaryQuery)
                ->where('is_active', true)
                ->count();

            $summary = [
                'total' => $total,
                'active' => $active,
                'inactive' => max($total - $active, 0),
            ];

            $sortableColumn = [
                'name' => 'spare_part_units.name',
                'symbol' => 'spare_part_units.symbol',
                'is_active' => 'spare_part_units.is_active',
                'created_at' => 'spare_part_units.created_at',
            ][$sortBy] ?? 'spare_part_units.created_at';

            $paginator = $this->cursorPaginateWithFallback(
                SparePartUnit::query()
                    ->where('tenant_id', $tenantId)
                    ->when($search !== '', function (Builder $query) use ($search): void {
                        $query->where(function (Builder $nestedQuery) use ($search): void {
                            $nestedQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('symbol', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        });
                    })
                    ->orderBy($sortableColumn, $sortDir)
                    ->orderBy('spare_part_units.id', $sortDir),
                $perPage,
                [
                    'spare_part_units.id',
                    'spare_part_units.name',
                    'spare_part_units.symbol',
                    'spare_part_units.description',
                    'spare_part_units.is_active',
                    'spare_part_units.created_at',
                    'spare_part_units.updated_at',
                ],
                $cursor,
            );

            $rows = collect($paginator->items())
                ->map(function (SparePartUnit $unit): array {
                    return [
                        'id' => (string) $unit->id,
                        'name' => (string) $unit->name,
                        'symbol' => (string) ($unit->symbol ?? ''),
                        'description' => (string) ($unit->description ?? ''),
                        'is_active' => (bool) $unit->is_active,
                        'created_at' => $unit->created_at,
                        'updated_at' => $unit->updated_at,
                    ];
                })
                ->values();

            $payload = [
                'mode' => 'cursor',
                'data' => $rows->all(),
                'per_page' => $paginator->perPage(),
                'total' => $total,
                'from' => $rows->isEmpty() ? 0 : 1,
                'to' => $rows->count(),
                'current_cursor' => $paginator->cursor()?->encode(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more_pages' => $paginator->hasMorePages(),
            ];
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'sparePartUnits' => $payload,
            'sparePartUnitFilters' => [
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'per_page' => $perPage,
                'cursor' => $payload['current_cursor'],
            ],
            'sparePartUnitSummary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createUnit(string $tenantId, array $validated): void
    {
        $this->assertTableReady('create_sparepart_unit', 'Tabel satuan sparepart belum siap.');

        DB::transaction(function () use ($tenantId, $validated): void {
            SparePartUnit::query()->create($this->normalizePayload($tenantId, $validated));
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateUnit(string $tenantId, string $unitId, array $validated): void
    {
        $this->assertTableReady('update_sparepart_unit', 'Tabel satuan sparepart belum siap.');

        $unit = $this->findOrFail($tenantId, $unitId, 'update_sparepart_unit');

        DB::transaction(function () use ($tenantId, $validated, $unit): void {
            $unit->forceFill($this->normalizePayload($tenantId, $validated))->save();
        });
    }

    public function deleteUnit(string $tenantId, string $unitId): void
    {
        $this->assertTableReady('delete_sparepart_unit', 'Tabel satuan sparepart belum siap.');

        $unit = $this->findOrFail($tenantId, $unitId, 'delete_sparepart_unit');

        DB::transaction(function () use ($unit): void {
            $unit->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(string $tenantId, array $validated): array
    {
        return [
            'tenant_id' => $tenantId,
            'name' => trim((string) ($validated['name'] ?? '')),
            'symbol' => $this->normalizeNullableString($validated['symbol'] ?? null),
            'description' => $this->normalizeNullableString($validated['description'] ?? null),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function findOrFail(string $tenantId, string $unitId, string $errorKey): SparePartUnit
    {
        $unit = SparePartUnit::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $unitId)
            ->first();

        if (! $unit) {
            throw ValidationException::withMessages([
                $errorKey => 'Satuan sparepart tidak ditemukan.',
            ]);
        }

        return $unit;
    }

    private function assertTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('spare_part_units')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
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
        return in_array($sortBy, ['name', 'symbol', 'is_active', 'created_at'], true)
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
                ->cursorPaginate($perPage, $columns, 'sparepart_unit_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'sparepart_unit_cursor_fallback', null)
                ->withQueryString();
        }
    }
}

