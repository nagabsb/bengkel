<?php

namespace App\Services\Platform;

use App\Models\PlanPrice;
use App\Models\RoleHasPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkshopSubscription;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PlatformTenantService
{
    /**
     * @return array<string, mixed>
     */
    public function buildPageData(Request $request): array
    {
        $tenantSearch = trim((string) $request->query('tenant_search', ''));
        $tenantSortBy = $this->resolveSortBy((string) $request->query('tenant_sort_by', 'created_at'));
        $tenantSortDir = $this->resolveSortDirection((string) $request->query('tenant_sort_dir', 'desc'));
        $tenantPerPage = $this->resolvePerPage((int) $request->query('tenant_per_page', 10));
        $tenantCursor = trim((string) $request->query('tenant_cursor', ''));
        $tenantRootDomain = $this->resolveTenantRootDomain($request);
        $tenantRootScheme = $this->resolveTenantRootScheme($request);

        $tenantPayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $tenantPerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        if (Schema::hasTable('tenants')) {
            $baseQuery = Tenant::query()
                ->when($tenantSearch !== '', function ($query) use ($tenantSearch): void {
                    $query->where(function ($nestedQuery) use ($tenantSearch): void {
                        $nestedQuery
                            ->where('name', 'like', "%{$tenantSearch}%")
                            ->orWhere('code', 'like', "%{$tenantSearch}%");
                    });
                });

            $tenantTotal = (int) (clone $baseQuery)->count();

            $tenantQuery = (clone $baseQuery)->select('tenants.*');

            $withCount = [];
            if (Schema::hasTable('workshops')) {
                $withCount['workshops as workshops_count'] = fn ($query) => $query;
            }

            if (Schema::hasTable('users')) {
                $withCount['users as users_count'] = fn ($query) => $query;
            }

            if (count($withCount) > 0) {
                $tenantQuery->withCount($withCount);
            }

            if (Schema::hasTable('workshop_subscriptions') && Schema::hasTable('plan_prices') && Schema::hasTable('plans')) {
                $tenantQuery->with([
                    'subscriptions' => fn ($query) => $query
                        ->whereIn('status', ['trial', 'active'])
                        ->orderByDesc('started_at')
                        ->orderByDesc('created_at')
                        ->with([
                            'planPrice' => fn ($planPriceQuery) => $planPriceQuery->with('plan'),
                        ]),
                ]);
            }

            $sortableColumn = [
                'name' => 'tenants.name',
                'code' => 'tenants.code',
                'is_active' => 'tenants.is_active',
                'created_at' => 'tenants.created_at',
            ][$tenantSortBy] ?? 'tenants.created_at';

            $tenantQuery
                ->orderBy($sortableColumn, $tenantSortDir)
                ->orderBy('tenants.id', $tenantSortDir);

            $tenantPaginator = $this->cursorPaginateWithFallback(
                $tenantQuery,
                $tenantPerPage,
                ['*'],
                $tenantCursor,
            );

            $tenantRows = collect($tenantPaginator->items())
                ->map(function (Tenant $tenant) use ($tenantRootDomain, $tenantRootScheme): array {
                    $activeSubscription = $this->resolveActiveSubscription($tenant);
                    $planPrice = $activeSubscription?->planPrice;
                    $plan = $planPrice?->plan;

                    return [
                        'id' => (string) $tenant->id,
                        'name' => (string) $tenant->name,
                        'code' => (string) $tenant->code,
                        'subdomain' => trim((string) ($tenant->subdomain ?? '')),
                        'phone' => trim((string) ($tenant->getAttribute('phone') ?? '')),
                        'address' => trim((string) ($tenant->getAttribute('address') ?? '')),
                        'subdomain_url' => $this->buildTenantSubdomainUrl(
                            $tenant->subdomain ?? null,
                            $tenantRootDomain,
                            $tenantRootScheme,
                        ),
                        'is_active' => (bool) $tenant->is_active,
                        'workshops_count' => (int) ($tenant->workshops_count ?? 0),
                        'users_count' => (int) ($tenant->users_count ?? 0),
                        'package' => $activeSubscription && $planPrice && $plan
                            ? [
                                'subscription_id' => (string) $activeSubscription->id,
                                'status' => (string) $activeSubscription->status,
                                'started_at' => $activeSubscription->started_at,
                                'expired_at' => $activeSubscription->expired_at,
                                'trial_ends_at' => $activeSubscription->trial_ends_at,
                                'plan' => [
                                    'id' => (int) $plan->id,
                                    'name' => (string) $plan->name,
                                    'slug' => (string) $plan->slug,
                                ],
                                'price' => [
                                    'id' => (int) $planPrice->id,
                                    'label' => (string) $planPrice->label,
                                    'duration_months' => (int) $planPrice->duration_months,
                                    'amount' => (float) $planPrice->price,
                                    'discount_pct' => (int) $planPrice->discount_pct,
                                ],
                            ]
                            : null,
                    ];
                })
                ->values();

            $tenantPayload = [
                'mode' => 'cursor',
                'data' => $tenantRows->all(),
                'per_page' => $tenantPaginator->perPage(),
                'total' => $tenantTotal,
                'from' => $tenantRows->isEmpty() ? 0 : 1,
                'to' => $tenantRows->count(),
                'current_cursor' => $tenantPaginator->cursor()?->encode(),
                'next_cursor' => $tenantPaginator->nextCursor()?->encode(),
                'prev_cursor' => $tenantPaginator->previousCursor()?->encode(),
                'has_more_pages' => $tenantPaginator->hasMorePages(),
            ];
        }

        $planOptions = [];
        if (Schema::hasTable('plan_prices') && Schema::hasTable('plans')) {
            $planOptions = PlanPrice::query()
                ->with('plan')
                ->where('is_active', true)
                ->whereHas('plan', fn ($query) => $query->where('is_active', true))
                ->orderBy('price')
                ->orderBy('duration_months')
                ->orderBy('id')
                ->get(['id', 'plan_id', 'label', 'duration_months', 'price', 'discount_pct'])
                ->map(function (PlanPrice $planPrice): array {
                    return [
                        'id' => (int) $planPrice->id,
                        'label' => (string) $planPrice->label,
                        'duration_months' => (int) $planPrice->duration_months,
                        'amount' => (float) $planPrice->price,
                        'discount_pct' => (int) $planPrice->discount_pct,
                        'plan' => [
                            'id' => (int) ($planPrice->plan?->id ?? 0),
                            'name' => (string) ($planPrice->plan?->name ?? '-'),
                            'slug' => (string) ($planPrice->plan?->slug ?? '-'),
                        ],
                    ];
                })
                ->values()
                ->all();
        }

        $tenantsCount = Schema::hasTable('tenants')
            ? (int) Tenant::query()->count()
            : 0;

        return [
            'tenants' => $tenantPayload,
            'tenantFilters' => [
                'search' => $tenantSearch,
                'sort_by' => $tenantSortBy,
                'sort_dir' => $tenantSortDir,
                'per_page' => $tenantPerPage,
                'cursor' => $tenantPayload['current_cursor'],
            ],
            'tenantRootDomain' => $tenantRootDomain,
            'planOptions' => $planOptions,
            'tenantsCount' => $tenantsCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{search: string, sort_by: string, sort_dir: string}
     */
    public function normalizeExportFilters(array $payload): array
    {
        return [
            'search' => trim((string) ($payload['tenant_search'] ?? '')),
            'sort_by' => $this->resolveSortBy((string) ($payload['tenant_sort_by'] ?? 'created_at')),
            'sort_dir' => $this->resolveSortDirection((string) ($payload['tenant_sort_dir'] ?? 'desc')),
        ];
    }

    /**
     * @param  array{search: string, sort_by: string, sort_dir: string}  $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function buildTenantExportRows(array $filters): LazyCollection
    {
        if (! Schema::hasTable('tenants')) {
            return LazyCollection::make(static fn (): \Generator => yield from []);
        }

        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $sortableColumn = [
            'name' => 'tenants.name',
            'code' => 'tenants.code',
            'is_active' => 'tenants.is_active',
            'created_at' => 'tenants.created_at',
        ][(string) ($filters['sort_by'] ?? 'created_at')] ?? 'tenants.created_at';

        return $this->buildTenantExportBaseQuery($filters)
            ->orderBy($sortableColumn, $sortDir)
            ->orderBy('tenants.id', $sortDir)
            ->cursor()
            ->map(fn (Tenant $tenant): array => $this->mapTenantExportRow($tenant));
    }

    public function buildTenantExportSpreadsheet(LazyCollection $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Daftar Tenant');

        $headers = [
            'A' => 'Nama Tenant',
            'B' => 'Nama Owner',
            'C' => 'Email Owner',
            'D' => 'Paket Aktif',
            'E' => 'Tanggal Mulai Paket',
        ];

        foreach ($headers as $column => $label) {
            $sheet->setCellValue($column.'1', $label);
        }

        $columnWidths = [
            'A' => 32,
            'B' => 28,
            'C' => 34,
            'D' => 26,
            'E' => 22,
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:E1');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $currentRow = 2;

        foreach ($rows as $row) {
            $sheet->setCellValueExplicit('A'.$currentRow, $this->sanitizeExcelString($row['tenant_name'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$currentRow, $this->sanitizeExcelString($row['owner_name'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$currentRow, $this->sanitizeExcelString($row['owner_email'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$currentRow, $this->sanitizeExcelString($row['active_plan_name'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E'.$currentRow, $this->formatExcelDateValue($row['active_plan_started_at'] ?? null), DataType::TYPE_STRING);
            $currentRow++;
        }

        $lastDataRow = max(1, $currentRow - 1);
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF0F766E'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF0B5E57'],
                ],
            ],
        ]);

        if ($lastDataRow >= 2) {
            $sheet->getStyle("A2:E{$lastDataRow}")->applyFromArray([
                'font' => [
                    'size' => 10,
                    'color' => ['argb' => 'FF1E293B'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFE2E8F0'],
                    ],
                ],
            ]);

            for ($rowIndex = 2; $rowIndex <= $lastDataRow; $rowIndex++) {
                if ($rowIndex % 2 !== 0) {
                    continue;
                }

                $sheet->getStyle("A{$rowIndex}:E{$rowIndex}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFF8FAFC');
            }
        }

        return $spreadsheet;
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function createTenant(array $validated): void
    {
        $this->assertTenantsTableReady('create_tenant', 'Tabel tenant belum siap.');

        DB::transaction(function () use ($validated): void {
            $tenantPayload = $this->normalizeTenantPayload($validated);

            $tenant = Tenant::query()->create($tenantPayload);
            $this->createOwnerAccountForTenant($tenant, $validated);

            $planPriceId = array_key_exists('plan_price_id', $validated)
                ? (int) ($validated['plan_price_id'] ?? 0)
                : 0;
            $planStartedAt = $this->extractPlanStartedAt($validated);

            if ($planPriceId > 0) {
                $this->assignPlanToTenant((string) $tenant->id, $planPriceId, $planStartedAt);
            }
        });
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function updateTenant(string $tenantId, array $validated): void
    {
        $this->assertTenantsTableReady('update_tenant', 'Tabel tenant belum siap.');

        $tenant = $this->findTenantOrFail($tenantId, 'update_tenant', 'Tenant tidak ditemukan.');

        DB::transaction(function () use ($tenant, $validated): void {
            $tenant->forceFill($this->normalizeTenantPayload($validated, $tenant))->save();

            $planPriceId = array_key_exists('plan_price_id', $validated)
                ? (int) ($validated['plan_price_id'] ?? 0)
                : 0;
            $planStartedAt = $this->extractPlanStartedAt($validated);

            if ($planPriceId > 0) {
                $this->assignPlanToTenant((string) $tenant->id, $planPriceId, $planStartedAt);
            }
        });
    }

    public function updateTenantStatus(string $tenantId, bool $isActive): void
    {
        $this->assertTenantsTableReady('status_tenant', 'Tabel tenant belum siap.');

        $tenant = $this->findTenantOrFail($tenantId, 'status_tenant', 'Tenant tidak ditemukan.');

        DB::transaction(function () use ($tenant, $isActive): void {
            $tenant->forceFill(['is_active' => $isActive])->save();

            if (! $isActive && Schema::hasTable('workshop_subscriptions')) {
                WorkshopSubscription::query()
                    ->where('tenant_id', (string) $tenant->id)
                    ->whereIn('status', ['trial', 'active'])
                    ->update([
                        'status' => 'suspended',
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    /**
     * @param  array{search: string, sort_by: string, sort_dir: string}  $filters
     */
    private function buildTenantExportBaseQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Tenant::query()
            ->select([
                'tenants.id',
                'tenants.name',
                'tenants.code',
                'tenants.created_at',
                'tenants.is_active',
            ])
            ->when($search !== '', function (Builder $searchQuery) use ($search): void {
                $searchQuery->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('tenants.name', 'like', "%{$search}%")
                        ->orWhere('tenants.code', 'like', "%{$search}%");
                });
            });

        $this->applyOwnerExportSelect($query);
        $this->applyPackageExportSelect($query);

        return $query;
    }

    private function applyOwnerExportSelect(Builder $query): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $hasIsOwnerColumn = Schema::hasColumn('users', 'is_owner');
        $hasUserTypeColumn = Schema::hasColumn('users', 'user_type');

        $ownerNameQuery = User::query()
            ->select('name')
            ->whereColumn('users.tenant_id', 'tenants.id');

        $ownerEmailQuery = User::query()
            ->select('email')
            ->whereColumn('users.tenant_id', 'tenants.id');

        if ($hasIsOwnerColumn || $hasUserTypeColumn) {
            $ownerNameQuery->where(function (Builder $ownerScopeQuery) use ($hasIsOwnerColumn, $hasUserTypeColumn): void {
                if ($hasIsOwnerColumn) {
                    $ownerScopeQuery->where('is_owner', true);
                }

                if ($hasUserTypeColumn) {
                    if ($hasIsOwnerColumn) {
                        $ownerScopeQuery->orWhere('user_type', 'owner');
                    } else {
                        $ownerScopeQuery->where('user_type', 'owner');
                    }
                }
            });

            $ownerEmailQuery->where(function (Builder $ownerScopeQuery) use ($hasIsOwnerColumn, $hasUserTypeColumn): void {
                if ($hasIsOwnerColumn) {
                    $ownerScopeQuery->where('is_owner', true);
                }

                if ($hasUserTypeColumn) {
                    if ($hasIsOwnerColumn) {
                        $ownerScopeQuery->orWhere('user_type', 'owner');
                    } else {
                        $ownerScopeQuery->where('user_type', 'owner');
                    }
                }
            });
        }

        if ($hasIsOwnerColumn) {
            $ownerNameQuery->orderByDesc('is_owner');
            $ownerEmailQuery->orderByDesc('is_owner');
        }

        $ownerNameQuery->orderBy('created_at')->limit(1);
        $ownerEmailQuery->orderBy('created_at')->limit(1);

        $query->selectSub($ownerNameQuery, 'owner_name');
        $query->selectSub($ownerEmailQuery, 'owner_email');
    }

    private function applyPackageExportSelect(Builder $query): void
    {
        if (
            ! Schema::hasTable('workshop_subscriptions')
            || ! Schema::hasTable('plan_prices')
            || ! Schema::hasTable('plans')
        ) {
            return;
        }

        $tenantColumn = Schema::hasColumn('workshop_subscriptions', 'tenant_id')
            ? 'tenant_id'
            : (Schema::hasColumn('workshop_subscriptions', 'workshop_id') ? 'workshop_id' : null);

        if ($tenantColumn === null) {
            return;
        }

        $planNameQuery = WorkshopSubscription::query()
            ->select('plans.name')
            ->join('plan_prices', 'plan_prices.id', '=', 'workshop_subscriptions.plan_price_id')
            ->join('plans', 'plans.id', '=', 'plan_prices.plan_id')
            ->whereColumn("workshop_subscriptions.{$tenantColumn}", 'tenants.id')
            ->whereIn('workshop_subscriptions.status', ['trial', 'active'])
            ->orderByDesc('workshop_subscriptions.started_at')
            ->orderByDesc('workshop_subscriptions.created_at')
            ->limit(1);

        $planStartedAtQuery = WorkshopSubscription::query()
            ->select('workshop_subscriptions.started_at')
            ->whereColumn("workshop_subscriptions.{$tenantColumn}", 'tenants.id')
            ->whereIn('workshop_subscriptions.status', ['trial', 'active'])
            ->orderByDesc('workshop_subscriptions.started_at')
            ->orderByDesc('workshop_subscriptions.created_at')
            ->limit(1);

        $query->selectSub($planNameQuery, 'active_plan_name');
        $query->selectSub($planStartedAtQuery, 'active_plan_started_at');
    }

    /**
     * @return array<string, string|DateTimeInterface|null>
     */
    private function mapTenantExportRow(Tenant $tenant): array
    {
        $activePlanName = trim((string) ($tenant->getAttribute('active_plan_name') ?? ''));

        return [
            'tenant_name' => trim((string) ($tenant->name ?? '-')),
            'owner_name' => trim((string) ($tenant->getAttribute('owner_name') ?? '-')),
            'owner_email' => trim((string) ($tenant->getAttribute('owner_email') ?? '-')),
            'active_plan_name' => $activePlanName !== '' ? $activePlanName : 'Belum Paket',
            'active_plan_started_at' => $tenant->getAttribute('active_plan_started_at'),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function normalizeTenantPayload(array $validated, ?Tenant $currentTenant = null): array
    {
        $tenantName = trim((string) $validated['name']);
        $payload = [
            'name' => $tenantName,
            'code' => $this->normalizeTenantCode((string) $validated['code']),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ];

        if (Schema::hasColumn('tenants', 'subdomain')) {
            $payload['subdomain'] = $this->resolveTenantSubdomain(
                $tenantName,
                trim((string) ($validated['subdomain'] ?? '')),
                $currentTenant?->id ? (string) $currentTenant->id : null,
            );
        }

        if (Schema::hasColumn('tenants', 'phone')) {
            $payload['phone'] = trim((string) ($validated['phone'] ?? ''));
        }

        if (Schema::hasColumn('tenants', 'address')) {
            $payload['address'] = $this->normalizeNullableString($validated['address'] ?? null);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function createOwnerAccountForTenant(Tenant $tenant, array $validated): void
    {
        if (! Schema::hasTable('users')) {
            throw ValidationException::withMessages([
                'create_tenant' => 'Tabel users belum siap untuk membuat akun owner tenant.',
            ]);
        }

        $ownerName = trim((string) ($validated['owner_name'] ?? ''));
        $ownerEmail = strtolower(trim((string) ($validated['owner_email'] ?? '')));
        $ownerPassword = (string) ($validated['owner_password'] ?? '');

        if ($ownerName === '' || $ownerEmail === '' || $ownerPassword === '') {
            throw ValidationException::withMessages([
                'create_tenant' => 'Data akun owner wajib diisi saat membuat tenant.',
            ]);
        }

        $ownerUser = User::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => $ownerName,
            'email' => $ownerEmail,
            'password' => $ownerPassword,
            'role' => 'owner',
            'user_type' => 'owner',
            'is_superadmin' => false,
            'is_owner' => true,
            'email_verified_at' => now(),
        ]);

        $this->syncOwnerRoleToTenantUser($ownerUser, (string) $tenant->id);
    }

    private function syncOwnerRoleToTenantUser(User $ownerUser, string $tenantId): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $tenantOwnerRole = Role::query()->firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);

        $templateOwnerRole = Role::query()
            ->where('name', 'owner')
            ->whereNull('tenant_id')
            ->first();

        if (! $templateOwnerRole) {
            $templateOwnerRole = Role::query()
                ->where('name', 'owner')
                ->where('tenant_id', '!=', $tenantId)
                ->orderBy('id')
                ->first();
        }

        $permissionIds = [];
        if ($templateOwnerRole && Schema::hasTable('role_has_permissions')) {
            $permissionIds = RoleHasPermission::query()
                ->where('role_id', (int) $templateOwnerRole->id)
                ->pluck('permission_id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->filter(fn (int $permissionId): bool => $permissionId > 0)
                ->unique()
                ->values()
                ->all();
        }

        if (count($permissionIds) === 0 && Schema::hasTable('permissions')) {
            $permissionIds = Permission::query()
                ->where('name', 'owner.dashboard.view')
                ->pluck('id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->filter(fn (int $permissionId): bool => $permissionId > 0)
                ->values()
                ->all();
        }

        if (count($permissionIds) > 0) {
            $tenantOwnerRole->syncPermissions($permissionIds);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $ownerUser->syncRoles([$tenantOwnerRole]);
    }

    private function normalizeTenantCode(string $code): string
    {
        $normalized = preg_replace('/[^A-Z0-9-]/', '', Str::upper(trim($code)));
        $normalized = is_string($normalized) ? $normalized : '';

        return Str::limit($normalized !== '' ? $normalized : 'TENANT', 20, '');
    }

    private function normalizeTenantSubdomain(string $value): string
    {
        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();

        $limited = trim(Str::limit($normalized !== '' ? $normalized : 'tenant', 63, ''), '-');

        return $limited !== '' ? $limited : 'tenant';
    }

    private function resolveTenantSubdomain(
        string $tenantName,
        string $requestedSubdomain = '',
        ?string $ignoredTenantId = null,
    ): string
    {
        $isManualSubdomain = trim($requestedSubdomain) !== '';
        $subdomainSource = $isManualSubdomain ? $requestedSubdomain : $tenantName;
        $subdomain = $this->normalizeTenantSubdomain($subdomainSource);

        $query = Tenant::query()->where('subdomain', $subdomain);
        if ($ignoredTenantId !== null && $ignoredTenantId !== '') {
            $query->where('id', '!=', $ignoredTenantId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'subdomain' => $isManualSubdomain
                    ? "Subdomain '{$subdomain}' sudah terdaftar. Gunakan subdomain lain."
                    : "Subdomain otomatis '{$subdomain}' sudah terdaftar. Ubah subdomain secara manual.",
            ]);
        }

        return $subdomain;
    }

    private function buildTenantSubdomainUrl(
        mixed $subdomain,
        string $rootDomain = '',
        string $scheme = '',
    ): ?string
    {
        $normalizedSubdomain = trim((string) $subdomain);
        if ($normalizedSubdomain === '') {
            return null;
        }

        $host = trim($rootDomain);
        $normalizedScheme = trim($scheme);

        if ($host === '') {
            $appUrl = trim((string) config('app.url', ''));
            $host = trim((string) parse_url($appUrl, PHP_URL_HOST));
        }

        if ($host === '') {
            return null;
        }

        if ($normalizedScheme === '') {
            $appUrl = trim((string) config('app.url', ''));
            $normalizedScheme = trim((string) parse_url($appUrl, PHP_URL_SCHEME));
        }

        $normalizedScheme = $normalizedScheme !== '' ? $normalizedScheme : 'https';

        return "{$normalizedScheme}://{$normalizedSubdomain}.{$host}";
    }

    private function resolveTenantRootDomain(Request $request): string
    {
        $appUrl = trim((string) config('app.url', ''));
        $appHost = trim((string) parse_url($appUrl, PHP_URL_HOST));
        if ($appHost !== '') {
            return strtolower($appHost);
        }

        $requestHost = trim((string) $request->getHost());
        if ($requestHost !== '') {
            if (str_contains($requestHost, ':')) {
                [$requestHostWithoutPort] = explode(':', $requestHost, 2);
                $requestHost = trim($requestHostWithoutPort);
            }

            return strtolower($requestHost);
        }

        return '';
    }

    private function resolveTenantRootScheme(Request $request): string
    {
        $appUrl = trim((string) config('app.url', ''));
        $appScheme = trim((string) parse_url($appUrl, PHP_URL_SCHEME));
        if ($appScheme !== '') {
            return strtolower($appScheme);
        }

        $requestScheme = trim((string) $request->getScheme());
        if ($requestScheme !== '') {
            return strtolower($requestScheme);
        }

        return 'https';
    }

    private function resolveActiveSubscription(Tenant $tenant): ?WorkshopSubscription
    {
        if (! $tenant->relationLoaded('subscriptions')) {
            return null;
        }

        return $tenant->subscriptions
            ->first(fn (WorkshopSubscription $subscription): bool => $subscription->planPrice?->plan !== null);
    }

    private function assignPlanToTenant(string $tenantId, int $planPriceId, string $planStartedAt = ''): void
    {
        if (! Schema::hasTable('workshop_subscriptions') || ! Schema::hasTable('plan_prices') || ! Schema::hasTable('plans')) {
            throw ValidationException::withMessages([
                'plan_price_id' => 'Data plan subscription belum siap.',
            ]);
        }

        $planPrice = PlanPrice::query()
            ->with('plan')
            ->where('id', $planPriceId)
            ->where('is_active', true)
            ->first();

        if (! $planPrice || ! $planPrice->plan || ! $planPrice->plan->is_active) {
            throw ValidationException::withMessages([
                'plan_price_id' => 'Plan price tidak valid atau sudah nonaktif.',
            ]);
        }

        $currentSubscription = WorkshopSubscription::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['trial', 'active'])
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->first();

        $startedAt = $this->resolvePlanStartAt($planStartedAt);
        $hasTrial = (bool) $planPrice->plan->has_trial;
        $trialDurationDays = max(0, (int) $planPrice->plan->trial_duration_days);
        $nextStatus = $hasTrial && $trialDurationDays > 0 ? 'trial' : 'active';

        if ($currentSubscription && (int) $currentSubscription->plan_price_id === $planPriceId) {
            $currentStartedAt = $currentSubscription->started_at;
            if ($currentStartedAt && $currentStartedAt->toDateString() === $startedAt->toDateString()) {
                return;
            }

            $currentSubscription->forceFill([
                'status' => $nextStatus,
                'started_at' => $startedAt,
                'expired_at' => $startedAt->copy()->addMonthsNoOverflow(max(1, (int) $planPrice->duration_months)),
                'trial_ends_at' => $hasTrial && $trialDurationDays > 0
                    ? $startedAt->copy()->addDays($trialDurationDays)
                    : null,
            ])->save();

            return;
        }

        if ($currentSubscription) {
            $currentSubscription->forceFill([
                'status' => 'cancelled',
                'expired_at' => now(),
            ])->save();
        }

        WorkshopSubscription::query()->create([
            'tenant_id' => $tenantId,
            'plan_price_id' => (int) $planPrice->id,
            'status' => $nextStatus,
            'started_at' => $startedAt,
            'expired_at' => $startedAt->copy()->addMonthsNoOverflow(max(1, (int) $planPrice->duration_months)),
            'trial_ends_at' => $hasTrial && $trialDurationDays > 0
                ? $startedAt->copy()->addDays($trialDurationDays)
                : null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function extractPlanStartedAt(array $validated): string
    {
        return trim((string) ($validated['plan_started_at'] ?? ''));
    }

    private function resolvePlanStartAt(string $planStartedAt): Carbon
    {
        $normalizedDate = trim($planStartedAt);
        if ($normalizedDate === '') {
            return now();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $normalizedDate)->startOfDay();
        } catch (\Throwable) {
            return now();
        }
    }

    private function assertTenantsTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('tenants')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function findTenantOrFail(string $tenantId, string $errorKey, string $message): Tenant
    {
        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }

        return $tenant;
    }

    private function sanitizeExcelString(mixed $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '-';
        }

        $firstChar = substr($normalized, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@'], true)) {
            return "'".$normalized;
        }

        return $normalized;
    }

    private function formatExcelDateValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '-';
        }

        try {
            return Carbon::parse($normalized)->format('d/m/Y');
        } catch (\Throwable) {
            return $normalized;
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
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

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'tenant_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'tenant_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
