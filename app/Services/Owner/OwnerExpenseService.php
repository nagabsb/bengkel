<?php

namespace App\Services\Owner;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Workshop;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerExpenseService
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
        $expenseSearch = trim((string) $request->query('expense_search', ''));
        $expenseCategory = trim((string) $request->query('expense_category', ''));
        $expenseSortBy = $this->resolveSortBy((string) $request->query('expense_sort_by', 'expense_date'));
        $expenseSortDir = $this->resolveSortDirection((string) $request->query('expense_sort_dir', 'desc'));
        $expensePerPage = $this->resolvePerPage((int) $request->query('expense_per_page', 10));
        $expenseCursor = trim((string) $request->query('expense_cursor', ''));
        $expensePeriod = $this->resolvePeriod((string) $request->query('expense_period', 'all'));
        $expenseDateFromInput = trim((string) $request->query('expense_date_from', ''));
        $expenseDateToInput = trim((string) $request->query('expense_date_to', ''));
        $expenseWorkshopFilterInput = trim((string) $request->query('expense_workshop_id', ''));

        $resolvedDateFilter = $this->resolveDateFilter(
            $expensePeriod,
            $expenseDateFromInput,
            $expenseDateToInput,
        );
        $expensePeriod = $resolvedDateFilter['period'];
        $expenseDateFrom = $resolvedDateFilter['date_from'];
        $expenseDateTo = $resolvedDateFilter['date_to'];

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

        $expensePayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $expensePerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $expenseSummary = [
            'total_entries' => 0,
            'total_amount' => 0,
            'this_month_entries' => 0,
            'this_month_amount' => 0,
            'period_label' => 'Bulan Ini',
        ];

        $expenseRecapByWorkshop = [];
        $expenseCategoryOptions = [];
        $expenseWorkshopFilter = $this->resolveWorkshopFilter(
            $tenantId,
            $activeWorkshopId,
            $expenseWorkshopFilterInput,
        );

        if (Schema::hasTable('expenses')) {
            $scopedBaseQuery = Expense::query();
            $this->applyExpenseScope(
                $scopedBaseQuery,
                $tenantId,
                $activeWorkshopId,
                $expenseWorkshopFilter,
            );

            $totalEntries = (int) (clone $scopedBaseQuery)->count();
            $totalAmount = (int) ((clone $scopedBaseQuery)->sum('amount') ?? 0);

            $hasDateRangeFilter = $expenseDateFrom !== null || $expenseDateTo !== null;
            $periodLabel = 'Bulan Ini';

            if ($hasDateRangeFilter) {
                $periodSummaryQuery = Expense::query();
                $this->applyExpenseScope(
                    $periodSummaryQuery,
                    $tenantId,
                    $activeWorkshopId,
                    $expenseWorkshopFilter,
                    $expenseDateFrom,
                    $expenseDateTo,
                );
                $periodLabel = $this->resolvePeriodLabel($expensePeriod, $expenseDateFrom, $expenseDateTo);
            } else {
                $monthStart = Carbon::now()->startOfMonth()->toDateString();
                $monthEnd = Carbon::now()->endOfMonth()->toDateString();
                $periodSummaryQuery = (clone $scopedBaseQuery)
                    ->whereDate('expense_date', '>=', $monthStart)
                    ->whereDate('expense_date', '<=', $monthEnd);
            }

            $expenseSummary = [
                'total_entries' => $totalEntries,
                'total_amount' => $totalAmount,
                'this_month_entries' => (int) (clone $periodSummaryQuery)->count(),
                'this_month_amount' => (int) ((clone $periodSummaryQuery)->sum('amount') ?? 0),
                'period_label' => $periodLabel,
            ];

            if (Schema::hasTable('expense_categories')) {
                $expenseCategoryOptions = ExpenseCategory::query()
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->limit(100)
                    ->pluck('name')
                    ->map(fn ($name): string => trim((string) $name))
                    ->filter(fn (string $name): bool => $name !== '')
                    ->values()
                    ->all();
            } else {
                $fallbackCategoryQuery = Expense::query();
                $this->applyExpenseScope(
                    $fallbackCategoryQuery,
                    $tenantId,
                    $activeWorkshopId,
                    $expenseWorkshopFilter,
                );

                $expenseCategoryOptions = $fallbackCategoryQuery
                    ->whereNotNull('category')
                    ->select('category')
                    ->distinct()
                    ->orderBy('category')
                    ->limit(30)
                    ->pluck('category')
                    ->map(fn ($category): string => trim((string) $category))
                    ->filter(fn (string $category): bool => $category !== '')
                    ->values()
                    ->all();
            }

            $expenseRecapQuery = Expense::query()
                ->leftJoin('workshops', 'workshops.id', '=', 'expenses.workshop_id');
            $this->applyExpenseScope(
                $expenseRecapQuery,
                $tenantId,
                $activeWorkshopId,
                $expenseWorkshopFilter,
                $expenseDateFrom,
                $expenseDateTo,
                'expenses.',
            );

            $expenseRecapByWorkshop = $expenseRecapQuery
                ->groupBy('expenses.workshop_id', 'workshops.name', 'workshops.code')
                ->orderByDesc('total_amount')
                ->orderByDesc('total_entries')
                ->selectRaw('expenses.workshop_id as workshop_id')
                ->selectRaw("COALESCE(workshops.name, 'Cabang Tidak Diketahui') as workshop_name")
                ->selectRaw("COALESCE(workshops.code, '-') as workshop_code")
                ->selectRaw('COUNT(expenses.id) as total_entries')
                ->selectRaw('COALESCE(SUM(expenses.amount), 0) as total_amount')
                ->limit(10)
                ->get()
                ->map(function ($row): array {
                    return [
                        'workshop_id' => trim((string) ($row->workshop_id ?? '')),
                        'workshop_name' => trim((string) ($row->workshop_name ?? '')) ?: 'Cabang',
                        'workshop_code' => trim((string) ($row->workshop_code ?? '')) ?: '-',
                        'total_entries' => (int) ($row->total_entries ?? 0),
                        'total_amount' => (int) ($row->total_amount ?? 0),
                    ];
                })
                ->values()
                ->all();

            $sortableColumn = [
                'expense_date' => 'expenses.expense_date',
                'description' => 'expenses.description',
                'category' => 'expenses.category',
                'amount' => 'expenses.amount',
                'created_at' => 'expenses.created_at',
            ][$expenseSortBy] ?? 'expenses.expense_date';

            $expenseListQuery = Expense::query();
            $this->applyExpenseScope(
                $expenseListQuery,
                $tenantId,
                $activeWorkshopId,
                $expenseWorkshopFilter,
                $expenseDateFrom,
                $expenseDateTo,
            );

            $expenseListQuery
                ->with(['workshop:id,name,code'])
                ->when($expenseSearch !== '', function (Builder $query) use ($expenseSearch): void {
                    $query->where(function (Builder $nestedQuery) use ($expenseSearch): void {
                        $nestedQuery
                            ->where('description', 'like', "%{$expenseSearch}%")
                            ->orWhere('category', 'like', "%{$expenseSearch}%")
                            ->orWhere('reference_number', 'like', "%{$expenseSearch}%")
                            ->orWhere('notes', 'like', "%{$expenseSearch}%");
                    });
                })
                ->when($expenseCategory !== '', function (Builder $query) use ($expenseCategory): void {
                    $query->where('category', $expenseCategory);
                });

            $filteredTotalEntries = (int) (clone $expenseListQuery)->count();

            $expensePaginator = $this->cursorPaginateWithFallback(
                (clone $expenseListQuery)
                    ->orderBy($sortableColumn, $expenseSortDir)
                    ->orderBy('expenses.id', $expenseSortDir),
                $expensePerPage,
                [
                    'expenses.id',
                    'expenses.workshop_id',
                    'expenses.expense_date',
                    'expenses.category',
                    'expenses.description',
                    'expenses.reference_number',
                    'expenses.notes',
                    'expenses.amount',
                    'expenses.created_at',
                    'expenses.updated_at',
                ],
                $expenseCursor,
            );

            $expenseRows = collect($expensePaginator->items())
                ->map(function (Expense $expense): array {
                    $workshopName = trim((string) ($expense->workshop?->name ?? ''));
                    $workshopCode = trim((string) ($expense->workshop?->code ?? ''));

                    return [
                        'id' => (string) $expense->id,
                        'workshop_id' => (string) ($expense->workshop_id ?? ''),
                        'workshop_name' => $workshopName,
                        'workshop_code' => $workshopCode,
                        'expense_date' => $expense->expense_date,
                        'category' => (string) ($expense->category ?? ''),
                        'description' => (string) ($expense->description ?? ''),
                        'reference_number' => (string) ($expense->reference_number ?? ''),
                        'notes' => (string) ($expense->notes ?? ''),
                        'amount' => (int) ($expense->amount ?? 0),
                        'created_at' => $expense->created_at,
                        'updated_at' => $expense->updated_at,
                    ];
                })
                ->values();

            $expensePayload = [
                'mode' => 'cursor',
                'data' => $expenseRows->all(),
                'per_page' => $expensePaginator->perPage(),
                'total' => $filteredTotalEntries,
                'from' => $expenseRows->isEmpty() ? 0 : 1,
                'to' => $expenseRows->count(),
                'current_cursor' => $expensePaginator->cursor()?->encode(),
                'next_cursor' => $expensePaginator->nextCursor()?->encode(),
                'prev_cursor' => $expensePaginator->previousCursor()?->encode(),
                'has_more_pages' => $expensePaginator->hasMorePages(),
            ];
        }

        $activeWorkshopPayload = $this->resolveActiveWorkshopPayload($request, $tenantId, $activeWorkshopId);

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'expenses' => $expensePayload,
            'expenseFilters' => [
                'search' => $expenseSearch,
                'category' => $expenseCategory,
                'sort_by' => $expenseSortBy,
                'sort_dir' => $expenseSortDir,
                'per_page' => $expensePerPage,
                'cursor' => $expensePayload['current_cursor'],
                'period' => $expensePeriod,
                'date_from' => $expenseDateFrom ?? '',
                'date_to' => $expenseDateTo ?? '',
                'workshop_id' => $expenseWorkshopFilter,
            ],
            'expenseSummary' => $expenseSummary,
            'expenseRecapByWorkshop' => $expenseRecapByWorkshop,
            'expenseCategoryOptions' => $expenseCategoryOptions,
            'activeWorkshop' => $activeWorkshopPayload,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createExpense(
        string $tenantId,
        string $activeWorkshopId,
        array $validated,
        ?Authenticatable $actor = null,
    ): void {
        $this->assertExpensesTableReady('create_expense', 'Tabel pengeluaran belum siap.');
        $targetWorkshopId = $this->resolveTargetWorkshopId($tenantId, $activeWorkshopId, $validated, 'workshop_id');

        DB::transaction(function () use ($tenantId, $targetWorkshopId, $validated, $actor): void {
            Expense::query()->create([
                ...$this->normalizeExpensePayload($tenantId, $targetWorkshopId, $validated),
                'created_by_user_id' => $this->resolveActorUserId($actor),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateExpense(
        string $tenantId,
        string $activeWorkshopId,
        string $expenseId,
        array $validated,
    ): void {
        $this->assertExpensesTableReady('update_expense', 'Tabel pengeluaran belum siap.');

        $expense = $this->findTenantExpenseOrFail($tenantId, $activeWorkshopId, $expenseId, 'update_expense');
        $targetWorkshopId = $this->resolveTargetWorkshopId(
            $tenantId,
            $activeWorkshopId,
            $validated,
            'workshop_id',
            fallbackWorkshopId: (string) ($expense->workshop_id ?? ''),
        );

        DB::transaction(function () use ($tenantId, $targetWorkshopId, $validated, $expense): void {
            $expense->forceFill(
                $this->normalizeExpensePayload($tenantId, $targetWorkshopId, $validated),
            )->save();
        });
    }

    public function deleteExpense(string $tenantId, string $activeWorkshopId, string $expenseId): void
    {
        $this->assertExpensesTableReady('delete_expense', 'Tabel pengeluaran belum siap.');

        $expense = $this->findTenantExpenseOrFail($tenantId, $activeWorkshopId, $expenseId, 'delete_expense');

        DB::transaction(function () use ($expense): void {
            $expense->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeExpensePayload(string $tenantId, string $workshopId, array $validated): array
    {
        return [
            'tenant_id' => $tenantId,
            'workshop_id' => $workshopId,
            'expense_date' => (string) ($validated['expense_date'] ?? now()->toDateString()),
            'category' => trim((string) ($validated['category'] ?? '')),
            'description' => trim((string) ($validated['description'] ?? '')),
            'reference_number' => $this->normalizeNullableString($validated['reference_number'] ?? null),
            'notes' => $this->normalizeNullableString($validated['notes'] ?? null),
            'amount' => max((int) ($validated['amount'] ?? 0), 0),
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveActorUserId(?Authenticatable $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        $actorId = trim((string) $actor->getAuthIdentifier());

        return $actorId !== '' ? $actorId : null;
    }

    private function assertExpensesTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('expenses')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function findTenantExpenseOrFail(
        string $tenantId,
        string $activeWorkshopId,
        string $expenseId,
        string $errorKey,
    ): Expense {
        $expense = Expense::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyWorkshopScope($activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->where('id', $expenseId)
            ->first();

        if (! $expense) {
            throw ValidationException::withMessages([
                $errorKey => 'Data pengeluaran tidak ditemukan di cabang aktif.',
            ]);
        }

        return $expense;
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['expense_date', 'description', 'category', 'amount', 'created_at'], true)
            ? $sortBy
            : 'expense_date';
    }

    private function resolveSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
    }

    private function resolvePerPage(int $perPage): int
    {
        return in_array($perPage, [10, 20, 50], true) ? $perPage : 10;
    }

    private function resolvePeriod(string $period): string
    {
        $normalized = strtolower(trim($period));

        return in_array($normalized, ['all', 'daily', 'weekly', 'monthly', 'custom'], true)
            ? $normalized
            : 'all';
    }

    /**
     * @return array{period: string, date_from: ?string, date_to: ?string}
     */
    private function resolveDateFilter(string $period, string $dateFromInput, string $dateToInput): array
    {
        $now = Carbon::now();
        $resolvedPeriod = $this->resolvePeriod($period);
        $resolvedFrom = null;
        $resolvedTo = null;

        if ($resolvedPeriod === 'daily') {
            $resolvedFrom = $now->copy()->startOfDay()->toDateString();
            $resolvedTo = $now->copy()->endOfDay()->toDateString();
        } elseif ($resolvedPeriod === 'weekly') {
            $resolvedFrom = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $resolvedTo = $now->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
        } elseif ($resolvedPeriod === 'monthly') {
            $resolvedFrom = $now->copy()->startOfMonth()->toDateString();
            $resolvedTo = $now->copy()->endOfMonth()->toDateString();
        } else {
            $resolvedFrom = $this->normalizeDateString($dateFromInput);
            $resolvedTo = $this->normalizeDateString($dateToInput);

            if ($resolvedFrom === null && $resolvedTo === null && $resolvedPeriod === 'custom') {
                $resolvedPeriod = 'all';
            }

            if ($resolvedFrom !== null || $resolvedTo !== null) {
                $resolvedPeriod = 'custom';
            }
        }

        if ($resolvedFrom !== null && $resolvedTo !== null && $resolvedFrom > $resolvedTo) {
            [$resolvedFrom, $resolvedTo] = [$resolvedTo, $resolvedFrom];
        }

        return [
            'period' => $resolvedPeriod,
            'date_from' => $resolvedFrom,
            'date_to' => $resolvedTo,
        ];
    }

    private function resolvePeriodLabel(string $period, ?string $dateFrom, ?string $dateTo): string
    {
        return match ($period) {
            'daily' => 'Hari Ini',
            'weekly' => 'Minggu Ini',
            'monthly' => 'Bulan Ini',
            'custom' => ($dateFrom !== null && $dateTo !== null && $dateFrom === $dateTo)
                ? 'Tanggal Terpilih'
                : 'Rentang Tanggal',
            default => 'Bulan Ini',
        };
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

    private function applyExpenseScope(
        Builder $query,
        string $tenantId,
        string $activeWorkshopId,
        string $workshopFilterId = '',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $columnPrefix = '',
    ): void {
        $prefix = trim($columnPrefix);
        if ($prefix !== '' && ! str_ends_with($prefix, '.')) {
            $prefix .= '.';
        }

        $query->where($prefix.'tenant_id', $tenantId);

        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $query->where($prefix.'workshop_id', $activeWorkshopId);
        }

        if (trim($workshopFilterId) !== '') {
            $query->where($prefix.'workshop_id', trim($workshopFilterId));
        }

        if ($dateFrom !== null) {
            $query->whereDate($prefix.'expense_date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate($prefix.'expense_date', '<=', $dateTo);
        }
    }

    private function normalizeDateString(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::parse($normalized)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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
                ->cursorPaginate($perPage, $columns, 'expense_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'expense_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
