<?php

namespace App\Services\Owner;

use App\Models\User;
use App\Models\Workshop;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class OwnerUserService
{
    /**
     * @var array<int, string>
     */
    private const MANAGED_ROLES = ['admin', 'mekanik'];

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
        $userSearch = trim((string) $request->query('user_search', ''));
        $userSortBy = $this->resolveSortBy((string) $request->query('user_sort_by', 'created_at'));
        $userSortDir = $this->resolveSortDirection((string) $request->query('user_sort_dir', 'desc'));
        $userPerPage = $this->resolvePerPage((int) $request->query('user_per_page', 10));
        $userCursor = trim((string) $request->query('user_cursor', ''));

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

        $usersPayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $userPerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $userSummary = [
            'total' => 0,
            'admin' => 0,
            'mekanik' => 0,
        ];

        if (Schema::hasTable('users')) {
            $summaryQuery = $this->queryTenantManagedUsers($tenantId, $activeWorkshopId);
            $totalUsers = (int) (clone $summaryQuery)->count();
            $adminUsers = (int) (clone $summaryQuery)
                ->where(function (Builder $query): void {
                    $this->applyRoleFilter($query, 'admin');
                })
                ->count();
            $mechanicUsers = (int) (clone $summaryQuery)
                ->where(function (Builder $query): void {
                    $this->applyRoleFilter($query, 'mekanik');
                })
                ->count();

            $userSummary = [
                'total' => $totalUsers,
                'admin' => $adminUsers,
                'mekanik' => $mechanicUsers,
            ];

            $sortableColumn = [
                'name' => 'users.name',
                'email' => 'users.email',
                'role' => 'users.user_type',
                'created_at' => 'users.created_at',
            ][$userSortBy] ?? 'users.created_at';

            $userPaginator = $this->cursorPaginateWithFallback(
                $this->queryTenantManagedUsers($tenantId, $activeWorkshopId)
                    ->with(['roles:id,name,tenant_id'])
                    ->when($userSearch !== '', function (Builder $query) use ($userSearch): void {
                        $query->where(function (Builder $nestedQuery) use ($userSearch): void {
                            $nestedQuery
                                ->where('users.name', 'like', "%{$userSearch}%")
                                ->orWhere('users.email', 'like', "%{$userSearch}%")
                                ->orWhere('users.user_type', 'like', "%{$userSearch}%")
                                ->orWhere('users.role', 'like', "%{$userSearch}%")
                                ->orWhereHas('roles', function (Builder $roleQuery) use ($userSearch): void {
                                    $roleQuery->where('name', 'like', "%{$userSearch}%");
                                });
                        });
                    })
                    ->orderBy($sortableColumn, $userSortDir)
                    ->orderBy('users.id', $userSortDir),
                $userPerPage,
                ['users.id', 'users.name', 'users.email', 'users.role', 'users.user_type', 'users.created_at', 'users.updated_at'],
                $userCursor,
            );

            $userRows = collect($userPaginator->items())
                ->map(function (User $managedUser): array {
                    $roleKey = $this->resolveUserRoleKey($managedUser);
                    $workshopName = trim((string) ($managedUser->workshop?->name ?? ''));
                    $workshopCode = trim((string) ($managedUser->workshop?->code ?? ''));

                    return [
                        'id' => (string) $managedUser->id,
                        'workshop_id' => (string) ($managedUser->workshop_id ?? ''),
                        'workshop_name' => $workshopName,
                        'workshop_code' => $workshopCode,
                        'name' => (string) $managedUser->name,
                        'email' => (string) $managedUser->email,
                        'role' => $roleKey,
                        'role_label' => $this->resolveRoleLabel($roleKey),
                        'created_at' => $managedUser->created_at,
                        'updated_at' => $managedUser->updated_at,
                    ];
                })
                ->values();

            $usersPayload = [
                'mode' => 'cursor',
                'data' => $userRows->all(),
                'per_page' => $userPaginator->perPage(),
                'total' => $totalUsers,
                'from' => $userRows->isEmpty() ? 0 : 1,
                'to' => $userRows->count(),
                'current_cursor' => $userPaginator->cursor()?->encode(),
                'next_cursor' => $userPaginator->nextCursor()?->encode(),
                'prev_cursor' => $userPaginator->previousCursor()?->encode(),
                'has_more_pages' => $userPaginator->hasMorePages(),
            ];
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'users' => $usersPayload,
            'userFilters' => [
                'search' => $userSearch,
                'sort_by' => $userSortBy,
                'sort_dir' => $userSortDir,
                'per_page' => $userPerPage,
                'cursor' => $usersPayload['current_cursor'],
            ],
            'userSummary' => $userSummary,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createUser(string $tenantId, string $activeWorkshopId, array $validated): void
    {
        $this->assertUsersTableReady('create_user', 'Tabel user belum siap.');
        $targetWorkshopId = $this->resolveTargetWorkshopId($tenantId, $activeWorkshopId, $validated, 'workshop_id');
        $tenantRole = $this->resolveTenantRole($tenantId, (string) ($validated['role'] ?? ''));

        DB::transaction(function () use ($tenantId, $targetWorkshopId, $validated, $tenantRole): void {
            $payload = $this->normalizeUserPayload($tenantId, $targetWorkshopId, $validated, includePassword: true);
            $user = User::query()->create($payload);
            $user->syncRoles([$tenantRole]);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateUser(string $tenantId, string $activeWorkshopId, string $userId, array $validated): void
    {
        $this->assertUsersTableReady('update_user', 'Tabel user belum siap.');
        $managedUser = $this->findTenantManagedUserOrFail($tenantId, $activeWorkshopId, $userId, 'update_user');
        $targetWorkshopId = $this->resolveTargetWorkshopId(
            $tenantId,
            $activeWorkshopId,
            $validated,
            'workshop_id',
            fallbackWorkshopId: (string) ($managedUser->workshop_id ?? ''),
        );
        $tenantRole = $this->resolveTenantRole($tenantId, (string) ($validated['role'] ?? ''));

        DB::transaction(function () use ($tenantId, $targetWorkshopId, $validated, $managedUser, $tenantRole): void {
            $managedUser->forceFill($this->normalizeUserPayload($tenantId, $targetWorkshopId, $validated, includePassword: true))
                ->save();

            $managedUser->syncRoles([$tenantRole]);
        });
    }

    public function deleteUser(string $tenantId, string $activeWorkshopId, string $userId, string $actorUserId = ''): void
    {
        $this->assertUsersTableReady('delete_user', 'Tabel user belum siap.');

        if ($actorUserId !== '' && $actorUserId === $userId) {
            throw ValidationException::withMessages([
                'delete_user' => 'Akun Anda sendiri tidak dapat dihapus.',
            ]);
        }

        $managedUser = $this->findTenantManagedUserOrFail($tenantId, $activeWorkshopId, $userId, 'delete_user');

        DB::transaction(function () use ($managedUser): void {
            $managedUser->syncRoles([]);
            $managedUser->delete();
        });
    }

    private function queryTenantManagedUsers(string $tenantId, string $activeWorkshopId): Builder
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where(function (Builder $scopedQuery) use ($activeWorkshopId): void {
                    $scopedQuery
                        ->where('workshop_id', $activeWorkshopId)
                        ->orWhereNull('workshop_id');
                });
            })
            ->where('is_superadmin', false)
            ->where('is_owner', false)
            ->with(['workshop:id,name,code'])
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('users.user_type', self::MANAGED_ROLES)
                    ->orWhereIn('users.role', self::MANAGED_ROLES)
                    ->orWhereHas('roles', function (Builder $roleQuery): void {
                        $roleQuery->whereIn('name', self::MANAGED_ROLES);
                    });
            });
    }

    private function applyRoleFilter(Builder $query, string $roleName): void
    {
        $normalizedRole = $this->normalizeRoleKey($roleName);
        if ($normalizedRole === '') {
            return;
        }

        $query
            ->whereRaw('LOWER(COALESCE(users.user_type, ?)) = ?', ['', $normalizedRole])
            ->orWhereRaw('LOWER(COALESCE(users.role, ?)) = ?', ['', $normalizedRole])
            ->orWhereHas('roles', function (Builder $roleQuery) use ($normalizedRole): void {
                $roleQuery->whereRaw('LOWER(name) = ?', [$normalizedRole]);
            });
    }

    private function resolveTenantRole(string $tenantId, string $roleName): Role
    {
        $normalizedRole = $this->normalizeRoleKey($roleName);
        if ($normalizedRole === '') {
            throw ValidationException::withMessages([
                'role' => 'Tipe user harus Admin atau Mekanik.',
            ]);
        }

        if (! Schema::hasTable('roles')) {
            throw ValidationException::withMessages([
                'role' => 'Role tenant belum siap.',
            ]);
        }

        return Role::query()->firstOrCreate([
            'tenant_id' => $tenantId,
            'name' => $normalizedRole,
            'guard_name' => 'web',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeUserPayload(
        string $tenantId,
        string $activeWorkshopId,
        array $validated,
        bool $includePassword,
    ): array
    {
        $normalizedRole = $this->normalizeRoleKey((string) ($validated['role'] ?? ''));
        $payload = [
            'tenant_id' => $tenantId,
            'workshop_id' => $activeWorkshopId !== '' ? $activeWorkshopId : null,
            'name' => trim((string) ($validated['name'] ?? '')),
            'email' => $this->normalizeEmail((string) ($validated['email'] ?? '')),
            'role' => $normalizedRole,
            'user_type' => $normalizedRole,
            'is_superadmin' => false,
            'is_owner' => false,
        ];

        if ($includePassword) {
            $password = trim((string) ($validated['password'] ?? ''));
            if ($password !== '') {
                $payload['password'] = $password;
            }
        }

        return $payload;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function normalizeRoleKey(string $roleName): string
    {
        $normalizedRole = strtolower(trim($roleName));

        return in_array($normalizedRole, self::MANAGED_ROLES, true)
            ? $normalizedRole
            : '';
    }

    private function resolveUserRoleKey(User $user): string
    {
        $fromUserType = $this->normalizeRoleKey((string) ($user->user_type ?? ''));
        if ($fromUserType !== '') {
            return $fromUserType;
        }

        $fromRoleColumn = $this->normalizeRoleKey((string) ($user->role ?? ''));
        if ($fromRoleColumn !== '') {
            return $fromRoleColumn;
        }

        $roleNames = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->all()
            : $user->roles()->pluck('name')->all();

        foreach ($roleNames as $roleName) {
            $normalizedRole = $this->normalizeRoleKey((string) $roleName);
            if ($normalizedRole !== '') {
                return $normalizedRole;
            }
        }

        return 'admin';
    }

    private function resolveRoleLabel(string $roleKey): string
    {
        if ($roleKey === 'mekanik') {
            return 'Mekanik';
        }

        return 'Admin';
    }

    private function shouldApplyWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId)
            && $this->hasUserWorkshopScope()
            && $this->hasActiveWorkshops($tenantId);
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
        $hasActiveWorkshops = $this->hasActiveWorkshops($tenantId);
        if ($requestedWorkshopId === '') {
            $requestedWorkshopId = $this->shouldApplyWorkshopScope($tenantId, $activeWorkshopId)
                ? trim($activeWorkshopId)
                : trim($fallbackWorkshopId);
        }

        if ($requestedWorkshopId === '') {
            if (! $hasActiveWorkshops) {
                return trim($fallbackWorkshopId);
            }

            throw ValidationException::withMessages([
                $errorKey => 'Pilih bengkel tujuan terlebih dahulu.',
            ]);
        }

        if (! Schema::hasTable('workshops') || ! $hasActiveWorkshops) {
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

    private function hasUserWorkshopScope(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasColumn('users', 'workshop_id');
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

    private function findTenantManagedUserOrFail(
        string $tenantId,
        string $activeWorkshopId,
        string $userId,
        string $errorKey,
    ): User
    {
        $managedUser = $this->queryTenantManagedUsers($tenantId, $activeWorkshopId)
            ->where('id', $userId)
            ->first();

        if (! $managedUser) {
            throw ValidationException::withMessages([
                $errorKey => 'User tidak ditemukan.',
            ]);
        }

        return $managedUser;
    }

    private function assertUsersTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('users')) {
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
        return in_array($sortBy, ['name', 'email', 'role', 'created_at'], true)
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
                ->cursorPaginate($perPage, $columns, 'user_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'user_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
