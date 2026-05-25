<?php

namespace App\Services\Owner;

use App\Models\Booking;
use App\Models\Expense;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderSparePart;
use App\Models\Workshop;
use App\Support\Billing\TenantPlanResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OwnerDashboardService
{
    private const DASHBOARD_MONTH_COUNT = 12;

    public function __construct(
        private readonly OwnerMenuService $ownerMenuService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDashboardData(
        string $tenantId,
        string $activeWorkshopId,
        TenantPlanResolver $planResolver,
        ?Authenticatable $user,
        string $currentUri,
    ): array {
        $package = $planResolver->forTenantId($tenantId);
        $planId = data_get($package, 'plan.id');

        $menuTree = $this->ownerMenuService->buildOwnerMenuTree(
            $tenantId,
            $planId,
            hasPlanMenuTable: Schema::hasTable('plan_menu'),
        );

        $scopeLabel = $this->resolveScopeLabel($tenantId, $activeWorkshopId);
        $monthBuckets = $this->resolveMonthBuckets(self::DASHBOARD_MONTH_COUNT);
        $access = $this->resolveDashboardAccess($user);
        $canViewServiceOrders = (bool) ($access['can_view_service_orders'] ?? false);
        $canViewFinance = (bool) ($access['can_view_finance'] ?? false);
        $canViewExpenses = (bool) ($access['can_view_expenses'] ?? false);
        $canViewBookings = (bool) ($access['can_view_bookings'] ?? false);
        $showCategories = $canViewFinance && $canViewServiceOrders;
        $showTable = $canViewServiceOrders;
        $showActivities = $canViewServiceOrders || $canViewBookings || $canViewExpenses;

        $zeroSeries = array_fill(0, count($monthBuckets), 0);

        $monthlyRevenue = $canViewFinance
            ? $this->resolveMonthlyRevenueSeries($tenantId, $activeWorkshopId, $monthBuckets)
            : $zeroSeries;
        $monthlyDoneOrders = $canViewServiceOrders
            ? $this->resolveMonthlyDoneOrderSeries($tenantId, $activeWorkshopId, $monthBuckets)
            : $zeroSeries;
        $monthlyBookingActive = $canViewBookings
            ? $this->resolveMonthlyBookingActiveSeries($tenantId, $activeWorkshopId, $monthBuckets)
            : $zeroSeries;
        $monthlyExpenses = $canViewExpenses
            ? $this->resolveMonthlyExpenseSeries($tenantId, $activeWorkshopId, $monthBuckets)
            : $zeroSeries;

        $now = CarbonImmutable::now();
        $currentStartDate = $now->startOfMonth()->toDateString();
        $currentEndDate = $now->endOfMonth()->toDateString();
        $previousStartDate = $now->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousEndDate = $now->subMonthNoOverflow()->endOfMonth()->toDateString();

        $currentRevenue = $canViewFinance
            ? $this->resolveRevenueTotalForRange(
                $tenantId,
                $activeWorkshopId,
                $currentStartDate,
                $currentEndDate,
            )
            : 0;
        $previousRevenue = $canViewFinance
            ? $this->resolveRevenueTotalForRange(
                $tenantId,
                $activeWorkshopId,
                $previousStartDate,
                $previousEndDate,
            )
            : 0;
        $currentDoneOrders = $canViewServiceOrders
            ? $this->countDoneOrdersForRange(
                $tenantId,
                $activeWorkshopId,
                $currentStartDate,
                $currentEndDate,
            )
            : 0;
        $previousDoneOrders = $canViewServiceOrders
            ? $this->countDoneOrdersForRange(
                $tenantId,
                $activeWorkshopId,
                $previousStartDate,
                $previousEndDate,
            )
            : 0;
        $currentBookingActive = $canViewBookings
            ? $this->countActiveBookings($tenantId, $activeWorkshopId)
            : 0;
        $previousBookingActive = $canViewBookings
            ? $this->countActiveBookings($tenantId, $activeWorkshopId, $now->subDay())
            : 0;
        $currentExpenses = $canViewExpenses
            ? $this->sumExpensesForRange(
                $tenantId,
                $activeWorkshopId,
                $currentStartDate,
                $currentEndDate,
            )
            : 0;
        $previousExpenses = $canViewExpenses
            ? $this->sumExpensesForRange(
                $tenantId,
                $activeWorkshopId,
                $previousStartDate,
                $previousEndDate,
            )
            : 0;

        $stats = [];

        if ($canViewFinance) {
            [$revenueTrendLabel, $revenueTrendDirection] = $this->resolveTrendData($currentRevenue, $previousRevenue);
            $stats[] = [
                'title' => 'Pendapatan Bulan Ini',
                'value' => $this->formatCurrency($currentRevenue),
                'hint' => "{$currentDoneOrders} servis selesai - {$scopeLabel}",
                'trend' => $revenueTrendLabel,
                'trendDirection' => $revenueTrendDirection,
                'color' => 'emerald',
                'icon' => 'currency',
                'bars' => $this->normalizeBars($monthlyRevenue),
            ];
        }

        if ($canViewServiceOrders) {
            [$doneTrendLabel, $doneTrendDirection] = $this->resolveTrendData($currentDoneOrders, $previousDoneOrders);
            $stats[] = [
                'title' => 'Servis Selesai Bulan Ini',
                'value' => $this->formatNumber($currentDoneOrders),
                'hint' => "Status done - {$scopeLabel}",
                'trend' => $doneTrendLabel,
                'trendDirection' => $doneTrendDirection,
                'color' => 'indigo',
                'icon' => 'orders',
                'bars' => $this->normalizeBars($monthlyDoneOrders),
            ];
        }

        if ($canViewBookings) {
            [$bookingTrendLabel, $bookingTrendDirection] = $this->resolveTrendData($currentBookingActive, $previousBookingActive);
            $stats[] = [
                'title' => 'Booking Aktif',
                'value' => $this->formatNumber($currentBookingActive),
                'hint' => "Antrian + dikerjakan - {$scopeLabel}",
                'trend' => $bookingTrendLabel,
                'trendDirection' => $bookingTrendDirection,
                'color' => 'amber',
                'icon' => 'users',
                'bars' => $this->normalizeBars($monthlyBookingActive),
            ];
        }

        if ($canViewExpenses) {
            [$expenseTrendLabel, $expenseTrendDirection] = $this->resolveTrendData($currentExpenses, $previousExpenses);
            $stats[] = [
                'title' => 'Pengeluaran Bulan Ini',
                'value' => $this->formatCurrency($currentExpenses),
                'hint' => "Beban operasional - {$scopeLabel}",
                'trend' => $expenseTrendLabel,
                'trendDirection' => $expenseTrendDirection,
                'color' => 'rose',
                'icon' => 'currency',
                'bars' => $this->normalizeBars($monthlyExpenses),
            ];
        }

        if (count($stats) < 1) {
            $stats[] = $this->buildLimitedAccessStat($scopeLabel);
        }

        $showChart = false;
        $chart = [];
        if ($canViewFinance) {
            $showChart = true;
            $chart = $this->buildChartPayload(
                $monthBuckets,
                $monthlyRevenue,
                'Grafik Pendapatan',
                "12 bulan terakhir - {$scopeLabel}",
            );
        } elseif ($canViewServiceOrders) {
            $showChart = true;
            $chart = $this->buildChartPayload(
                $monthBuckets,
                $monthlyDoneOrders,
                'Grafik Servis Selesai',
                "12 bulan terakhir - {$scopeLabel}",
            );
        } elseif ($canViewBookings) {
            $showChart = true;
            $chart = $this->buildChartPayload(
                $monthBuckets,
                $monthlyBookingActive,
                'Grafik Booking Aktif',
                "12 bulan terakhir - {$scopeLabel}",
            );
        }

        return [
            'tenantId' => $tenantId,
            'activeWorkshopId' => $activeWorkshopId,
            'package' => $package,
            'dashboardSubtitle' => "Ringkasan performa bengkel - {$scopeLabel}",
            'menuItems' => $this->ownerMenuService->buildSidebarMenuItems(
                $menuTree,
                $tenantId,
                $user,
                $currentUri,
            ),
            'stats' => $stats,
            'chart' => $chart,
            'categories' => $showCategories
                ? $this->buildRevenueCategoryPayload(
                    $tenantId,
                    $activeWorkshopId,
                    $currentStartDate,
                    $currentEndDate,
                )
                : [],
            'table' => $showTable
                ? $this->buildRecentOrderTablePayload($tenantId, $activeWorkshopId)
                : [],
            'activities' => $this->buildRecentActivityPayload(
                $tenantId,
                $activeWorkshopId,
                $scopeLabel,
                includeOrders: $canViewServiceOrders,
                includeBookings: $canViewBookings,
                includeExpenses: $canViewExpenses,
            ),
            'visibility' => [
                'showStats' => count($stats) > 0,
                'showChart' => $showChart,
                'showCategories' => $showCategories,
                'showTable' => $showTable,
                'showActivities' => $showActivities,
            ],
        ];
    }

    private function resolveScopeLabel(string $tenantId, string $activeWorkshopId): string
    {
        if (! $this->shouldApplyWorkshopScope($activeWorkshopId)) {
            return 'Semua Cabang';
        }

        if (! Schema::hasTable('workshops')) {
            return 'Cabang Aktif';
        }

        $workshop = Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $activeWorkshopId)
            ->first(['name', 'code']);

        if (! $workshop) {
            return 'Cabang Aktif';
        }

        $name = trim((string) ($workshop->name ?? ''));
        $code = trim((string) ($workshop->code ?? ''));

        return $code !== '' ? "{$name} - {$code}" : ($name !== '' ? $name : 'Cabang Aktif');
    }

    /**
     * @return array<int, array{key: string, label: string, start: string, end: string}>
     */
    private function resolveMonthBuckets(int $count = self::DASHBOARD_MONTH_COUNT): array
    {
        $bucketCount = max($count, 1);
        $now = CarbonImmutable::now();
        $buckets = [];

        for ($offset = $bucketCount - 1; $offset >= 0; $offset--) {
            $monthStart = $now->subMonthsNoOverflow($offset)->startOfMonth();

            $buckets[] = [
                'key' => $monthStart->format('Y-m'),
                'label' => $this->resolveMonthLabel((int) $monthStart->format('n')),
                'start' => $monthStart->toDateString(),
                'end' => $monthStart->endOfMonth()->toDateString(),
            ];
        }

        return $buckets;
    }

    /**
     * @param  array<int, array{key: string, label: string, start: string, end: string}>  $monthBuckets
     * @return array<int, int>
     */
    private function resolveMonthlyRevenueSeries(string $tenantId, string $activeWorkshopId, array $monthBuckets): array
    {
        if (! Schema::hasTable('service_orders') || count($monthBuckets) < 1) {
            return array_fill(0, count($monthBuckets), 0);
        }

        $windowStart = $monthBuckets[0]['start'];
        $windowEnd = $monthBuckets[count($monthBuckets) - 1]['end'];
        $seriesByMonth = $this->initializeSeriesByMonthKey($monthBuckets);

        $orders = $this->serviceOrderDoneBaseQuery($tenantId, $activeWorkshopId)
            ->whereDate('service_date', '>=', $windowStart)
            ->whereDate('service_date', '<=', $windowEnd)
            ->get(['service_date', 'total_amount']);

        foreach ($orders as $order) {
            $monthKey = $this->resolveMonthKey($order->service_date);
            if ($monthKey === null || ! array_key_exists($monthKey, $seriesByMonth)) {
                continue;
            }

            $seriesByMonth[$monthKey] += max((int) ($order->total_amount ?? 0), 0);
        }

        return $this->seriesByBuckets($monthBuckets, $seriesByMonth);
    }

    /**
     * @param  array<int, array{key: string, label: string, start: string, end: string}>  $monthBuckets
     * @return array<int, int>
     */
    private function resolveMonthlyDoneOrderSeries(string $tenantId, string $activeWorkshopId, array $monthBuckets): array
    {
        if (! Schema::hasTable('service_orders') || count($monthBuckets) < 1) {
            return array_fill(0, count($monthBuckets), 0);
        }

        $windowStart = $monthBuckets[0]['start'];
        $windowEnd = $monthBuckets[count($monthBuckets) - 1]['end'];
        $seriesByMonth = $this->initializeSeriesByMonthKey($monthBuckets);

        $orders = $this->serviceOrderDoneBaseQuery($tenantId, $activeWorkshopId)
            ->whereDate('service_date', '>=', $windowStart)
            ->whereDate('service_date', '<=', $windowEnd)
            ->get(['service_date']);

        foreach ($orders as $order) {
            $monthKey = $this->resolveMonthKey($order->service_date);
            if ($monthKey === null || ! array_key_exists($monthKey, $seriesByMonth)) {
                continue;
            }

            $seriesByMonth[$monthKey]++;
        }

        return $this->seriesByBuckets($monthBuckets, $seriesByMonth);
    }

    /**
     * @param  array<int, array{key: string, label: string, start: string, end: string}>  $monthBuckets
     * @return array<int, int>
     */
    private function resolveMonthlyBookingActiveSeries(string $tenantId, string $activeWorkshopId, array $monthBuckets): array
    {
        if (! Schema::hasTable('bookings') || count($monthBuckets) < 1) {
            return array_fill(0, count($monthBuckets), 0);
        }

        $windowStart = $monthBuckets[0]['start'];
        $windowEnd = $monthBuckets[count($monthBuckets) - 1]['end'];
        $seriesByMonth = $this->initializeSeriesByMonthKey($monthBuckets);

        $bookings = $this->bookingBaseQuery($tenantId, $activeWorkshopId)
            ->whereIn('status', ['queued', 'in_service'])
            ->whereDate('booking_date', '>=', $windowStart)
            ->whereDate('booking_date', '<=', $windowEnd)
            ->get(['booking_date']);

        foreach ($bookings as $booking) {
            $monthKey = $this->resolveMonthKey($booking->booking_date);
            if ($monthKey === null || ! array_key_exists($monthKey, $seriesByMonth)) {
                continue;
            }

            $seriesByMonth[$monthKey]++;
        }

        return $this->seriesByBuckets($monthBuckets, $seriesByMonth);
    }

    /**
     * @param  array<int, array{key: string, label: string, start: string, end: string}>  $monthBuckets
     * @return array<int, int>
     */
    private function resolveMonthlyExpenseSeries(string $tenantId, string $activeWorkshopId, array $monthBuckets): array
    {
        if (! Schema::hasTable('expenses') || count($monthBuckets) < 1) {
            return array_fill(0, count($monthBuckets), 0);
        }

        $windowStart = $monthBuckets[0]['start'];
        $windowEnd = $monthBuckets[count($monthBuckets) - 1]['end'];
        $seriesByMonth = $this->initializeSeriesByMonthKey($monthBuckets);

        $expenses = $this->expenseBaseQuery($tenantId, $activeWorkshopId)
            ->whereDate('expense_date', '>=', $windowStart)
            ->whereDate('expense_date', '<=', $windowEnd)
            ->get(['expense_date', 'amount']);

        foreach ($expenses as $expense) {
            $monthKey = $this->resolveMonthKey($expense->expense_date);
            if ($monthKey === null || ! array_key_exists($monthKey, $seriesByMonth)) {
                continue;
            }

            $seriesByMonth[$monthKey] += max((int) ($expense->amount ?? 0), 0);
        }

        return $this->seriesByBuckets($monthBuckets, $seriesByMonth);
    }

    private function resolveRevenueTotalForRange(
        string $tenantId,
        string $activeWorkshopId,
        string $startDate,
        string $endDate,
    ): int {
        if (! Schema::hasTable('service_orders')) {
            return 0;
        }

        return (int) ($this->serviceOrderDoneBaseQuery($tenantId, $activeWorkshopId)
            ->whereDate('service_date', '>=', $startDate)
            ->whereDate('service_date', '<=', $endDate)
            ->sum('total_amount') ?? 0);
    }

    private function countDoneOrdersForRange(
        string $tenantId,
        string $activeWorkshopId,
        string $startDate,
        string $endDate,
    ): int {
        if (! Schema::hasTable('service_orders')) {
            return 0;
        }

        return (int) $this->serviceOrderDoneBaseQuery($tenantId, $activeWorkshopId)
            ->whereDate('service_date', '>=', $startDate)
            ->whereDate('service_date', '<=', $endDate)
            ->count();
    }

    private function countActiveBookings(
        string $tenantId,
        string $activeWorkshopId,
        ?CarbonImmutable $referenceAt = null,
    ): int {
        if (! Schema::hasTable('bookings')) {
            return 0;
        }

        $query = $this->bookingBaseQuery($tenantId, $activeWorkshopId)
            ->whereIn('status', ['queued', 'in_service']);

        if ($referenceAt instanceof CarbonImmutable) {
            $query->where('created_at', '<=', $referenceAt->toDateTimeString());
        }

        return (int) $query->count();
    }

    private function sumExpensesForRange(
        string $tenantId,
        string $activeWorkshopId,
        string $startDate,
        string $endDate,
    ): int {
        if (! Schema::hasTable('expenses')) {
            return 0;
        }

        return (int) ($this->expenseBaseQuery($tenantId, $activeWorkshopId)
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->sum('amount') ?? 0);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveTrendData(int $currentValue, int $previousValue): array
    {
        if ($currentValue <= 0 && $previousValue <= 0) {
            return ['0%', 'up'];
        }

        if ($previousValue <= 0) {
            return ['+100%', 'up'];
        }

        $change = (($currentValue - $previousValue) / max($previousValue, 1)) * 100;
        $rounded = round($change, 1);
        $direction = $rounded >= 0 ? 'up' : 'down';

        $formattedNumber = number_format($rounded, 1, '.', '');
        if (str_ends_with($formattedNumber, '.0')) {
            $formattedNumber = substr($formattedNumber, 0, -2);
        }

        if ($rounded > 0 && ! str_starts_with($formattedNumber, '+')) {
            $formattedNumber = '+'.$formattedNumber;
        }

        return [$formattedNumber.'%', $direction];
    }

    /**
     * @param  array<int, int>  $series
     * @return array<int, int>
     */
    private function normalizeBars(array $series): array
    {
        if (count($series) < 1) {
            return [20];
        }

        $normalized = [];
        $maxValue = 0;
        foreach ($series as $value) {
            $safeValue = max((int) $value, 0);
            $normalized[] = $safeValue;
            if ($safeValue > $maxValue) {
                $maxValue = $safeValue;
            }
        }

        if ($maxValue < 1) {
            return array_fill(0, count($normalized), 20);
        }

        $bars = [];
        foreach ($normalized as $value) {
            $scaled = (int) round(($value / $maxValue) * 100);
            $bars[] = max(15, min(100, $scaled));
        }

        return $bars;
    }

    /**
     * @param  array<int, array{key: string, label: string, start: string, end: string}>  $monthBuckets
     * @param  array<int, int>  $values
     * @return array<string, mixed>
     */
    private function buildChartPayload(
        array $monthBuckets,
        array $values,
        string $title,
        string $subtitle,
    ): array
    {
        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'months' => array_map(
                fn (array $bucket): string => $bucket['label'],
                $monthBuckets,
            ),
            'values' => $values,
            'filters' => ['12 Bulan'],
            'activeFilter' => '12 Bulan',
            'types' => ['Area'],
            'activeType' => 'Area',
        ];
    }

    /**
     * @return array<int, array{label: string, percent: int, color: string, dotClass: string}>
     */
    private function buildRevenueCategoryPayload(
        string $tenantId,
        string $activeWorkshopId,
        string $startDate,
        string $endDate,
    ): array
    {
        $totalRevenue = $this->resolveRevenueTotalForRange(
            $tenantId,
            $activeWorkshopId,
            $startDate,
            $endDate,
        );

        if ($totalRevenue < 1) {
            return [[
                'label' => 'Belum Ada Pendapatan',
                'percent' => 100,
                'color' => 'rgb(148 163 184)',
                'dotClass' => 'bg-slate-400',
            ]];
        }

        $serviceRevenue = 0;
        if (Schema::hasTable('service_orders') && Schema::hasColumn('service_orders', 'service_fee')) {
            $serviceRevenue = (int) ($this->serviceOrderDoneBaseQuery($tenantId, $activeWorkshopId)
                ->whereDate('service_date', '>=', $startDate)
                ->whereDate('service_date', '<=', $endDate)
                ->sum('service_fee') ?? 0);
        }

        $sparePartRevenue = $this->resolveSparePartRevenueForRange(
            $tenantId,
            $activeWorkshopId,
            $startDate,
            $endDate,
        );

        $otherRevenue = max($totalRevenue - $serviceRevenue - $sparePartRevenue, 0);

        $segments = [
            [
                'label' => 'Jasa Servis',
                'amount' => max($serviceRevenue, 0),
                'color' => 'rgb(16 185 129)',
                'dotClass' => 'bg-emerald-500',
            ],
            [
                'label' => 'Sparepart',
                'amount' => max($sparePartRevenue, 0),
                'color' => 'rgb(99 102 241)',
                'dotClass' => 'bg-indigo-500',
            ],
            [
                'label' => 'Lainnya',
                'amount' => $otherRevenue,
                'color' => 'rgb(245 158 11)',
                'dotClass' => 'bg-amber-500',
            ],
        ];

        $segments = array_values(array_filter(
            $segments,
            fn (array $segment): bool => (int) ($segment['amount'] ?? 0) > 0,
        ));

        if (count($segments) < 1) {
            return [[
                'label' => 'Belum Ada Pendapatan',
                'percent' => 100,
                'color' => 'rgb(148 163 184)',
                'dotClass' => 'bg-slate-400',
            ]];
        }

        $percentRows = [];
        $allocated = 0;
        $fractions = [];
        $distributionTotal = max(
            array_sum(array_map(
                fn (array $segment): int => max((int) ($segment['amount'] ?? 0), 0),
                $segments,
            )),
            1,
        );

        foreach ($segments as $index => $segment) {
            $rawPercent = ((int) $segment['amount'] / $distributionTotal) * 100;
            $basePercent = (int) floor($rawPercent);
            $percentRows[$index] = [
                'label' => (string) $segment['label'],
                'percent' => $basePercent,
                'color' => (string) $segment['color'],
                'dotClass' => (string) $segment['dotClass'],
            ];
            $fractions[$index] = $rawPercent - $basePercent;
            $allocated += $basePercent;
        }

        $remaining = max(0, 100 - $allocated);
        if ($remaining > 0 && count($percentRows) > 0) {
            arsort($fractions);
            $keys = array_keys($fractions);

            for ($i = 0; $i < $remaining; $i++) {
                $targetIndex = $keys[$i % count($keys)];
                $percentRows[$targetIndex]['percent']++;
            }
        }

        return array_values($percentRows);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecentOrderTablePayload(string $tenantId, string $activeWorkshopId): array
    {
        $columns = [
            ['key' => 'id', 'label' => 'ID Pesanan'],
            ['key' => 'customer', 'label' => 'Pelanggan'],
            ['key' => 'service', 'label' => 'Layanan'],
            ['key' => 'amount', 'label' => 'Jumlah'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'date', 'label' => 'Tanggal'],
        ];

        if (! Schema::hasTable('service_orders')) {
            return [
                'title' => 'Pesanan Terbaru',
                'subtitle' => '0 data terbaru',
                'actionLabel' => 'Lihat Semua',
                'columns' => $columns,
                'rows' => [],
            ];
        }

        $rows = $this->serviceOrderBaseQuery($tenantId, $activeWorkshopId)
            ->with(['customer:id,name'])
            ->orderByDesc('service_date')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'code', 'customer_id', 'service_date', 'status', 'complaint', 'total_amount'])
            ->map(function (ServiceOrder $order): array {
                $serviceLabel = trim((string) ($order->complaint ?? ''));
                if ($serviceLabel === '') {
                    $serviceLabel = 'Servis Kendaraan';
                }

                return [
                    'id' => (string) ($order->code ?? $order->id),
                    'customer' => trim((string) ($order->customer?->name ?? 'Pelanggan')),
                    'service' => Str::limit($serviceLabel, 42),
                    'amount' => $this->formatCurrency((int) ($order->total_amount ?? 0)),
                    'status' => $this->resolveDashboardTableStatus((string) ($order->status ?? 'open')),
                    'date' => $this->formatDateShort($order->service_date),
                ];
            })
            ->values()
            ->all();

        return [
            'title' => 'Pesanan Terbaru',
            'subtitle' => count($rows).' data terbaru',
            'actionLabel' => 'Lihat Semua',
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecentActivityPayload(
        string $tenantId,
        string $activeWorkshopId,
        string $scopeLabel,
        bool $includeOrders = true,
        bool $includeBookings = true,
        bool $includeExpenses = true,
    ): array {
        $entries = [];

        if ($includeOrders && Schema::hasTable('service_orders')) {
            $orderActivities = $this->serviceOrderBaseQuery($tenantId, $activeWorkshopId)
                ->with(['customer:id,name'])
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->limit(3)
                ->get(['id', 'code', 'customer_id', 'status', 'complaint', 'updated_at', 'created_at']);

            foreach ($orderActivities as $order) {
                $status = strtolower(trim((string) ($order->status ?? 'open')));
                $entries[] = [
                    'timestamp' => $this->resolveTimestamp($order->updated_at, $order->created_at),
                    'title' => 'Servis '.(string) ($order->code ?? $order->id).' '.$this->resolveOrderActivityVerb($status),
                    'description' => $this->buildOrderActivityDescription($order),
                    'time' => $this->formatRelativeTime($order->updated_at ?? $order->created_at),
                    'dotClass' => $this->resolveOrderDotClass($status),
                ];
            }
        }

        if ($includeBookings && Schema::hasTable('bookings')) {
            $bookingActivities = $this->bookingBaseQuery($tenantId, $activeWorkshopId)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->limit(2)
                ->get(['id', 'code', 'customer_name', 'status', 'updated_at', 'created_at']);

            foreach ($bookingActivities as $booking) {
                $status = strtolower(trim((string) ($booking->status ?? 'queued')));
                $entries[] = [
                    'timestamp' => $this->resolveTimestamp($booking->updated_at, $booking->created_at),
                    'title' => 'Booking '.(string) ($booking->code ?? $booking->id).' '.$this->resolveBookingActivityVerb($status),
                    'description' => trim((string) ($booking->customer_name ?? 'Pelanggan')).' - '.$this->resolveBookingStatusLabel($status),
                    'time' => $this->formatRelativeTime($booking->updated_at ?? $booking->created_at),
                    'dotClass' => $this->resolveBookingDotClass($status),
                ];
            }
        }

        if ($includeExpenses && Schema::hasTable('expenses')) {
            $expenseActivities = $this->expenseBaseQuery($tenantId, $activeWorkshopId)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->limit(2)
                ->get(['id', 'category', 'description', 'amount', 'updated_at', 'created_at']);

            foreach ($expenseActivities as $expense) {
                $entries[] = [
                    'timestamp' => $this->resolveTimestamp($expense->updated_at, $expense->created_at),
                    'title' => 'Pengeluaran '.trim((string) ($expense->category ?? 'Operasional')).' dicatat',
                    'description' => Str::limit(trim((string) ($expense->description ?? '')), 62)
                        .' - '.$this->formatCurrency((int) ($expense->amount ?? 0)),
                    'time' => $this->formatRelativeTime($expense->updated_at ?? $expense->created_at),
                    'dotClass' => 'bg-rose-500',
                ];
            }
        }

        usort($entries, fn (array $left, array $right): int => ((int) $right['timestamp']) <=> ((int) $left['timestamp']));

        $entries = array_slice($entries, 0, 5);
        $items = array_values(array_map(function (array $entry): array {
            return [
                'title' => (string) ($entry['title'] ?? '-'),
                'description' => (string) ($entry['description'] ?? '-'),
                'time' => (string) ($entry['time'] ?? '-'),
                'dotClass' => (string) ($entry['dotClass'] ?? 'bg-slate-400'),
            ];
        }, $entries));

        if (count($items) < 1) {
            $items[] = [
                'title' => 'Belum ada aktivitas terbaru',
                'description' => "Belum ada transaksi untuk {$scopeLabel}.",
                'time' => 'baru saja',
                'dotClass' => 'bg-slate-400',
            ];
        }

        return [
            'title' => 'Aktivitas Terbaru',
            'subtitle' => $scopeLabel,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function resolveDashboardAccess(?Authenticatable $user): array
    {
        return [
            'can_view_service_orders' => $this->userCanAny($user, [
                'service_orders.view',
                'service_orders.manage',
            ]),
            'can_view_finance' => $this->userCanAny($user, [
                'finance.view',
                'finance.manage',
            ]),
            'can_view_expenses' => $this->userCanAny($user, [
                'expenses.view',
                'expenses.manage',
                'finance.view',
                'finance.manage',
            ]),
            'can_view_bookings' => $this->userCanAny($user, [
                'bookings.view',
                'bookings.manage',
            ]),
        ];
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    private function userCanAny(?Authenticatable $user, array $permissionNames): bool
    {
        if (! $user || ! method_exists($user, 'can')) {
            return false;
        }

        foreach ($permissionNames as $permissionName) {
            $normalizedPermissionName = trim($permissionName);
            if ($normalizedPermissionName === '') {
                continue;
            }

            try {
                if ($user->can($normalizedPermissionName)) {
                    return true;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLimitedAccessStat(string $scopeLabel): array
    {
        return [
            'title' => 'Akses Dashboard',
            'value' => 'Terbatas',
            'hint' => "Hak akses saat ini terbatas - {$scopeLabel}",
            'trend' => '',
            'trendDirection' => 'up',
            'color' => 'indigo',
            'icon' => 'dashboard',
            'bars' => array_fill(0, self::DASHBOARD_MONTH_COUNT, 20),
        ];
    }

    private function resolveSparePartRevenueForRange(
        string $tenantId,
        string $activeWorkshopId,
        string $startDate,
        string $endDate,
    ): int {
        if (! Schema::hasTable('service_order_spare_parts') || ! Schema::hasTable('service_orders')) {
            return 0;
        }

        $query = ServiceOrderSparePart::query()
            ->join('service_orders', 'service_orders.id', '=', 'service_order_spare_parts.service_order_id')
            ->where('service_order_spare_parts.tenant_id', $tenantId)
            ->where('service_orders.tenant_id', $tenantId)
            ->where('service_orders.status', 'done')
            ->whereDate('service_orders.service_date', '>=', $startDate)
            ->whereDate('service_orders.service_date', '<=', $endDate);

        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            if (Schema::hasColumn('service_order_spare_parts', 'workshop_id')) {
                $query->where('service_order_spare_parts.workshop_id', $activeWorkshopId);
            } elseif ($this->hasCustomerWorkshopScope()) {
                $query->whereExists(function ($subQuery) use ($activeWorkshopId): void {
                    $subQuery
                        ->selectRaw('1')
                        ->from('customers')
                        ->whereColumn('customers.id', 'service_orders.customer_id')
                        ->where('customers.workshop_id', $activeWorkshopId);
                });
            }
        }

        return (int) ($query->sum('service_order_spare_parts.subtotal') ?? 0);
    }

    private function serviceOrderBaseQuery(string $tenantId, string $activeWorkshopId): Builder
    {
        return ServiceOrder::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->whereHas('customer', function (Builder $customerQuery) use ($activeWorkshopId): void {
                    $customerQuery->where('workshop_id', $activeWorkshopId);
                });
            });
    }

    private function serviceOrderDoneBaseQuery(string $tenantId, string $activeWorkshopId): Builder
    {
        return $this->serviceOrderBaseQuery($tenantId, $activeWorkshopId)
            ->where('status', 'done');
    }

    private function bookingBaseQuery(string $tenantId, string $activeWorkshopId): Builder
    {
        return Booking::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyWorkshopScope($activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            });
    }

    private function expenseBaseQuery(string $tenantId, string $activeWorkshopId): Builder
    {
        return Expense::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyWorkshopScope($activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            });
    }

    /**
     * @param  array<int, array{key: string, label: string, start: string, end: string}>  $monthBuckets
     * @return array<string, int>
     */
    private function initializeSeriesByMonthKey(array $monthBuckets): array
    {
        $series = [];
        foreach ($monthBuckets as $bucket) {
            $series[(string) $bucket['key']] = 0;
        }

        return $series;
    }

    /**
     * @param  array<int, array{key: string, label: string, start: string, end: string}>  $monthBuckets
     * @param  array<string, int>  $seriesByMonth
     * @return array<int, int>
     */
    private function seriesByBuckets(array $monthBuckets, array $seriesByMonth): array
    {
        $series = [];
        foreach ($monthBuckets as $bucket) {
            $series[] = (int) ($seriesByMonth[$bucket['key']] ?? 0);
        }

        return $series;
    }

    private function resolveMonthLabel(int $month): string
    {
        return [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ][$month] ?? '-';
    }

    private function resolveMonthKey(mixed $dateValue): ?string
    {
        if ($dateValue === null || $dateValue === '') {
            return null;
        }

        try {
            if ($dateValue instanceof \DateTimeInterface) {
                return CarbonImmutable::instance($dateValue)->format('Y-m');
            }

            return CarbonImmutable::parse((string) $dateValue)->format('Y-m');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatCurrency(int $value): string
    {
        return 'Rp '.number_format(max($value, 0), 0, ',', '.');
    }

    private function formatNumber(int $value): string
    {
        return number_format(max($value, 0), 0, ',', '.');
    }

    private function formatDateShort(mixed $dateValue): string
    {
        if ($dateValue === null || $dateValue === '') {
            return '-';
        }

        try {
            $date = $dateValue instanceof \DateTimeInterface
                ? CarbonImmutable::instance($dateValue)
                : CarbonImmutable::parse((string) $dateValue);

            return $date->locale('id')->translatedFormat('d M Y');
        } catch (\Throwable) {
            return '-';
        }
    }

    private function formatRelativeTime(mixed $dateValue): string
    {
        if ($dateValue === null || $dateValue === '') {
            return '-';
        }

        try {
            $date = $dateValue instanceof \DateTimeInterface
                ? CarbonImmutable::instance($dateValue)
                : CarbonImmutable::parse((string) $dateValue);

            return $date->locale('id')->diffForHumans();
        } catch (\Throwable) {
            return '-';
        }
    }

    private function resolveTimestamp(mixed $primaryDate, mixed $fallbackDate): int
    {
        $primary = $this->resolveTimestampFromDate($primaryDate);
        if ($primary > 0) {
            return $primary;
        }

        return $this->resolveTimestampFromDate($fallbackDate);
    }

    private function resolveTimestampFromDate(mixed $dateValue): int
    {
        if ($dateValue === null || $dateValue === '') {
            return 0;
        }

        try {
            if ($dateValue instanceof \DateTimeInterface) {
                return (int) $dateValue->getTimestamp();
            }

            return CarbonImmutable::parse((string) $dateValue)->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function resolveDashboardTableStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'done' => 'selesai',
            'in_progress' => 'proses',
            'cancelled' => 'gagal',
            default => 'pending',
        };
    }

    private function resolveOrderActivityVerb(string $status): string
    {
        return match ($status) {
            'done' => 'selesai',
            'in_progress' => 'diproses',
            'cancelled' => 'dibatalkan',
            default => 'dibuka',
        };
    }

    private function resolveBookingActivityVerb(string $status): string
    {
        return match ($status) {
            'completed' => 'selesai',
            'in_service' => 'diproses',
            'cancelled' => 'dibatalkan',
            default => 'diantrikan',
        };
    }

    private function resolveBookingStatusLabel(string $status): string
    {
        return match ($status) {
            'in_service' => 'Sedang Dikerjakan',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => 'Dalam Antrian',
        };
    }

    private function resolveOrderDotClass(string $status): string
    {
        return match ($status) {
            'done' => 'bg-emerald-500',
            'in_progress' => 'bg-amber-500',
            'cancelled' => 'bg-rose-500',
            default => 'bg-blue-500',
        };
    }

    private function resolveBookingDotClass(string $status): string
    {
        return match ($status) {
            'completed' => 'bg-emerald-500',
            'in_service' => 'bg-amber-500',
            'cancelled' => 'bg-rose-500',
            default => 'bg-blue-500',
        };
    }

    private function buildOrderActivityDescription(ServiceOrder $order): string
    {
        $customerName = trim((string) ($order->customer?->name ?? 'Pelanggan'));
        $complaint = trim((string) ($order->complaint ?? ''));

        if ($complaint === '') {
            return $customerName;
        }

        return $customerName.' - '.Str::limit($complaint, 56);
    }

    private function shouldApplyCustomerWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return $this->shouldApplyWorkshopScope($activeWorkshopId)
            && $this->hasCustomerWorkshopScope()
            && $this->hasActiveWorkshops($tenantId);
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

    private function shouldApplyWorkshopScope(string $activeWorkshopId): bool
    {
        return trim($activeWorkshopId) !== ''
            && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }
}
