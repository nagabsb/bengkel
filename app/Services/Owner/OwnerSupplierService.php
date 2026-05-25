<?php

namespace App\Services\Owner;

use App\Models\Supplier;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerSupplierService
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
        $supplierSearch = trim((string) $request->query('supplier_search', ''));
        $supplierSortBy = $this->resolveSortBy((string) $request->query('supplier_sort_by', 'created_at'));
        $supplierSortDir = $this->resolveSortDirection((string) $request->query('supplier_sort_dir', 'desc'));
        $supplierPerPage = $this->resolvePerPage((int) $request->query('supplier_per_page', 10));
        $supplierCursor = trim((string) $request->query('supplier_cursor', ''));

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

        $supplierPayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $supplierPerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $supplierSummary = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
        ];

        if (Schema::hasTable('suppliers')) {
            $summaryQuery = Supplier::query()
                ->where('tenant_id', $tenantId);

            $totalSuppliers = (int) (clone $summaryQuery)->count();
            $activeSuppliers = (int) (clone $summaryQuery)
                ->where('is_active', true)
                ->count();

            $supplierSummary = [
                'total' => $totalSuppliers,
                'active' => $activeSuppliers,
                'inactive' => max($totalSuppliers - $activeSuppliers, 0),
            ];

            $sortableColumn = [
                'name' => 'suppliers.name',
                'phone' => 'suppliers.phone',
                'email' => 'suppliers.email',
                'is_active' => 'suppliers.is_active',
                'created_at' => 'suppliers.created_at',
            ][$supplierSortBy] ?? 'suppliers.created_at';

            $supplierPaginator = $this->cursorPaginateWithFallback(
                Supplier::query()
                    ->where('tenant_id', $tenantId)
                    ->when($supplierSearch !== '', function (Builder $query) use ($supplierSearch): void {
                        $query->where(function (Builder $nestedQuery) use ($supplierSearch): void {
                            $nestedQuery
                                ->where('name', 'like', "%{$supplierSearch}%")
                                ->orWhere('phone', 'like', "%{$supplierSearch}%")
                                ->orWhere('email', 'like', "%{$supplierSearch}%")
                                ->orWhere('pic_name', 'like', "%{$supplierSearch}%")
                                ->orWhere('pic_phone', 'like', "%{$supplierSearch}%");
                        });
                    })
                    ->orderBy($sortableColumn, $supplierSortDir)
                    ->orderBy('suppliers.id', $supplierSortDir),
                $supplierPerPage,
                [
                    'suppliers.id',
                    'suppliers.name',
                    'suppliers.phone',
                    'suppliers.email',
                    'suppliers.address',
                    'suppliers.pic_name',
                    'suppliers.pic_phone',
                    'suppliers.notes',
                    'suppliers.is_active',
                    'suppliers.created_at',
                    'suppliers.updated_at',
                ],
                $supplierCursor,
            );

            $supplierRows = collect($supplierPaginator->items())
                ->map(function (Supplier $supplier): array {
                    return [
                        'id' => (string) $supplier->id,
                        'name' => (string) $supplier->name,
                        'phone' => (string) ($supplier->phone ?? ''),
                        'email' => (string) ($supplier->email ?? ''),
                        'address' => (string) ($supplier->address ?? ''),
                        'pic_name' => (string) ($supplier->pic_name ?? ''),
                        'pic_phone' => (string) ($supplier->pic_phone ?? ''),
                        'notes' => (string) ($supplier->notes ?? ''),
                        'is_active' => (bool) $supplier->is_active,
                        'created_at' => $supplier->created_at,
                        'updated_at' => $supplier->updated_at,
                    ];
                })
                ->values();

            $supplierPayload = [
                'mode' => 'cursor',
                'data' => $supplierRows->all(),
                'per_page' => $supplierPaginator->perPage(),
                'total' => $totalSuppliers,
                'from' => $supplierRows->isEmpty() ? 0 : 1,
                'to' => $supplierRows->count(),
                'current_cursor' => $supplierPaginator->cursor()?->encode(),
                'next_cursor' => $supplierPaginator->nextCursor()?->encode(),
                'prev_cursor' => $supplierPaginator->previousCursor()?->encode(),
                'has_more_pages' => $supplierPaginator->hasMorePages(),
            ];
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'suppliers' => $supplierPayload,
            'supplierFilters' => [
                'search' => $supplierSearch,
                'sort_by' => $supplierSortBy,
                'sort_dir' => $supplierSortDir,
                'per_page' => $supplierPerPage,
                'cursor' => $supplierPayload['current_cursor'],
            ],
            'supplierSummary' => $supplierSummary,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createSupplier(string $tenantId, array $validated): void
    {
        $this->assertSuppliersTableReady('create_supplier', 'Tabel supplier belum siap.');

        DB::transaction(function () use ($tenantId, $validated): void {
            Supplier::query()->create($this->normalizeSupplierPayload($tenantId, $validated));
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateSupplier(string $tenantId, string $supplierId, array $validated): void
    {
        $this->assertSuppliersTableReady('update_supplier', 'Tabel supplier belum siap.');

        $supplier = $this->findTenantSupplierOrFail($tenantId, $supplierId, 'update_supplier');

        DB::transaction(function () use ($tenantId, $validated, $supplier): void {
            $supplier->forceFill($this->normalizeSupplierPayload($tenantId, $validated))
                ->save();
        });
    }

    public function deleteSupplier(string $tenantId, string $supplierId): void
    {
        $this->assertSuppliersTableReady('delete_supplier', 'Tabel supplier belum siap.');

        $supplier = $this->findTenantSupplierOrFail($tenantId, $supplierId, 'delete_supplier');

        DB::transaction(function () use ($supplier): void {
            $supplier->delete();
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
    private function normalizeSupplierPayload(string $tenantId, array $validated): array
    {
        return [
            'tenant_id' => $tenantId,
            'name' => trim((string) ($validated['name'] ?? '')),
            'phone' => $this->normalizeNullableString($validated['phone'] ?? null),
            'email' => $this->normalizeNullableString($validated['email'] ?? null),
            'address' => $this->normalizeNullableString($validated['address'] ?? null),
            'pic_name' => $this->normalizeNullableString($validated['pic_name'] ?? null),
            'pic_phone' => $this->normalizeNullableString($validated['pic_phone'] ?? null),
            'notes' => $this->normalizeNullableString($validated['notes'] ?? null),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function assertSuppliersTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('suppliers')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function findTenantSupplierOrFail(string $tenantId, string $supplierId, string $errorKey): Supplier
    {
        $supplier = Supplier::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $supplierId)
            ->first();

        if (! $supplier) {
            throw ValidationException::withMessages([
                $errorKey => 'Supplier tidak ditemukan.',
            ]);
        }

        return $supplier;
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

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'supplier_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'supplier_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
