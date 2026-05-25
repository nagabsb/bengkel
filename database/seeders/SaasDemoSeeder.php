<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\Plan;
use App\Models\PlanMenu;
use App\Models\PlanPrice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopSubscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SaasDemoSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $now = now();
            $tenantId = $this->upsertTenant();
            $this->upsertWorkshops($tenantId);
            $planPriceIds = $this->upsertPlanCatalog();
            $this->upsertWorkshopSubscription($tenantId, $planPriceIds['growth_monthly'], $now);

            $permissionNames = [
                'platform.tenants.view',
                'platform.tenants.manage',
                'platform.billing.view',
                'platform.billing.manage',
                'owner.dashboard.view',
                'users.manage',
                'service_orders.view',
                'service_orders.manage',
                'inventory.view',
                'inventory.manage',
                'finance.view',
                'finance.manage',
                'expenses.view',
                'expenses.manage',
                'expense_categories.view',
                'expense_categories.manage',
                'customers.view',
                'customers.manage',
                'suppliers.view',
                'suppliers.manage',
                'warehouses.view',
                'warehouses.manage',
                'spareparts.view',
                'spareparts.manage',
                'sparepart_categories.view',
                'sparepart_categories.manage',
                'sparepart_units.view',
                'sparepart_units.manage',
            ];

            $permissions = collect($permissionNames)
                ->mapWithKeys(fn (string $permissionName): array => [
                    $permissionName => Permission::findOrCreate($permissionName, 'web'),
                ]);
            $this->upsertMenuCatalog($tenantId, $now);

            $roles = [
                'superadmin' => $this->upsertRole('superadmin', null),
                'owner' => $this->upsertRole('owner', $tenantId),
                'admin' => $this->upsertRole('admin', $tenantId),
                'kasir' => $this->upsertRole('kasir', $tenantId),
                'mekanik' => $this->upsertRole('mekanik', $tenantId),
            ];

            $rolePermissions = [
                'superadmin' => $permissionNames,
                'owner' => [
                    'owner.dashboard.view',
                    'users.manage',
                    'service_orders.view',
                    'service_orders.manage',
                    'inventory.view',
                    'inventory.manage',
                    'finance.view',
                    'finance.manage',
                    'expenses.view',
                    'expenses.manage',
                    'expense_categories.view',
                    'expense_categories.manage',
                    'customers.view',
                    'customers.manage',
                    'suppliers.view',
                    'suppliers.manage',
                    'warehouses.view',
                    'warehouses.manage',
                    'spareparts.view',
                    'spareparts.manage',
                    'sparepart_categories.view',
                    'sparepart_categories.manage',
                    'sparepart_units.view',
                    'sparepart_units.manage',
                ],
                'admin' => [
                    'owner.dashboard.view',
                    'service_orders.view',
                    'service_orders.manage',
                    'inventory.view',
                    'inventory.manage',
                    'expenses.view',
                    'expenses.manage',
                    'expense_categories.view',
                    'expense_categories.manage',
                    'customers.view',
                    'customers.manage',
                    'suppliers.view',
                    'suppliers.manage',
                    'warehouses.view',
                    'warehouses.manage',
                    'spareparts.view',
                    'spareparts.manage',
                    'sparepart_categories.view',
                    'sparepart_categories.manage',
                    'sparepart_units.view',
                    'sparepart_units.manage',
                ],
                'kasir' => [
                    'owner.dashboard.view',
                    'service_orders.view',
                    'finance.view',
                    'finance.manage',
                    'expenses.view',
                    'expenses.manage',
                    'expense_categories.view',
                    'customers.view',
                    'suppliers.view',
                    'warehouses.view',
                    'spareparts.view',
                    'sparepart_categories.view',
                    'sparepart_units.view',
                ],
                'mekanik' => [
                    'owner.dashboard.view',
                    'service_orders.view',
                    'service_orders.manage',
                    'expense_categories.view',
                    'customers.view',
                    'suppliers.view',
                    'warehouses.view',
                    'spareparts.view',
                    'sparepart_categories.view',
                    'sparepart_units.view',
                ],
            ];

            foreach ($rolePermissions as $roleName => $rolePermissionNames) {
                $roles[$roleName]->syncPermissions(
                    $this->resolvePermissions($permissions, $rolePermissionNames),
                );
            }

            $superadminUser = $this->upsertUser([
                'name' => 'Super Admin AutoServ',
                'email' => 'superadmin@autoserv.test',
                'password' => 'password',
                'tenant_id' => null,
                'role' => 'superadmin',
                'user_type' => 'superadmin',
                'is_superadmin' => true,
                'is_owner' => false,
            ]);

            $ownerUser = $this->upsertUser([
                'name' => 'Owner AutoServ',
                'email' => 'owner@autoserv.test',
                'password' => 'password',
                'tenant_id' => $tenantId,
                'role' => 'owner',
                'user_type' => 'owner',
                'is_superadmin' => false,
                'is_owner' => true,
            ]);

            $adminUser = $this->upsertUser([
                'name' => 'Admin Workshop',
                'email' => 'admin@autoserv.test',
                'password' => 'password',
                'tenant_id' => $tenantId,
                'role' => 'admin',
                'user_type' => 'admin',
                'is_superadmin' => false,
                'is_owner' => false,
            ]);

            $cashierUser = $this->upsertUser([
                'name' => 'Kasir Workshop',
                'email' => 'kasir@autoserv.test',
                'password' => 'password',
                'tenant_id' => $tenantId,
                'role' => 'kasir',
                'user_type' => 'kasir',
                'is_superadmin' => false,
                'is_owner' => false,
            ]);

            $mechanicUser = $this->upsertUser([
                'name' => 'Mekanik Workshop',
                'email' => 'mekanik@autoserv.test',
                'password' => 'password',
                'tenant_id' => $tenantId,
                'role' => 'mekanik',
                'user_type' => 'mekanik',
                'is_superadmin' => false,
                'is_owner' => false,
            ]);

            $this->syncUserRole($superadminUser, $roles['superadmin']);
            $this->syncUserRole($ownerUser, $roles['owner']);
            $this->syncUserRole($adminUser, $roles['admin']);
            $this->syncUserRole($cashierUser, $roles['kasir']);
            $this->syncUserRole($mechanicUser, $roles['mekanik']);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    private function upsertTenant(): string
    {
        $tenant = Tenant::query()
            ->where('code', 'ASV-001')
            ->first();

        if ($tenant) {
            $tenant->forceFill([
                'name' => 'AutoServ Tenant Demo',
                'is_active' => true,
            ])->save();

            return (string) $tenant->id;
        }

        $tenant = Tenant::query()->create([
            'name' => 'AutoServ Tenant Demo',
            'code' => 'ASV-001',
            'is_active' => true,
        ]);

        return (string) $tenant->id;
    }

    private function upsertWorkshops(string $tenantId): void
    {
        $workshopCatalog = [
            [
                'code' => 'ASV-001',
                'name' => 'AutoServ Workshop Pusat',
                'pin_to_tenant_id' => true,
            ],
            [
                'code' => 'ASV-002',
                'name' => 'AutoServ Workshop Cabang',
                'pin_to_tenant_id' => false,
            ],
        ];

        foreach ($workshopCatalog as $workshopData) {
            $workshop = Workshop::query()
                ->where('code', $workshopData['code'])
                ->first();

            if ($workshop) {
                $workshop->forceFill([
                    'tenant_id' => $tenantId,
                    'name' => $workshopData['name'],
                    'is_active' => true,
                ])->save();

                continue;
            }

            $payload = [
                'tenant_id' => $tenantId,
                'name' => $workshopData['name'],
                'code' => $workshopData['code'],
                'is_active' => true,
            ];

            if (($workshopData['pin_to_tenant_id'] ?? false) === true) {
                Workshop::query()->forceCreate([
                    ...$payload,
                    'id' => $tenantId,
                ]);

                continue;
            }

            Workshop::query()->create($payload);
        }
    }

    private function upsertRole(string $name, ?string $tenantId): Role
    {
        $query = Role::query()
            ->where('name', $name)
            ->where('guard_name', 'web');

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        $role = $query->first();
        if ($role) {
            return $role;
        }

        return Role::query()->create([
            'name' => $name,
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function upsertPlanCatalog(): array
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'max_workshops' => 1,
                'max_users_per_ws' => 5,
                'has_ai_feature' => false,
                'has_notification' => true,
                'has_loyalty' => false,
                'has_trial' => true,
                'trial_duration_days' => 14,
                'is_active' => true,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'max_workshops' => 3,
                'max_users_per_ws' => 15,
                'has_ai_feature' => true,
                'has_notification' => true,
                'has_loyalty' => true,
                'has_trial' => true,
                'trial_duration_days' => 14,
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'max_workshops' => 10,
                'max_users_per_ws' => 50,
                'has_ai_feature' => true,
                'has_notification' => true,
                'has_loyalty' => true,
                'has_trial' => true,
                'trial_duration_days' => 7,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'max_workshops' => $plan['max_workshops'],
                    'max_users_per_ws' => $plan['max_users_per_ws'],
                    'has_ai_feature' => $plan['has_ai_feature'],
                    'has_notification' => $plan['has_notification'],
                    'has_loyalty' => $plan['has_loyalty'],
                    'has_trial' => $plan['has_trial'],
                    'trial_duration_days' => $plan['trial_duration_days'],
                    'is_active' => $plan['is_active'],
                ],
            );
        }

        $planIds = Plan::query()
            ->whereIn('slug', collect($plans)->pluck('slug')->all())
            ->pluck('id', 'slug');

        $planPrices = [
            [
                'key' => 'starter_monthly',
                'plan_slug' => 'starter',
                'label' => 'Starter - 1 Bulan',
                'duration_months' => 1,
                'price' => 99000,
                'discount_pct' => 0,
            ],
            [
                'key' => 'growth_monthly',
                'plan_slug' => 'growth',
                'label' => 'Growth - 1 Bulan',
                'duration_months' => 1,
                'price' => 249000,
                'discount_pct' => 0,
            ],
            [
                'key' => 'pro_monthly',
                'plan_slug' => 'pro',
                'label' => 'Pro - 1 Bulan',
                'duration_months' => 1,
                'price' => 599000,
                'discount_pct' => 0,
            ],
        ];

        foreach ($planPrices as $planPrice) {
            $planId = (int) ($planIds[$planPrice['plan_slug']] ?? 0);
            if ($planId === 0) {
                continue;
            }

            PlanPrice::query()->updateOrCreate(
                [
                    'plan_id' => $planId,
                    'duration_months' => $planPrice['duration_months'],
                ],
                [
                    'label' => $planPrice['label'],
                    'price' => $planPrice['price'],
                    'discount_pct' => $planPrice['discount_pct'],
                    'is_active' => true,
                ],
            );
        }

        $result = [];
        foreach ($planPrices as $planPrice) {
            $planId = (int) ($planIds[$planPrice['plan_slug']] ?? 0);
            if ($planId === 0) {
                continue;
            }

            $priceId = PlanPrice::query()
                ->where('plan_id', $planId)
                ->where('duration_months', $planPrice['duration_months'])
                ->value('id');

            if ($priceId !== null) {
                $result[$planPrice['key']] = (int) $priceId;
            }
        }

        return $result;
    }

    private function upsertWorkshopSubscription(string $tenantId, int $planPriceId, $now): void
    {
        $activeSubscription = WorkshopSubscription::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['trial', 'active'])
            ->orderByDesc('created_at')
            ->first();

        $payload = [
            'plan_price_id' => $planPriceId,
            'status' => 'active',
            'started_at' => $now->copy()->subDays(5),
            'expired_at' => $now->copy()->addDays(25),
            'trial_ends_at' => null,
            'updated_at' => $now,
        ];

        if ($activeSubscription) {
            $activeSubscription->forceFill($payload)->save();

            return;
        }

        WorkshopSubscription::query()->create([
            ...$payload,
            'tenant_id' => $tenantId,
        ]);
    }

    private function upsertMenuCatalog(string $tenantId, $now): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $systemMenus = [
            ['key' => 'dashboard', 'parent_key' => null, 'label' => 'Dashboard', 'route' => 'owner.dashboard', 'icon' => 'dashboard', 'sort_order' => 10],
            ['key' => 'workshops', 'parent_key' => null, 'label' => 'Bengkel', 'route' => 'owner.workshops.index', 'icon' => 'building', 'sort_order' => 15],
            ['key' => 'bookings', 'parent_key' => null, 'label' => 'Booking', 'route' => 'owner.bookings.index', 'icon' => 'calendar', 'sort_order' => 19],
            ['key' => 'orders', 'parent_key' => null, 'label' => 'Servis', 'route' => 'owner.orders.index', 'icon' => 'services', 'sort_order' => 20],
            ['key' => 'expenses', 'parent_key' => null, 'label' => 'Pengeluaran', 'route' => 'owner.expenses.index', 'icon' => 'finance', 'sort_order' => 25],
            ['key' => 'reports', 'parent_key' => null, 'label' => 'Laporan', 'route' => null, 'icon' => 'reports', 'sort_order' => 55],
            ['key' => 'reports.sales', 'parent_key' => 'reports', 'label' => 'Laporan Servis', 'route' => 'owner.reports.sales.index', 'icon' => 'services', 'sort_order' => 10],
            ['key' => 'reports.spareparts', 'parent_key' => 'reports', 'label' => 'Laporan Sparepart', 'route' => 'owner.reports.spareparts.index', 'icon' => 'products', 'sort_order' => 20],
            ['key' => 'reports.expenses', 'parent_key' => 'reports', 'label' => 'Laporan Pengeluaran', 'route' => 'owner.reports.expenses.index', 'icon' => 'finance', 'sort_order' => 30],
            ['key' => 'reports.customers', 'parent_key' => 'reports', 'label' => 'Laporan Pelanggan', 'route' => 'owner.reports.customers.index', 'icon' => 'customers', 'sort_order' => 40],
            ['key' => 'settings', 'parent_key' => null, 'label' => 'Pengaturan', 'route' => null, 'icon' => 'settings', 'sort_order' => 70],
            ['key' => 'settings.permission', 'parent_key' => 'settings', 'label' => 'Permission', 'route' => 'owner.settings?tab=permissions', 'icon' => 'settings', 'sort_order' => 71],
            ['key' => 'settings.nota', 'parent_key' => 'settings', 'label' => 'Nota', 'route' => 'owner.settings?tab=nota', 'icon' => 'printer', 'sort_order' => 72],
        ];

        $systemMenuIds = [];
        foreach ($systemMenus as $menu) {
            $parentId = null;
            if (is_string($menu['parent_key'])) {
                $parentId = $systemMenuIds[$menu['parent_key']] ?? null;
            }

            $existingMenu = Menu::query()
                ->whereNull('tenant_id')
                ->where('menu_type', 'system')
                ->where('label', $menu['label'])
                ->where('parent_id', $parentId)
                ->first();

            $payload = [
                'tenant_id' => null,
                'parent_id' => $parentId,
                'menu_type' => 'system',
                'label' => $menu['label'],
                'route' => $menu['route'],
                'icon' => $menu['icon'],
                'sort_order' => $menu['sort_order'],
                'is_active' => true,
                'updated_at' => $now,
            ];

            if ($existingMenu) {
                $existingMenu->forceFill($payload)->save();

                $systemMenuIds[$menu['key']] = (int) $existingMenu->id;

                continue;
            }

            $createdMenu = Menu::query()->create([
                ...$payload,
                'created_at' => $now,
            ]);

            $systemMenuIds[$menu['key']] = (int) $createdMenu->id;
        }

        Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('parent_id')
            ->whereNull('route')
            ->where('label', 'Data Master')
            ->update([
                'sort_order' => 17,
                'is_active' => true,
                'updated_at' => $now,
            ]);

        // Nonaktifkan menu root legacy yang sudah digantikan oleh submenu Data Master.
        Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->whereNull('parent_id')
            ->whereIn('route', ['owner.products.index', 'owner.users.index'])
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        if (Schema::hasTable('menu_permission') && Schema::hasTable('permissions')) {
            $permissionIdByName = Permission::query()->pluck('id', 'name');
            $menuPermissionMap = [
                'dashboard' => ['owner.dashboard.view'],
                'workshops' => ['users.manage'],
                'bookings' => ['bookings.view', 'bookings.manage'],
                'orders' => ['service_orders.view', 'service_orders.manage'],
                'expenses' => ['expenses.view', 'expenses.manage'],
                'reports.sales' => ['service_orders.view', 'service_orders.manage'],
                'reports.spareparts' => ['spareparts.view', 'spareparts.manage'],
                'reports.expenses' => ['expenses.view', 'expenses.manage'],
                'reports.customers' => ['customers.view', 'customers.manage'],
                'settings.permission' => ['users.manage'],
                'settings.nota' => ['users.manage'],
            ];

            $systemMenuIdList = collect($systemMenuIds)
                ->map(fn ($menuId): int => (int) $menuId)
                ->filter(fn (int $menuId): bool => $menuId > 0)
                ->values()
                ->all();

            if (count($systemMenuIdList) > 0) {
                MenuPermission::query()
                    ->whereIn('menu_id', $systemMenuIdList)
                    ->delete();
            }

            foreach ($menuPermissionMap as $menuKey => $permissionNames) {
                $menuId = (int) ($systemMenuIds[$menuKey] ?? 0);
                if ($menuId <= 0) {
                    continue;
                }

                foreach (collect($permissionNames)->unique()->values() as $permissionName) {
                    $permissionId = (int) ($permissionIdByName[$permissionName] ?? 0);
                    if ($permissionId <= 0) {
                        continue;
                    }

                    MenuPermission::query()->updateOrCreate(
                        [
                            'menu_id' => $menuId,
                            'permission_id' => $permissionId,
                        ],
                        [
                            'menu_id' => $menuId,
                            'permission_id' => $permissionId,
                        ],
                    );
                }
            }
        }

        $legacyTenantRootMenuIds = Menu::query()
            ->where('tenant_id', $tenantId)
            ->where('menu_type', 'tenant')
            ->where('label', 'Operasional Kustom')
            ->whereNull('parent_id')
            ->pluck('id')
            ->map(fn ($menuId): int => (int) $menuId)
            ->values()
            ->all();

        if (count($legacyTenantRootMenuIds) > 0) {
            Menu::query()
                ->where('tenant_id', $tenantId)
                ->where('menu_type', 'tenant')
                ->whereIn('parent_id', $legacyTenantRootMenuIds)
                ->delete();

            Menu::query()
                ->whereIn('id', $legacyTenantRootMenuIds)
                ->delete();
        }
        if (! Schema::hasTable('plan_menu') || ! Schema::hasTable('plans')) {
            return;
        }

        $planIds = Plan::query()->pluck('id', 'slug');
        $planMenuKeys = [
            'starter' => ['dashboard', 'workshops', 'bookings', 'orders', 'expenses', 'reports', 'reports.sales', 'reports.spareparts', 'reports.expenses', 'reports.customers', 'settings', 'settings.permission', 'settings.nota'],
            'growth' => ['dashboard', 'workshops', 'bookings', 'orders', 'expenses', 'reports', 'reports.sales', 'reports.spareparts', 'reports.expenses', 'reports.customers', 'settings', 'settings.permission', 'settings.nota'],
            'pro' => ['dashboard', 'workshops', 'bookings', 'orders', 'expenses', 'reports', 'reports.sales', 'reports.spareparts', 'reports.expenses', 'reports.customers', 'settings', 'settings.permission', 'settings.nota'],
        ];

        foreach ($planMenuKeys as $planSlug => $menuKeys) {
            $planId = (int) ($planIds[$planSlug] ?? 0);
            if ($planId === 0) {
                continue;
            }

            foreach ($menuKeys as $menuKey) {
                $menuId = (int) ($systemMenuIds[$menuKey] ?? 0);
                if ($menuId === 0) {
                    continue;
                }

                PlanMenu::query()->updateOrCreate(
                    ['plan_id' => $planId, 'menu_id' => $menuId],
                    ['plan_id' => $planId, 'menu_id' => $menuId],
                );
            }
        }
    }

    /**
     * @param  Collection<string, Permission>  $permissions
     * @param  array<int, string>  $permissionNames
     * @return array<int, Permission>
     */
    private function resolvePermissions(Collection $permissions, array $permissionNames): array
    {
        return collect($permissionNames)
            ->map(fn (string $permissionName) => $permissions->get($permissionName))
            ->filter(fn ($permission) => $permission instanceof Permission)
            ->values()
            ->all();
    }

    private function upsertUser(array $attributes): User
    {
        $tenantId = isset($attributes['tenant_id']) ? trim((string) $attributes['tenant_id']) : '';
        if ($tenantId !== '' && ! array_key_exists('workshop_id', $attributes)) {
            $attributes['workshop_id'] = $tenantId;
        }

        $user = User::query()->firstOrNew(['email' => $attributes['email']]);
        $user->fill($attributes);
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        return $user;
    }

    private function syncUserRole(User $user, Role $role): void
    {
        $user->syncRoles([$role]);
    }
}
