<?php

namespace App\Services\Platform;

use App\Models\Tenant;
use App\Support\Billing\TenantPlanResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlatformDashboardService
{
    private const DASHBOARD_MONTH_COUNT = 12;

    /**
     * @return array<string, mixed>
     */
    public function buildDashboardData(TenantPlanResolver $planResolver): array
    {
        $tenants = $this->tenants($planResolver);
        $monthBuckets = $this->resolveMonthBuckets(self::DASHBOARD_MONTH_COUNT);

        $registrationSeries = $this->resolveMonthlySeriesByDateKey($tenants, 'created_at', $monthBuckets);
        $activeRegistrationSeries = $this->resolveMonthlySeriesByDateKey(
            array_values(array_filter(
                $tenants,
                fn (array $tenant): bool => (bool) ($tenant['is_active'] ?? false),
            )),
            'created_at',
            $monthBuckets,
        );
        $subscriptionSeries = $this->resolveMonthlySeriesByDateKey(
            array_values(array_filter(
                $tenants,
                fn (array $tenant): bool => $this->isNotBlank(data_get($tenant, 'package.started_at')),
            )),
            'package.started_at',
            $monthBuckets,
        );
        $withoutPackageSeries = $this->resolveMonthlySeriesByDateKey(
            array_values(array_filter(
                $tenants,
                fn (array $tenant): bool => ! is_array($tenant['package'] ?? null),
            )),
            'created_at',
            $monthBuckets,
        );

        $totalTenants = count($tenants);
        $activeTenants = count(array_filter(
            $tenants,
            fn (array $tenant): bool => (bool) ($tenant['is_active'] ?? false),
        ));
        $subscribedTenants = count(array_filter(
            $tenants,
            fn (array $tenant): bool => is_array($tenant['package'] ?? null),
        ));
        $withoutPackageTenants = max($totalTenants - $subscribedTenants, 0);

        [$tenantTrendLabel, $tenantTrendDirection] = $this->resolveSeriesTrend($registrationSeries);
        [$activeTrendLabel, $activeTrendDirection] = $this->resolveSeriesTrend($activeRegistrationSeries);
        [$subscriptionTrendLabel, $subscriptionTrendDirection] = $this->resolveSeriesTrend($subscriptionSeries);
        [$withoutPackageTrendLabel, $withoutPackageTrendDirection] = $this->resolveSeriesTrend($withoutPackageSeries);

        return [
            'tenants' => $tenants,
            'dashboardSubtitle' => 'Ringkasan performa tenant dan langganan',
            'stats' => [
                [
                    'title' => 'Total Tenant',
                    'value' => $this->formatNumber($totalTenants),
                    'hint' => "{$activeTenants} tenant aktif saat ini",
                    'trend' => $tenantTrendLabel,
                    'trendDirection' => $tenantTrendDirection,
                    'color' => 'indigo',
                    'icon' => 'users',
                    'bars' => $this->normalizeBars($registrationSeries),
                ],
                [
                    'title' => 'Tenant Aktif',
                    'value' => $this->formatNumber($activeTenants),
                    'hint' => 'Status aktif dari seluruh tenant',
                    'trend' => $activeTrendLabel,
                    'trendDirection' => $activeTrendDirection,
                    'color' => 'emerald',
                    'icon' => 'dashboard',
                    'bars' => $this->normalizeBars($activeRegistrationSeries),
                ],
                [
                    'title' => 'Tenant Berlangganan',
                    'value' => $this->formatNumber($subscribedTenants),
                    'hint' => 'Tenant dengan paket trial/active',
                    'trend' => $subscriptionTrendLabel,
                    'trendDirection' => $subscriptionTrendDirection,
                    'color' => 'amber',
                    'icon' => 'currency',
                    'bars' => $this->normalizeBars($subscriptionSeries),
                ],
                [
                    'title' => 'Tenant Belum Paket',
                    'value' => $this->formatNumber($withoutPackageTenants),
                    'hint' => 'Perlu follow-up aktivasi paket',
                    'trend' => $withoutPackageTrendLabel,
                    'trendDirection' => $withoutPackageTrendDirection,
                    'color' => 'rose',
                    'icon' => 'conversion',
                    'bars' => $this->normalizeBars($withoutPackageSeries),
                ],
            ],
            'chart' => [
                'title' => 'Pertumbuhan Tenant',
                'subtitle' => 'Tenant baru 12 bulan terakhir',
                'months' => array_map(
                    fn (array $bucket): string => (string) $bucket['label'],
                    $monthBuckets,
                ),
                'values' => $registrationSeries,
                'filters' => ['12 Bulan'],
                'activeFilter' => '12 Bulan',
                'types' => ['Area'],
                'activeType' => 'Area',
            ],
            'categories' => $this->buildPlanDistributionPayload($tenants),
            'table' => $this->buildLatestTenantTablePayload($tenants),
            'activities' => $this->buildRecentActivityPayload($tenants),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tenants(TenantPlanResolver $planResolver): array
    {
        if (! Schema::hasTable('tenants')) {
            return [];
        }

        $tenantQuery = Tenant::query();
        if (Schema::hasTable('users')) {
            $tenantQuery->withCount([
                'users as users_count' => fn ($query) => $query,
            ]);
        }

        return $tenantQuery
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active', 'created_at', 'updated_at'])
            ->map(function (Tenant $tenant) use ($planResolver): array {
                $package = $planResolver->forTenantId((string) $tenant->id);

                return [
                    'id' => (string) $tenant->id,
                    'name' => (string) $tenant->name,
                    'code' => (string) $tenant->code,
                    'is_active' => (bool) $tenant->is_active,
                    'users_count' => (int) ($tenant->users_count ?? 0),
                    'created_at' => $this->resolveDateTimeString($tenant->created_at),
                    'updated_at' => $this->resolveDateTimeString($tenant->updated_at),
                    'package' => $this->normalizePackagePayload($package),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $package
     * @return array<string, mixed>|null
     */
    private function normalizePackagePayload(?array $package): ?array
    {
        if (! is_array($package)) {
            return null;
        }

        return [
            'id' => (string) ($package['id'] ?? ''),
            'status' => (string) ($package['status'] ?? ''),
            'started_at' => $this->resolveDateTimeString($package['started_at'] ?? null),
            'expired_at' => $this->resolveDateTimeString($package['expired_at'] ?? null),
            'trial_ends_at' => $this->resolveDateTimeString($package['trial_ends_at'] ?? null),
            'plan' => [
                'id' => (int) data_get($package, 'plan.id', 0),
                'name' => (string) data_get($package, 'plan.name', ''),
                'slug' => (string) data_get($package, 'plan.slug', ''),
            ],
            'price' => [
                'id' => (int) data_get($package, 'price.id', 0),
                'label' => (string) data_get($package, 'price.label', ''),
                'duration_months' => (int) data_get($package, 'price.duration_months', 0),
                'amount' => (float) data_get($package, 'price.amount', 0),
                'discount_pct' => (int) data_get($package, 'price.discount_pct', 0),
            ],
        ];
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
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array{key: string, label: string, start: string, end: string}>  $monthBuckets
     * @return array<int, int>
     */
    private function resolveMonthlySeriesByDateKey(array $rows, string $dateKey, array $monthBuckets): array
    {
        if (count($monthBuckets) < 1) {
            return [];
        }

        $seriesByMonth = $this->initializeSeriesByMonthKey($monthBuckets);

        foreach ($rows as $row) {
            $monthKey = $this->resolveMonthKey(data_get($row, $dateKey));
            if ($monthKey === null || ! array_key_exists($monthKey, $seriesByMonth)) {
                continue;
            }

            $seriesByMonth[$monthKey]++;
        }

        return $this->seriesByBuckets($monthBuckets, $seriesByMonth);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSeriesTrend(array $series): array
    {
        $seriesCount = count($series);
        if ($seriesCount < 2) {
            return ['0%', 'up'];
        }

        return $this->resolveTrendData(
            (int) ($series[$seriesCount - 1] ?? 0),
            (int) ($series[$seriesCount - 2] ?? 0),
        );
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
     * @param  array<int, array<string, mixed>>  $tenants
     * @return array<int, array{label: string, percent: int, color: string, dotClass: string}>
     */
    private function buildPlanDistributionPayload(array $tenants): array
    {
        $countByPlan = [];
        foreach ($tenants as $tenant) {
            $slug = trim((string) data_get($tenant, 'package.plan.slug', ''));
            if ($slug === '') {
                $slug = 'none';
            }

            $countByPlan[$slug] = (int) ($countByPlan[$slug] ?? 0) + 1;
        }

        $total = array_sum($countByPlan);
        if ($total < 1) {
            return [[
                'label' => 'Belum Ada Tenant',
                'percent' => 100,
                'color' => 'rgb(148 163 184)',
                'dotClass' => 'bg-slate-400',
            ]];
        }

        $planMeta = [
            'starter' => ['label' => 'Starter', 'color' => 'rgb(99 102 241)', 'dotClass' => 'bg-indigo-500'],
            'growth' => ['label' => 'Growth', 'color' => 'rgb(16 185 129)', 'dotClass' => 'bg-emerald-500'],
            'pro' => ['label' => 'Pro', 'color' => 'rgb(245 158 11)', 'dotClass' => 'bg-amber-500'],
            'none' => ['label' => 'Belum Paket', 'color' => 'rgb(148 163 184)', 'dotClass' => 'bg-slate-400'],
        ];

        $rows = [];
        $allocated = 0;
        $fractions = [];
        $index = 0;
        foreach ($countByPlan as $slug => $count) {
            $percentRaw = ((int) $count / max($total, 1)) * 100;
            $basePercent = (int) floor($percentRaw);
            $rows[$index] = [
                'label' => (string) ($planMeta[$slug]['label'] ?? Str::headline((string) $slug)),
                'percent' => $basePercent,
                'color' => (string) ($planMeta[$slug]['color'] ?? 'rgb(6 182 212)'),
                'dotClass' => (string) ($planMeta[$slug]['dotClass'] ?? 'bg-cyan-500'),
            ];
            $fractions[$index] = $percentRaw - $basePercent;
            $allocated += $basePercent;
            $index++;
        }

        $remaining = max(0, 100 - $allocated);
        if ($remaining > 0 && count($rows) > 0) {
            arsort($fractions);
            $keys = array_keys($fractions);
            for ($i = 0; $i < $remaining; $i++) {
                $targetIndex = $keys[$i % count($keys)];
                $rows[$targetIndex]['percent']++;
            }
        }

        return array_values($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tenants
     * @return array<string, mixed>
     */
    private function buildLatestTenantTablePayload(array $tenants): array
    {
        $columns = [
            ['key' => 'id', 'label' => 'ID Tenant'],
            ['key' => 'tenant', 'label' => 'Tenant'],
            ['key' => 'plan', 'label' => 'Paket'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'joined_at', 'label' => 'Bergabung'],
        ];

        $sortedTenants = $tenants;
        usort($sortedTenants, function (array $left, array $right): int {
            $leftTimestamp = $this->resolveTimestamp(data_get($left, 'created_at'));
            $rightTimestamp = $this->resolveTimestamp(data_get($right, 'created_at'));

            return $rightTimestamp <=> $leftTimestamp;
        });

        $rows = array_map(
            function (array $tenant): array {
                $planName = trim((string) data_get($tenant, 'package.plan.name', ''));
                $planLabel = trim((string) data_get($tenant, 'package.price.label', ''));
                $status = trim((string) data_get($tenant, 'package.status', ''));

                if ($status === '') {
                    $status = (bool) ($tenant['is_active'] ?? false) ? 'aktif' : 'nonaktif';
                } else {
                    $status = $this->resolvePackageStatusLabel($status);
                }

                return [
                    'id' => (string) ($tenant['code'] ?: $tenant['id']),
                    'tenant' => (string) ($tenant['name'] ?? '-'),
                    'plan' => $planName !== ''
                        ? ($planLabel !== '' ? "{$planName} ({$planLabel})" : $planName)
                        : 'Belum Paket',
                    'status' => $status,
                    'joined_at' => $this->formatDateShort($tenant['created_at'] ?? null),
                ];
            },
            array_slice($sortedTenants, 0, 6),
        );

        return [
            'title' => 'Tenant Terbaru',
            'subtitle' => count($rows).' data terbaru',
            'actionLabel' => 'Kelola Tenant',
            'columns' => $columns,
            'rows' => array_values($rows),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tenants
     * @return array<string, mixed>
     */
    private function buildRecentActivityPayload(array $tenants): array
    {
        $entries = [];

        foreach ($tenants as $tenant) {
            $createdAt = $tenant['created_at'] ?? null;
            $createdTimestamp = $this->resolveTimestamp($createdAt);

            if ($createdTimestamp > 0) {
                $entries[] = [
                    'timestamp' => $createdTimestamp,
                    'title' => 'Tenant '.(string) ($tenant['name'] ?? '-') .' terdaftar',
                    'description' => 'Kode '.(string) ($tenant['code'] ?? '-') .' - '
                        .((bool) ($tenant['is_active'] ?? false) ? 'aktif' : 'nonaktif'),
                    'time' => $this->formatRelativeTime($createdAt),
                    'dotClass' => (bool) ($tenant['is_active'] ?? false) ? 'bg-emerald-500' : 'bg-rose-500',
                ];
            }

            $packageStartedAt = data_get($tenant, 'package.started_at');
            $packageTimestamp = $this->resolveTimestamp($packageStartedAt);
            if ($packageTimestamp > 0) {
                $status = strtolower(trim((string) data_get($tenant, 'package.status', '')));
                $statusLabel = $this->resolvePackageStatusLabel($status);

                $entries[] = [
                    'timestamp' => $packageTimestamp,
                    'title' => 'Paket '.(string) data_get($tenant, 'package.plan.name', 'tenant').' diaktifkan',
                    'description' => (string) ($tenant['name'] ?? '-').' - '
                        .(string) data_get($tenant, 'package.price.label', '-')
                        .' ('.($statusLabel !== '' ? $statusLabel : 'aktif').')',
                    'time' => $this->formatRelativeTime($packageStartedAt),
                    'dotClass' => $this->resolvePackageDotClass($status),
                ];
            }
        }

        usort($entries, fn (array $left, array $right): int => ((int) $right['timestamp']) <=> ((int) $left['timestamp']));

        $items = array_values(array_map(
            fn (array $entry): array => [
                'title' => (string) ($entry['title'] ?? '-'),
                'description' => (string) ($entry['description'] ?? '-'),
                'time' => (string) ($entry['time'] ?? '-'),
                'dotClass' => (string) ($entry['dotClass'] ?? 'bg-slate-400'),
            ],
            array_slice($entries, 0, 5),
        ));

        if (count($items) < 1) {
            $items[] = [
                'title' => 'Belum ada aktivitas terbaru',
                'description' => 'Data aktivitas tenant akan tampil setelah ada perubahan.',
                'time' => 'baru saja',
                'dotClass' => 'bg-slate-400',
            ];
        }

        return [
            'title' => 'Aktivitas Terbaru',
            'subtitle' => 'Update tenant dan paket terbaru',
            'items' => $items,
        ];
    }

    private function resolvePackageDotClass(string $status): string
    {
        return match ($status) {
            'active' => 'bg-emerald-500',
            'trial' => 'bg-amber-500',
            'cancelled', 'expired', 'suspended' => 'bg-rose-500',
            default => 'bg-slate-400',
        };
    }

    private function resolvePackageStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'active' => 'aktif',
            'trial' => 'trial',
            'expired', 'cancelled', 'suspended' => 'nonaktif',
            default => strtolower(trim($status)),
        };
    }

    private function formatNumber(int $value): string
    {
        return number_format(max($value, 0), 0, ',', '.');
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

    private function resolveMonthKey(mixed $dateValue): ?string
    {
        if (! $this->isNotBlank($dateValue)) {
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

    private function formatDateShort(mixed $dateValue): string
    {
        if (! $this->isNotBlank($dateValue)) {
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
        if (! $this->isNotBlank($dateValue)) {
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

    private function resolveTimestamp(mixed $dateValue): int
    {
        if (! $this->isNotBlank($dateValue)) {
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

    private function resolveDateTimeString(mixed $dateValue): ?string
    {
        if (! $this->isNotBlank($dateValue)) {
            return null;
        }

        try {
            if ($dateValue instanceof \DateTimeInterface) {
                return CarbonImmutable::instance($dateValue)->toIso8601String();
            }

            return CarbonImmutable::parse((string) $dateValue)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isNotBlank(mixed $value): bool
    {
        return ! ($value === null || (is_string($value) && trim($value) === ''));
    }
}
