<?php

namespace App\Services\Owner;

use App\Models\SparePartCategory;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerSparePartCategoryService
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
        $search = trim((string) $request->query('sparepart_category_search', ''));
        $sortBy = $this->resolveSortBy((string) $request->query('sparepart_category_sort_by', 'created_at'));
        $sortDir = $this->resolveSortDirection((string) $request->query('sparepart_category_sort_dir', 'desc'));
        $perPage = $this->resolvePerPage((int) $request->query('sparepart_category_per_page', 10));
        $cursor = trim((string) $request->query('sparepart_category_cursor', ''));

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

        if (Schema::hasTable('spare_part_categories')) {
            $summaryQuery = SparePartCategory::query()
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
                'name' => 'spare_part_categories.name',
                'is_active' => 'spare_part_categories.is_active',
                'created_at' => 'spare_part_categories.created_at',
            ][$sortBy] ?? 'spare_part_categories.created_at';

            $paginator = $this->cursorPaginateWithFallback(
                SparePartCategory::query()
                    ->where('tenant_id', $tenantId)
                    ->when($search !== '', function (Builder $query) use ($search): void {
                        $query->where(function (Builder $nestedQuery) use ($search): void {
                            $nestedQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        });
                    })
                    ->orderBy($sortableColumn, $sortDir)
                    ->orderBy('spare_part_categories.id', $sortDir),
                $perPage,
                [
                    'spare_part_categories.id',
                    'spare_part_categories.name',
                    'spare_part_categories.description',
                    'spare_part_categories.is_active',
                    'spare_part_categories.created_at',
                    'spare_part_categories.updated_at',
                ],
                $cursor,
            );

            $rows = collect($paginator->items())
                ->map(function (SparePartCategory $category): array {
                    return [
                        'id' => (string) $category->id,
                        'name' => (string) $category->name,
                        'description' => (string) ($category->description ?? ''),
                        'is_active' => (bool) $category->is_active,
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at,
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
            'sparePartCategories' => $payload,
            'sparePartCategoryFilters' => [
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'per_page' => $perPage,
                'cursor' => $payload['current_cursor'],
            ],
            'sparePartCategorySummary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createCategory(string $tenantId, array $validated): void
    {
        $this->assertTableReady('create_sparepart_category', 'Tabel kategori sparepart belum siap.');

        DB::transaction(function () use ($tenantId, $validated): void {
            SparePartCategory::query()->create($this->normalizePayload($tenantId, $validated));
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateCategory(string $tenantId, string $categoryId, array $validated): void
    {
        $this->assertTableReady('update_sparepart_category', 'Tabel kategori sparepart belum siap.');

        $category = $this->findOrFail($tenantId, $categoryId, 'update_sparepart_category');

        DB::transaction(function () use ($tenantId, $validated, $category): void {
            $category->forceFill($this->normalizePayload($tenantId, $validated))->save();
        });
    }

    public function deleteCategory(string $tenantId, string $categoryId): void
    {
        $this->assertTableReady('delete_sparepart_category', 'Tabel kategori sparepart belum siap.');

        $category = $this->findOrFail($tenantId, $categoryId, 'delete_sparepart_category');

        DB::transaction(function () use ($category): void {
            $category->delete();
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
            'description' => $this->normalizeNullableString($validated['description'] ?? null),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function findOrFail(string $tenantId, string $categoryId, string $errorKey): SparePartCategory
    {
        $category = SparePartCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $categoryId)
            ->first();

        if (! $category) {
            throw ValidationException::withMessages([
                $errorKey => 'Kategori sparepart tidak ditemukan.',
            ]);
        }

        return $category;
    }

    private function assertTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('spare_part_categories')) {
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
        return in_array($sortBy, ['name', 'is_active', 'created_at'], true)
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
                ->cursorPaginate($perPage, $columns, 'sparepart_category_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'sparepart_category_cursor_fallback', null)
                ->withQueryString();
        }
    }
}

