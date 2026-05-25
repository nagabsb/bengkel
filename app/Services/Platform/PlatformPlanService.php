<?php

namespace App\Services\Platform;

use App\Models\Menu;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformPlanService
{
    /**
     * @return array<string, mixed>
     */
    public function buildPageData(Request $request): array
    {
        $planSearch = trim((string) $request->query('plan_search', ''));
        $planSortBy = $this->resolveSortBy((string) $request->query('plan_sort_by', 'price'));
        $planSortDir = $this->resolveSortDirection((string) $request->query('plan_sort_dir', 'asc'));
        $planPerPage = $this->resolvePerPage((int) $request->query('plan_per_page', 10));
        $planCursor = trim((string) $request->query('plan_cursor', ''));

        $plansPayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $planPerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        if (Schema::hasTable('plans')) {
            $baseQuery = Plan::query()
                ->when($planSearch !== '', function ($query) use ($planSearch) {
                    $query->where(function ($nestedQuery) use ($planSearch) {
                        $nestedQuery
                            ->where('name', 'like', "%{$planSearch}%")
                            ->orWhere('slug', 'like', "%{$planSearch}%");
                    });
                });

            $planTotal = (int) (clone $baseQuery)->count();
            $sortableColumn = [
                'name' => 'plans.name',
                'slug' => 'plans.slug',
                'is_active' => 'plans.is_active',
                'created_at' => 'plans.created_at',
            ][$planSortBy] ?? 'plans.name';

            $planQuery = (clone $baseQuery)
                ->with([
                    'prices' => fn ($query) => $query
                        ->orderBy('duration_months')
                        ->orderBy('id'),
                    'menus' => fn ($query) => $query->select('menus.id'),
                ]);

            if ($planSortBy === 'price') {
                $planPriceOrderQuery = PlanPrice::query()
                    ->selectRaw('plan_id, MIN(price) as min_price')
                    ->groupBy('plan_id');

                $planQuery
                    ->leftJoinSub($planPriceOrderQuery, 'plan_price_order', function ($join): void {
                        $join->on('plans.id', '=', 'plan_price_order.plan_id');
                    })
                    ->select([
                        'plans.*',
                        DB::raw('COALESCE(plan_price_order.min_price, 0) as plan_sort_price'),
                    ])
                    ->orderBy('plan_sort_price', $planSortDir);
            } else {
                $planQuery
                    ->select('plans.*')
                    ->orderBy($sortableColumn, $planSortDir);
            }

            $planQuery->orderBy('plans.id', $planSortDir);

            $planPaginator = $this->cursorPaginateWithFallback(
                $planQuery,
                $planPerPage,
                ['*'],
                $planCursor,
            );

            $planRows = collect($planPaginator->items())
                ->map(function (Plan $plan): array {
                    $primaryPrice = $plan->prices->first();
                    $menuIds = $plan->menus
                        ->pluck('id')
                        ->map(fn ($menuId): int => (int) $menuId)
                        ->values()
                        ->all();

                    return [
                        'id' => (int) $plan->id,
                        'name' => (string) $plan->name,
                        'slug' => (string) $plan->slug,
                        'max_workshops' => (int) $plan->max_workshops,
                        'max_users_per_ws' => (int) $plan->max_users_per_ws,
                        'has_ai_feature' => (bool) $plan->has_ai_feature,
                        'has_notification' => (bool) $plan->has_notification,
                        'has_loyalty' => (bool) $plan->has_loyalty,
                        'has_trial' => (bool) $plan->has_trial,
                        'trial_duration_days' => (int) $plan->trial_duration_days,
                        'is_active' => (bool) $plan->is_active,
                        'menu_ids' => $menuIds,
                        'menu_count' => count($menuIds),
                        'price' => $primaryPrice
                            ? [
                                'id' => (int) $primaryPrice->id,
                                'label' => (string) $primaryPrice->label,
                                'duration_months' => (int) $primaryPrice->duration_months,
                                'amount' => (float) $primaryPrice->price,
                                'discount_pct' => (int) $primaryPrice->discount_pct,
                                'is_active' => (bool) $primaryPrice->is_active,
                            ]
                            : null,
                    ];
                })
                ->values();

            $plansPayload = [
                'mode' => 'cursor',
                'data' => $planRows->all(),
                'per_page' => $planPaginator->perPage(),
                'total' => $planTotal,
                'from' => $planRows->isEmpty() ? 0 : 1,
                'to' => $planRows->count(),
                'current_cursor' => $planPaginator->cursor()?->encode(),
                'next_cursor' => $planPaginator->nextCursor()?->encode(),
                'prev_cursor' => $planPaginator->previousCursor()?->encode(),
                'has_more_pages' => $planPaginator->hasMorePages(),
            ];
        }

        $menuOptions = [];
        if (Schema::hasTable('menus')) {
            $menuOptions = Menu::query()
                ->whereNull('tenant_id')
                ->where('menu_type', 'system')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'parent_id', 'label', 'route', 'sort_order'])
                ->map(fn (Menu $menu): array => [
                    'id' => (int) $menu->id,
                    'label' => (string) $menu->label,
                    'route' => $menu->route ? (string) $menu->route : null,
                    'parent_id' => $menu->parent_id ? (int) $menu->parent_id : null,
                    'sort_order' => (int) $menu->sort_order,
                ])
                ->values()
                ->all();
        }

        $tenantsCount = Schema::hasTable('tenants')
            ? (int) Tenant::query()->count()
            : 0;

        return [
            'plans' => $plansPayload,
            'planFilters' => [
                'search' => $planSearch,
                'sort_by' => $planSortBy,
                'sort_dir' => $planSortDir,
                'per_page' => $planPerPage,
                'cursor' => $plansPayload['current_cursor'],
            ],
            'menuOptions' => $menuOptions,
            'tenantsCount' => $tenantsCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createPlan(array $validated): void
    {
        $this->assertPlanTablesReady('create_plan', 'Tabel plan belum siap.');

        $menuIds = $this->resolveValidSystemMenuIds($validated['menu_ids'] ?? []);

        DB::transaction(function () use ($validated, $menuIds): void {
            $plan = Plan::query()->create($this->normalizePlanPayload($validated));

            $this->upsertPlanPrice(
                $plan,
                (int) $validated['duration_months'],
                (float) $validated['price'],
                (int) ($validated['discount_pct'] ?? 0),
                (bool) ($validated['is_active'] ?? true),
            );

            $plan->menus()->sync($menuIds);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updatePlan(int $planId, array $validated): void
    {
        $this->assertPlanTablesReady('update_plan', 'Tabel plan belum siap.');

        $plan = $this->findPlanOrFail($planId, 'update_plan', 'Plan tidak ditemukan.');
        $menuIds = $this->resolveValidSystemMenuIds($validated['menu_ids'] ?? []);

        DB::transaction(function () use ($plan, $validated, $menuIds): void {
            $plan->forceFill($this->normalizePlanPayload($validated))->save();

            $this->upsertPlanPrice(
                $plan,
                (int) $validated['duration_months'],
                (float) $validated['price'],
                (int) ($validated['discount_pct'] ?? 0),
                (bool) ($validated['is_active'] ?? true),
            );

            $plan->menus()->sync($menuIds);
        });
    }

    public function updatePlanStatus(int $planId, bool $isActive): void
    {
        $this->assertPlanTablesReady('status_plan', 'Tabel plan belum siap.');

        $plan = $this->findPlanOrFail($planId, 'status_plan', 'Plan tidak ditemukan.');

        DB::transaction(function () use ($plan, $isActive): void {
            $plan->forceFill(['is_active' => $isActive])->save();

            if (Schema::hasTable('plan_prices')) {
                PlanPrice::query()
                    ->where('plan_id', (int) $plan->id)
                    ->update([
                        'is_active' => $isActive,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    /**
     * @param  array<int, mixed>  $menuIds
     * @return array<int, int>
     */
    private function resolveValidSystemMenuIds(array $menuIds): array
    {
        $normalizedMenuIds = collect($menuIds)
            ->map(fn ($menuId): int => (int) $menuId)
            ->filter(fn (int $menuId): bool => $menuId > 0)
            ->unique()
            ->values()
            ->all();

        if (count($normalizedMenuIds) === 0) {
            return [];
        }

        if (! Schema::hasTable('menus')) {
            throw ValidationException::withMessages([
                'menu_ids' => 'Tabel menu belum siap.',
            ]);
        }

        $validMenuIds = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereIn('id', $normalizedMenuIds)
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->values()
            ->all();

        if (count($validMenuIds) !== count($normalizedMenuIds)) {
            throw ValidationException::withMessages([
                'menu_ids' => 'Ada menu yang tidak valid untuk plan ini.',
            ]);
        }

        return $validMenuIds;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePlanPayload(array $validated): array
    {
        $hasTrial = (bool) ($validated['has_trial'] ?? true);
        $trialDuration = $hasTrial
            ? max(1, (int) ($validated['trial_duration_days'] ?? 14))
            : 0;

        return [
            'name' => trim((string) $validated['name']),
            'slug' => Str::lower(trim((string) $validated['slug'])),
            'max_workshops' => max(1, (int) ($validated['max_workshops'] ?? 1)),
            'max_users_per_ws' => max(1, (int) ($validated['max_users_per_ws'] ?? 5)),
            'has_ai_feature' => (bool) ($validated['has_ai_feature'] ?? false),
            'has_notification' => (bool) ($validated['has_notification'] ?? false),
            'has_loyalty' => (bool) ($validated['has_loyalty'] ?? false),
            'has_trial' => $hasTrial,
            'trial_duration_days' => $trialDuration,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    private function upsertPlanPrice(
        Plan $plan,
        int $durationMonths,
        float $price,
        int $discountPct,
        bool $isActive,
    ): void {
        if (! Schema::hasTable('plan_prices')) {
            throw ValidationException::withMessages([
                'price' => 'Tabel harga plan belum siap.',
            ]);
        }

        $normalizedDuration = max(1, $durationMonths);
        $normalizedPrice = max(0, $price);
        $normalizedDiscount = max(0, min(100, $discountPct));

        PlanPrice::query()->updateOrCreate(
            [
                'plan_id' => (int) $plan->id,
                'duration_months' => $normalizedDuration,
            ],
            [
                'label' => sprintf('%s - %d Bulan', (string) $plan->name, $normalizedDuration),
                'price' => $normalizedPrice,
                'discount_pct' => $normalizedDiscount,
                'is_active' => $isActive,
            ],
        );
    }

    private function assertPlanTablesReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('plans') || ! Schema::hasTable('plan_prices') || ! Schema::hasTable('plan_menu')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function findPlanOrFail(int $planId, string $errorKey, string $message): Plan
    {
        $plan = Plan::query()->find($planId);
        if (! $plan) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }

        return $plan;
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['price', 'name', 'slug', 'is_active', 'created_at'], true)
            ? $sortBy
            : 'price';
    }

    private function resolveSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
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
                ->cursorPaginate($perPage, $columns, 'plan_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'plan_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
