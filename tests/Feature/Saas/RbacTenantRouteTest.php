<?php

use App\Models\Menu;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\PlatformManualPaymentProvider;
use App\Models\PlatformPaymentMethod;
use App\Models\Plan;
use App\Models\PlanMenu;
use App\Models\PlanPrice;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEstimate;
use App\Models\Tenant;
use App\Models\TenantPlanSwitchPayment;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createActiveTenant(string $tenantId): void
{
    Tenant::query()->forceCreate([
        'id' => $tenantId,
        'name' => 'Tenant '.$tenantId,
        'code' => strtoupper(str_replace('_', '-', $tenantId)),
        'is_active' => true,
    ]);
}

function provisionOwnerDashboardAccess(string $tenantId): void
{
    $permission = Permission::findOrCreate('owner.dashboard.view', 'web');

    createActiveTenant($tenantId);

    $plan = Plan::query()->create([
        'name' => 'Plan '.$tenantId,
        'slug' => 'plan-'.$tenantId,
        'max_workshops' => 1,
        'max_users_per_ws' => 5,
        'has_ai_feature' => false,
        'has_notification' => true,
        'has_loyalty' => false,
        'has_trial' => true,
        'trial_duration_days' => 14,
        'is_active' => true,
    ]);

    $planPrice = PlanPrice::query()->create([
        'plan_id' => (int) $plan->id,
        'label' => 'Plan '.$tenantId.' - 1 Bulan',
        'duration_months' => 1,
        'price' => 99000,
        'discount_pct' => 0,
        'is_active' => true,
    ]);

    WorkshopSubscription::query()->create([
        'tenant_id' => $tenantId,
        'plan_price_id' => (int) $planPrice->id,
        'status' => 'active',
        'started_at' => now()->subDay(),
        'expired_at' => now()->addMonth(),
        'trial_ends_at' => null,
    ]);

    $menu = Menu::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'menu_type' => 'system',
        'label' => 'Dasbor',
        'route' => 'owner.dashboard',
        'icon' => 'dashboard',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    PlanMenu::query()->create([
        'plan_id' => (int) $plan->id,
        'menu_id' => (int) $menu->id,
    ]);

    $menu->permissions()->syncWithoutDetaching([(int) $permission->id]);
}

function provisionOwnerWorkshopsAccess(string $tenantId): void
{
    provisionOwnerDashboardAccess($tenantId);

    $planId = (int) Plan::query()
        ->where('slug', 'plan-'.$tenantId)
        ->value('id');

    $workshopMenu = Menu::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'menu_type' => 'system',
        'label' => 'Bengkel',
        'route' => 'owner.workshops.index',
        'icon' => 'building',
        'sort_order' => 15,
        'is_active' => true,
    ]);

    if ($planId > 0) {
        PlanMenu::query()->create([
            'plan_id' => $planId,
            'menu_id' => (int) $workshopMenu->id,
        ]);
    }

    $workshopMenu->permissions()->syncWithoutDetaching([
        (int) Permission::findOrCreate('users.manage', 'web')->id,
    ]);
}

function provisionOwnerCustomersAccess(string $tenantId): void
{
    provisionOwnerDashboardAccess($tenantId);

    $planId = (int) Plan::query()
        ->where('slug', 'plan-'.$tenantId)
        ->value('id');

    $customerMenu = Menu::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'menu_type' => 'system',
        'label' => 'Customer',
        'route' => 'owner.customers.index',
        'icon' => 'customers',
        'sort_order' => 16,
        'is_active' => true,
    ]);

    if ($planId > 0) {
        PlanMenu::query()->create([
            'plan_id' => $planId,
            'menu_id' => (int) $customerMenu->id,
        ]);
    }

    $customerMenu->permissions()->syncWithoutDetaching([
        (int) Permission::findOrCreate('customers.view', 'web')->id,
        (int) Permission::findOrCreate('customers.manage', 'web')->id,
    ]);
}

function provisionOwnerOrdersAccess(string $tenantId): void
{
    provisionOwnerDashboardAccess($tenantId);

    $planId = (int) Plan::query()
        ->where('slug', 'plan-'.$tenantId)
        ->value('id');

    $ordersMenu = Menu::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'menu_type' => 'system',
        'label' => 'Servis',
        'route' => 'owner.orders.index',
        'icon' => 'services',
        'sort_order' => 20,
        'is_active' => true,
    ]);

    if ($planId > 0) {
        PlanMenu::query()->create([
            'plan_id' => $planId,
            'menu_id' => (int) $ordersMenu->id,
        ]);
    }

    $ordersMenu->permissions()->syncWithoutDetaching([
        (int) Permission::findOrCreate('service_orders.view', 'web')->id,
        (int) Permission::findOrCreate('service_orders.manage', 'web')->id,
    ]);
}

function provisionOwnerBookingsAccess(string $tenantId): void
{
    provisionOwnerDashboardAccess($tenantId);

    $planId = (int) Plan::query()
        ->where('slug', 'plan-'.$tenantId)
        ->value('id');

    $bookingsMenu = Menu::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'menu_type' => 'system',
        'label' => 'Booking',
        'route' => 'owner.bookings.index',
        'icon' => 'calendar',
        'sort_order' => 23,
        'is_active' => true,
    ]);

    if ($planId > 0) {
        PlanMenu::query()->create([
            'plan_id' => $planId,
            'menu_id' => (int) $bookingsMenu->id,
        ]);
    }

    $bookingsMenu->permissions()->syncWithoutDetaching([
        (int) Permission::findOrCreate('bookings.view', 'web')->id,
        (int) Permission::findOrCreate('bookings.manage', 'web')->id,
    ]);
}

function provisionOwnerUsersAccess(string $tenantId): void
{
    provisionOwnerDashboardAccess($tenantId);

    $planId = (int) Plan::query()
        ->where('slug', 'plan-'.$tenantId)
        ->value('id');

    $userMenu = Menu::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'menu_type' => 'system',
        'label' => 'Tim',
        'route' => 'owner.users.index',
        'icon' => 'users',
        'sort_order' => 40,
        'is_active' => true,
    ]);

    if ($planId > 0) {
        PlanMenu::query()->create([
            'plan_id' => $planId,
            'menu_id' => (int) $userMenu->id,
        ]);
    }

    $userMenu->permissions()->syncWithoutDetaching([
        (int) Permission::findOrCreate('users.manage', 'web')->id,
    ]);

    Role::query()->firstOrCreate([
        'tenant_id' => $tenantId,
        'name' => 'admin',
        'guard_name' => 'web',
    ]);

    Role::query()->firstOrCreate([
        'tenant_id' => $tenantId,
        'name' => 'mekanik',
        'guard_name' => 'web',
    ]);
}

test('platform dashboard requires superadmin role', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/platform/dashboard')
        ->assertForbidden();
});

test('platform dashboard can be accessed by superadmin fallback flag', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.view', 'web'));

    $this->actingAs($user)
        ->get('/platform/dashboard')
        ->assertOk();
});

test('platform dashboard can be accessed by superadmin spatie role', function () {
    $user = User::factory()->create();
    $role = Role::query()->create([
        'name' => 'superadmin',
        'guard_name' => 'web',
        'tenant_id' => null,
    ]);
    $role->givePermissionTo(Permission::findOrCreate('platform.tenants.view', 'web'));

    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/platform/dashboard')
        ->assertOk();
});

test('owner dashboard requires matching tenant workshop id', function () {
    createActiveTenant('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_beta/dashboard')
        ->assertForbidden();
});

test('owner dashboard can be accessed when role and workshop match', function () {
    provisionOwnerDashboardAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/dashboard')
        ->assertOk();
});

test('owner dashboard can be accessed via tenant subdomain without tenant path', function () {
    config()->set('app.url', 'https://bengkel.test');

    provisionOwnerDashboardAccess('ws_alpha');

    Tenant::query()
        ->where('id', 'ws_alpha')
        ->update(['subdomain' => 'ws-alpha']);

    expect((string) Tenant::query()->where('id', 'ws_alpha')->value('subdomain'))->toBe('ws-alpha');
    expect((string) app(\App\Services\Tenant\TenantSubdomainService::class)->resolveTenantIdFromSubdomain('ws-alpha'))
        ->toBe('ws_alpha');

    $hostRequest = \Illuminate\Http\Request::create('/owner/dashboard', 'GET', [], [], [], [
        'HTTP_HOST' => 'ws-alpha.bengkel.test',
    ]);
    expect((string) app(\App\Services\Tenant\TenantSubdomainService::class)->resolveTenantIdFromRequestHost($hostRequest))
        ->toBe('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get('http://ws-alpha.bengkel.test/owner/dashboard')
        ->assertRedirect('/owner/ws_alpha/dashboard');

    $this->actingAs($user)
        ->get('http://ws-alpha.bengkel.test/owner/ws_alpha/dashboard')
        ->assertOk();
});

test('owner dashboard rejects tenant path that mismatches current subdomain', function () {
    config()->set('app.url', 'https://bengkel.test');

    provisionOwnerDashboardAccess('ws_alpha');
    provisionOwnerDashboardAccess('ws_beta');

    Tenant::query()
        ->where('id', 'ws_alpha')
        ->update(['subdomain' => 'ws-alpha']);

    $user = User::factory()->create([
        'tenant_id' => 'ws_beta',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get('http://ws-alpha.bengkel.test/owner/ws_beta/dashboard')
        ->assertNotFound();
});

test('owner dashboard rejects unknown tenant subdomain even when tenant path is provided', function () {
    config()->set('app.url', 'https://bengkel.test');

    provisionOwnerDashboardAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get('http://ws-unknown.bengkel.test/owner/ws_alpha/dashboard')
        ->assertNotFound();
});

test('owner dashboard can be accessed by owner spatie role with matching workshop', function () {
    provisionOwnerDashboardAccess('ws_alpha');

    $workshop = new Workshop([
        'tenant_id' => 'ws_alpha',
        'name' => 'Workshop Alpha',
        'code' => 'WS-ALPHA',
        'is_active' => true,
    ]);
    $workshop->id = 'ws_alpha';
    $workshop->save();

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);

    $role = Role::query()->create([
        'name' => 'owner',
        'guard_name' => 'web',
        'tenant_id' => 'ws_alpha',
    ]);
    $role->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/owner/ws_alpha/dashboard')
        ->assertOk();
});

test('owner dashboard is forbidden for platform scope users even when tenant matches', function () {
    provisionOwnerDashboardAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);

    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/dashboard')
        ->assertForbidden();
});

test('owner dashboard forbidden when tenant is inactive', function () {
    provisionOwnerDashboardAccess('ws_alpha');

    Tenant::query()
        ->where('id', 'ws_alpha')
        ->update(['is_active' => false]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/dashboard')
        ->assertForbidden();
});

test('owner dashboard forbidden when active subscription is expired', function () {
    provisionOwnerDashboardAccess('ws_alpha');

    WorkshopSubscription::query()
        ->where('tenant_id', 'ws_alpha')
        ->update([
            'status' => 'active',
            'expired_at' => now()->subDay(),
        ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/dashboard')
        ->assertForbidden();
});

test('owner dashboard forbidden when trial subscription is expired', function () {
    provisionOwnerDashboardAccess('ws_alpha');

    WorkshopSubscription::query()
        ->where('tenant_id', 'ws_alpha')
        ->update([
            'status' => 'trial',
            'expired_at' => null,
            'trial_ends_at' => now()->subDay(),
        ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/dashboard')
        ->assertForbidden();
});

test('owner dashboard forbidden when plan does not include owner dashboard menu', function () {
    provisionOwnerDashboardAccess('ws_alpha');

    $planId = (int) Plan::query()
        ->where('slug', 'plan-ws_alpha')
        ->value('id');

    PlanMenu::query()
        ->where('plan_id', $planId)
        ->delete();

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/dashboard')
        ->assertForbidden();
});

test('owner workshops can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/workshops')
        ->assertOk();
});

test('owner customers can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerCustomersAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('customers.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/customers')
        ->assertOk();
});

test('owner customers store can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerCustomersAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('customers.manage', 'web'));

    $this->actingAs($user)
        ->post('/owner/ws_alpha/customers', [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka No. 1',
            'notes' => 'Customer prioritas',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', [
        'tenant_id' => 'ws_alpha',
        'name' => 'Budi Santoso',
        'phone' => '081234567890',
        'email' => 'budi@example.com',
        'is_active' => true,
    ]);
});

test('owner customers update can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerCustomersAccess('ws_alpha');

    $customer = Customer::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Pelanggan Lama',
        'phone' => '081200000001',
        'email' => 'lama@example.com',
        'address' => 'Alamat Lama',
        'notes' => 'Catatan Lama',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('customers.manage', 'web'));

    $this->actingAs($user)
        ->patch('/owner/ws_alpha/customers/'.(string) $customer->id, [
            'name' => 'Pelanggan Baru',
            'phone' => '081200000009',
            'email' => 'baru@example.com',
            'address' => 'Alamat Baru',
            'notes' => 'Catatan Baru',
            'is_active' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', [
        'id' => (string) $customer->id,
        'tenant_id' => 'ws_alpha',
        'name' => 'Pelanggan Baru',
        'phone' => '081200000009',
        'email' => 'baru@example.com',
        'is_active' => false,
    ]);
});

test('owner customers delete can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerCustomersAccess('ws_alpha');

    $customer = Customer::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Akan Dihapus',
        'phone' => '081255555555',
        'email' => 'hapus@example.com',
        'address' => null,
        'notes' => null,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('customers.manage', 'web'));

    $this->actingAs($user)
        ->delete('/owner/ws_alpha/customers/'.(string) $customer->id)
        ->assertRedirect();

    $this->assertSoftDeleted('customers', [
        'id' => (string) $customer->id,
        'tenant_id' => 'ws_alpha',
    ]);
});

test('owner orders can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerOrdersAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('service_orders.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/orders')
        ->assertOk();
});

test('owner bookings can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerBookingsAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('bookings.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/bookings')
        ->assertOk();
});

test('owner orders store creates customer vehicle and service order', function () {
    provisionOwnerOrdersAccess('ws_alpha');

    $actor = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $actor->givePermissionTo(Permission::findOrCreate('service_orders.manage', 'web'));

    $this->actingAs($actor)
        ->post('/owner/ws_alpha/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'customer_email' => '',
            'customer_address' => '',
            'vehicle_type' => 'mobil',
            'vehicle_brand' => 'Honda',
            'vehicle_model' => 'Beat',
            'vehicle_plate_number' => 'B 1234 CD',
            'vehicle_year' => 2023,
            'service_date' => now()->toDateString(),
            'vehicle_condition' => 'Mesin normal, rem depan aus, ban belakang menipis',
            'estimated_days' => 2,
            'complaint' => 'Mesin kurang bertenaga',
            'odometer' => 12000,
        ])
        ->assertRedirect();

    $customer = Customer::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('name', 'Budi Santoso')
        ->first();

    expect($customer)->not->toBeNull();
    expect((string) ($customer?->address ?? ''))->toBe('');

    $vehicle = CustomerVehicle::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('customer_id', (string) $customer?->id)
        ->where('vehicle_type', 'mobil')
        ->where('brand', 'Honda')
        ->where('model', 'Beat')
        ->first();

    expect($vehicle)->not->toBeNull();
    expect((string) ($vehicle?->vehicle_type ?? ''))->toBe('mobil');
    expect((string) ($vehicle?->plate_number ?? ''))->toBe('B1234CD');

    $serviceOrder = ServiceOrder::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('customer_id', (string) $customer?->id)
        ->where('customer_vehicle_id', (string) $vehicle?->id)
        ->latest('created_at')
        ->first();

    expect($serviceOrder)->not->toBeNull();
    expect((string) ($serviceOrder?->status ?? ''))->toBe('open');
    expect((int) ($serviceOrder?->estimated_days ?? 0))->toBe(2);
    expect((string) ($serviceOrder?->vehicle_condition ?? ''))->toContain('Mesin normal');
    expect((string) ($serviceOrder?->complaint ?? ''))->toBe('Mesin kurang bertenaga');
});

test('owner orders estimates store route can be accessed with valid menu and permission', function () {
    provisionOwnerOrdersAccess('ws_alpha');

    $actor = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $actor->givePermissionTo(Permission::findOrCreate('service_orders.manage', 'web'));

    $this->actingAs($actor)
        ->post('/owner/ws_alpha/orders/non-existing-order/estimates', [
            'approval_expires_at' => now()->addDay()->toDateString(),
            'internal_note' => 'Estimasi awal',
            'submit_for_approval' => true,
            'items' => [
                [
                    'item_type' => 'service',
                    'label' => 'Jasa Tune Up',
                    'description' => 'Pemeriksaan dan penyesuaian standar',
                    'unit_label' => '',
                    'qty' => 1,
                    'unit_price' => 150000,
                    'spare_part_id' => null,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['create_estimate']);
});

test('owner orders estimates cannot be recreated after approved', function () {
    provisionOwnerOrdersAccess('ws_alpha');

    $actor = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $actor->givePermissionTo(Permission::findOrCreate('service_orders.manage', 'web'));

    $this->actingAs($actor)
        ->post('/owner/ws_alpha/orders', [
            'customer_name' => 'Pelanggan Lock',
            'customer_phone' => '081234560001',
            'customer_email' => '',
            'customer_address' => '',
            'vehicle_type' => 'mobil',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Avanza',
            'vehicle_plate_number' => 'B 1234 LK',
            'vehicle_year' => 2022,
            'service_date' => now()->toDateString(),
            'vehicle_condition' => 'Kondisi awal',
            'estimated_days' => 2,
            'complaint' => 'Mesin bergetar',
            'odometer' => 23000,
        ])
        ->assertRedirect();

    $serviceOrder = ServiceOrder::query()
        ->where('tenant_id', 'ws_alpha')
        ->latest('created_at')
        ->first();

    expect($serviceOrder)->not->toBeNull();

    ServiceOrderEstimate::query()->create([
        'tenant_id' => 'ws_alpha',
        'service_order_id' => (string) $serviceOrder?->id,
        'code' => 'EST-LOCK-'.substr((string) $serviceOrder?->id, -8),
        'revision' => 1,
        'status' => 'approved',
        'customer_name' => 'Pelanggan Lock',
        'subtotal_service' => 200000,
        'subtotal_sparepart' => 0,
        'total_amount' => 200000,
        'approved_at' => now(),
        'requested_by_user_id' => (string) $actor->id,
    ]);

    $this->actingAs($actor)
        ->post('/owner/ws_alpha/orders/'.(string) $serviceOrder?->id.'/estimates', [
            'approval_expires_at' => now()->addDay()->toDateString(),
            'internal_note' => 'Tidak boleh masuk',
            'submit_for_approval' => true,
            'items' => [
                [
                    'item_type' => 'service',
                    'label' => 'Jasa Baru',
                    'description' => '',
                    'unit_label' => '',
                    'qty' => 1,
                    'unit_price' => 120000,
                    'spare_part_id' => null,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['estimate']);

    $this->assertEquals(
        1,
        ServiceOrderEstimate::query()
            ->where('tenant_id', 'ws_alpha')
            ->where('service_order_id', (string) $serviceOrder?->id)
            ->count(),
    );
});

test('owner users can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerUsersAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/users')
        ->assertOk();
});

test('owner users store can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerUsersAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->post('/owner/ws_alpha/users', [
            'name' => 'Admin Baru',
            'email' => 'admin.baru@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'tenant_id' => 'ws_alpha',
        'name' => 'Admin Baru',
        'email' => 'admin.baru@example.com',
        'role' => 'admin',
        'user_type' => 'admin',
        'is_owner' => false,
        'is_superadmin' => false,
    ]);
});

test('owner users update can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerUsersAccess('ws_alpha');

    $adminRole = Role::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('name', 'admin')
        ->first();

    $mechanicRole = Role::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('name', 'mekanik')
        ->first();

    expect($adminRole)->not->toBeNull();
    expect($mechanicRole)->not->toBeNull();

    $managedUser = User::factory()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'User Lama',
        'email' => 'user.lama@example.com',
        'role' => 'admin',
        'user_type' => 'admin',
        'is_owner' => false,
        'is_superadmin' => false,
    ]);
    $managedUser->syncRoles([$adminRole]);

    $actor = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $actor->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($actor)
        ->patch('/owner/ws_alpha/users/'.(string) $managedUser->id, [
            'name' => 'User Baru',
            'email' => 'user.baru@example.com',
            'password' => 'password123',
            'role' => 'mekanik',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'id' => (string) $managedUser->id,
        'tenant_id' => 'ws_alpha',
        'name' => 'User Baru',
        'email' => 'user.baru@example.com',
        'role' => 'mekanik',
        'user_type' => 'mekanik',
    ]);
});

test('owner users delete can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerUsersAccess('ws_alpha');

    $adminRole = Role::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('name', 'admin')
        ->first();

    expect($adminRole)->not->toBeNull();

    $managedUser = User::factory()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'User Hapus',
        'email' => 'user.hapus@example.com',
        'role' => 'admin',
        'user_type' => 'admin',
        'is_owner' => false,
        'is_superadmin' => false,
    ]);
    $managedUser->syncRoles([$adminRole]);

    $actor = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $actor->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($actor)
        ->delete('/owner/ws_alpha/users/'.(string) $managedUser->id)
        ->assertRedirect();

    $this->assertDatabaseMissing('users', [
        'id' => (string) $managedUser->id,
        'tenant_id' => 'ws_alpha',
    ]);
});

test('owner workshops forbidden when users manage permission is missing', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/workshops')
        ->assertForbidden();
});

test('owner workshops store can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->post('/owner/ws_alpha/workshops', [
            'name' => 'Workshop Baru',
            'code' => 'WS-BARU',
            'phone' => '081234567800',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('workshops', [
        'tenant_id' => 'ws_alpha',
        'name' => 'Workshop Baru',
        'code' => 'WS-BARU',
        'phone' => '081234567800',
        'is_active' => true,
    ]);
});

test('owner workshops store requires workshop phone', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->post('/owner/ws_alpha/workshops', [
            'name' => 'Workshop Tanpa Phone',
            'code' => 'WS-NOPHONE',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('phone');
});

test('owner workshops store rejects duplicate workshop name in same tenant', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    Workshop::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Workshop Duplikat',
        'code' => 'WS-DUP-1',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->post('/owner/ws_alpha/workshops', [
            'name' => 'Workshop Duplikat',
            'code' => 'WS-DUP-2',
            'phone' => '081234567801',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('name');

    expect(Workshop::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('name', 'Workshop Duplikat')
        ->count())->toBe(1);
});

test('owner workshops update can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    $workshop = Workshop::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Workshop Lama',
        'code' => 'WS-LAMA',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->patch('/owner/ws_alpha/workshops/'.(string) $workshop->id, [
            'name' => 'Workshop Update',
            'code' => 'WS-UPD',
            'phone' => '081234567802',
            'is_active' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('workshops', [
        'id' => (string) $workshop->id,
        'tenant_id' => 'ws_alpha',
        'name' => 'Workshop Update',
        'code' => 'WS-UPD',
        'phone' => '081234567802',
        'is_active' => false,
    ]);
});

test('owner workshops update rejects duplicate workshop name in same tenant', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    Workshop::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Workshop A',
        'code' => 'WS-AA',
        'is_active' => true,
    ]);

    $secondWorkshop = Workshop::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Workshop B',
        'code' => 'WS-BB',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->patch('/owner/ws_alpha/workshops/'.(string) $secondWorkshop->id, [
            'name' => 'Workshop A',
            'code' => 'WS-BB',
            'phone' => '081234567803',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('name');

    $secondWorkshop->refresh();
    expect((string) $secondWorkshop->name)->toBe('Workshop B');
});

test('owner workshops delete can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    Workshop::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Workshop A',
        'code' => 'WS-A',
        'is_active' => true,
    ]);

    $deletableWorkshop = Workshop::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Workshop B',
        'code' => 'WS-B',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->delete('/owner/ws_alpha/workshops/'.(string) $deletableWorkshop->id)
        ->assertRedirect();

    $this->assertDatabaseMissing('workshops', [
        'id' => (string) $deletableWorkshop->id,
        'tenant_id' => 'ws_alpha',
    ]);
});

test('owner workshops store auto generates unique code when code is not provided', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->post('/owner/ws_alpha/workshops', [
            'name' => 'Bengkel Otomatis Alpha',
            'phone' => '081234567804',
            'is_active' => true,
        ])
        ->assertRedirect();

    $createdWorkshop = Workshop::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('name', 'Bengkel Otomatis Alpha')
        ->first();

    expect($createdWorkshop)->not->toBeNull();
    expect((string) $createdWorkshop?->code)->not->toBe('');
});

test('owner workshops update resolves duplicate requested code automatically', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    Workshop::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Bengkel A',
        'code' => 'BENGKEL-A',
        'is_active' => true,
    ]);

    $secondWorkshop = Workshop::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Bengkel B',
        'code' => 'BENGKEL-B',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->patch('/owner/ws_alpha/workshops/'.(string) $secondWorkshop->id, [
            'name' => 'Bengkel B',
            'code' => 'BENGKEL-A',
            'phone' => '081234567805',
            'is_active' => true,
        ])
        ->assertRedirect();

    $secondWorkshop->refresh();

    expect((string) $secondWorkshop->code)->not->toBe('BENGKEL-A');
    expect((string) $secondWorkshop->code)->not->toBe('');
});

test('owner workshops store resolves duplicate requested code automatically', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    Plan::query()->where('slug', 'plan-ws_alpha')->update(['max_workshops' => 2]);

    Workshop::query()->create([
        'tenant_id' => 'ws_alpha',
        'name' => 'Workshop Existing',
        'code' => 'AUTO-SER',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->post('/owner/ws_alpha/workshops', [
            'name' => 'Workshop Baru',
            'code' => 'AUTO-SER',
            'phone' => '081234567806',
            'is_active' => true,
        ])
        ->assertRedirect();

    $createdWorkshop = Workshop::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('name', 'Workshop Baru')
        ->first();

    expect($createdWorkshop)->not->toBeNull();
    expect((string) $createdWorkshop?->code)->not->toBe('AUTO-SER');
    expect((string) $createdWorkshop?->code)->not->toBe('');
});

test('owner workshops switch plan can be accessed when menu, plan, and permission are valid', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    Plan::query()->where('slug', 'plan-ws_alpha')->update(['max_workshops' => 5]);
    $currentPlanPriceId = (int) PlanPrice::query()
        ->whereHas('plan', fn ($query) => $query->where('slug', 'plan-ws_alpha'))
        ->value('id');

    $targetPlan = Plan::query()->create([
        'name' => 'Plan Upgrade Alpha',
        'slug' => 'plan-upgrade-ws-alpha',
        'max_workshops' => 15,
        'max_users_per_ws' => 20,
        'has_ai_feature' => true,
        'has_notification' => true,
        'has_loyalty' => true,
        'has_trial' => false,
        'trial_duration_days' => 0,
        'is_active' => true,
    ]);

    $targetPrice = PlanPrice::query()->create([
        'plan_id' => (int) $targetPlan->id,
        'label' => 'Plan Upgrade Alpha - 1 Bulan',
        'duration_months' => 1,
        'price' => 299000,
        'discount_pct' => 0,
        'is_active' => true,
    ]);

    $manualMethod = PlatformPaymentMethod::query()->create([
        'code' => 'manual',
        'label' => 'Manual Transfer',
        'is_enabled' => true,
        'sort_order' => 20,
    ]);

    $manualProvider = PlatformManualPaymentProvider::query()->create([
        'payment_method_id' => (int) $manualMethod->id,
        'provider_name' => 'BCA',
        'account_name' => 'PT AutoServ Nusantara',
        'account_number' => '1234567890',
        'notes' => 'Kirim bukti transfer.',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->post('/owner/ws_alpha/workshops/switch-plan', [
            'plan_price_id' => (int) $targetPrice->id,
            'payment_method' => 'manual',
            'manual_provider_id' => (int) $manualProvider->id,
        ])
        ->assertRedirect();

    $pendingPayment = TenantPlanSwitchPayment::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('target_plan_price_id', (int) $targetPrice->id)
        ->where('status', 'pending')
        ->latest('created_at')
        ->first();

    expect($pendingPayment)->not->toBeNull();
    expect((string) ($pendingPayment?->payment_method ?? ''))->toBe('manual');
    expect((int) ($pendingPayment?->manual_provider_id ?? 0))->toBe((int) $manualProvider->id);
    expect((float) ($pendingPayment?->amount ?? 0))->toBe(299000.0);

    $latestActiveSubscription = WorkshopSubscription::query()
        ->where('tenant_id', 'ws_alpha')
        ->whereIn('status', ['trial', 'active'])
        ->orderByDesc('created_at')
        ->first();

    expect($latestActiveSubscription)->not->toBeNull();
    expect((int) ($latestActiveSubscription?->plan_price_id ?? 0))->toBe($currentPlanPriceId);
});

test('owner workshops switch plan midtrans creates checkout redirect without changing active subscription', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    Plan::query()->where('slug', 'plan-ws_alpha')->update(['max_workshops' => 5]);
    $currentPlanPriceId = (int) PlanPrice::query()
        ->whereHas('plan', fn ($query) => $query->where('slug', 'plan-ws_alpha'))
        ->value('id');

    $targetPlan = Plan::query()->create([
        'name' => 'Plan Midtrans Alpha',
        'slug' => 'plan-midtrans-ws-alpha',
        'max_workshops' => 20,
        'max_users_per_ws' => 25,
        'has_ai_feature' => true,
        'has_notification' => true,
        'has_loyalty' => true,
        'has_trial' => false,
        'trial_duration_days' => 0,
        'is_active' => true,
    ]);

    $targetPrice = PlanPrice::query()->create([
        'plan_id' => (int) $targetPlan->id,
        'label' => 'Plan Midtrans Alpha - 1 Bulan',
        'duration_months' => 1,
        'price' => 399000,
        'discount_pct' => 0,
        'is_active' => true,
    ]);

    PlatformPaymentMethod::query()->create([
        'code' => 'midtrans',
        'label' => 'Midtrans',
        'is_enabled' => true,
        'midtrans_environment' => 'sandbox',
        'midtrans_merchant_id' => 'G123456789',
        'midtrans_server_key' => 'SB-MID-SERVER-KEY',
        'midtrans_client_key' => 'SB-MID-CLIENT-KEY',
        'sort_order' => 10,
    ]);

    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token-alpha',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/ORDER-ALPHA',
        ], 201),
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->post('/owner/ws_alpha/workshops/switch-plan', [
            'plan_price_id' => (int) $targetPrice->id,
            'payment_method' => 'midtrans',
        ])
        ->assertRedirect()
        ->assertSessionHas('payment_snap_token', 'snap-token-alpha');

    $pendingPayment = TenantPlanSwitchPayment::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('target_plan_price_id', (int) $targetPrice->id)
        ->where('status', 'pending')
        ->latest('created_at')
        ->first();

    expect($pendingPayment)->not->toBeNull();
    expect((string) ($pendingPayment?->payment_method ?? ''))->toBe('midtrans');
    expect((string) ($pendingPayment?->payment_gateway ?? ''))->toBe('midtrans');
    expect((string) ($pendingPayment?->payment_gateway_reference ?? ''))->not->toBe('');
    expect((string) ($pendingPayment?->payment_url ?? ''))->toBe('https://app.sandbox.midtrans.com/snap/v2/vtweb/ORDER-ALPHA');
    expect((float) ($pendingPayment?->amount ?? 0))->toBe(399000.0);

    $latestActiveSubscription = WorkshopSubscription::query()
        ->where('tenant_id', 'ws_alpha')
        ->whereIn('status', ['trial', 'active'])
        ->orderByDesc('created_at')
        ->first();

    expect($latestActiveSubscription)->not->toBeNull();
    expect((int) ($latestActiveSubscription?->plan_price_id ?? 0))->toBe($currentPlanPriceId);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request->hasHeader('X-Override-Notification')
            && trim((string) data_get($request->data(), 'transaction_details.order_id', '')) !== '';
    });
});

test('midtrans notification settlement auto upgrades tenant plan', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    Plan::query()->where('slug', 'plan-ws_alpha')->update(['max_workshops' => 5]);

    $targetPlan = Plan::query()->create([
        'name' => 'Plan Midtrans Upgrade',
        'slug' => 'plan-midtrans-upgrade-ws-alpha',
        'max_workshops' => 30,
        'max_users_per_ws' => 50,
        'has_ai_feature' => true,
        'has_notification' => true,
        'has_loyalty' => true,
        'has_trial' => false,
        'trial_duration_days' => 0,
        'is_active' => true,
    ]);

    $targetPrice = PlanPrice::query()->create([
        'plan_id' => (int) $targetPlan->id,
        'label' => 'Plan Midtrans Upgrade - 1 Bulan',
        'duration_months' => 1,
        'price' => 299000,
        'discount_pct' => 0,
        'is_active' => true,
    ]);

    PlatformPaymentMethod::query()->create([
        'code' => 'midtrans',
        'label' => 'Midtrans',
        'is_enabled' => true,
        'midtrans_environment' => 'sandbox',
        'midtrans_merchant_id' => 'G123456789',
        'midtrans_server_key' => 'SB-MID-SERVER-KEY',
        'midtrans_client_key' => 'SB-MID-CLIENT-KEY',
        'sort_order' => 10,
    ]);

    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token-upgrade',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/ORDER-UPGRADE',
        ], 201),
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $currentActiveSubscription = WorkshopSubscription::query()
        ->where('tenant_id', 'ws_alpha')
        ->whereIn('status', ['trial', 'active'])
        ->orderByDesc('started_at')
        ->orderByDesc('created_at')
        ->first();

    expect($currentActiveSubscription)->not->toBeNull();

    $previousSubscriptionId = (string) ($currentActiveSubscription?->id ?? '');
    $previousExpiredAt = $currentActiveSubscription?->expired_at?->copy();
    expect($previousExpiredAt)->not->toBeNull();

    $this->actingAs($user)
        ->post('/owner/ws_alpha/workshops/switch-plan', [
            'plan_price_id' => (int) $targetPrice->id,
            'payment_method' => 'midtrans',
        ])
        ->assertRedirect();

    $pendingPayment = TenantPlanSwitchPayment::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('target_plan_price_id', (int) $targetPrice->id)
        ->where('payment_method', 'midtrans')
        ->latest('created_at')
        ->first();

    expect($pendingPayment)->not->toBeNull();

    $orderId = (string) ($pendingPayment?->payment_gateway_reference ?? '');
    $statusCode = '200';
    $grossAmount = '299000.00';
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.'SB-MID-SERVER-KEY');

    $this->postJson('/webhooks/midtrans/notification', [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'settlement',
        'transaction_id' => 'TX-MID-123',
        'payment_type' => 'bank_transfer',
    ])->assertOk();

    $pendingPayment->refresh();
    expect((string) $pendingPayment->status)->toBe('paid');
    expect($pendingPayment->paid_at)->not->toBeNull();

    $latestActiveSubscription = WorkshopSubscription::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('status', 'active')
        ->orderByDesc('created_at')
        ->first();

    expect($latestActiveSubscription)->not->toBeNull();
    expect((string) ($latestActiveSubscription?->id ?? ''))->toBe($previousSubscriptionId);
    expect((int) ($latestActiveSubscription?->plan_price_id ?? 0))->toBe((int) $targetPrice->id);
    expect($latestActiveSubscription?->expired_at?->toDateTimeString())
        ->toBe($previousExpiredAt?->copy()->addMonthsNoOverflow(1)->toDateTimeString());
});

test('midtrans notification settlement extends same plan subscription end date without changing plan', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    PlatformPaymentMethod::query()->create([
        'code' => 'midtrans',
        'label' => 'Midtrans',
        'is_enabled' => true,
        'midtrans_environment' => 'sandbox',
        'midtrans_merchant_id' => 'G123456789',
        'midtrans_server_key' => 'SB-MID-SERVER-KEY',
        'midtrans_client_key' => 'SB-MID-CLIENT-KEY',
        'sort_order' => 10,
    ]);

    $currentSubscription = WorkshopSubscription::query()
        ->where('tenant_id', 'ws_alpha')
        ->whereIn('status', ['trial', 'active'])
        ->orderByDesc('started_at')
        ->orderByDesc('created_at')
        ->first();

    expect($currentSubscription)->not->toBeNull();

    $planPriceId = (int) ($currentSubscription?->plan_price_id ?? 0);
    $previousSubscriptionId = (string) ($currentSubscription?->id ?? '');
    $previousExpiredAt = $currentSubscription?->expired_at?->copy();
    expect($planPriceId)->toBeGreaterThan(0);
    expect($previousExpiredAt)->not->toBeNull();

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $orderId = 'ORDER-SAME-PLAN-001';
    $pendingPayment = TenantPlanSwitchPayment::query()->create([
        'tenant_id' => 'ws_alpha',
        'requested_by_user_id' => (string) $user->id,
        'current_plan_price_id' => $planPriceId,
        'target_plan_price_id' => $planPriceId,
        'payment_method' => 'midtrans',
        'status' => 'pending',
        'amount' => 99000,
        'currency' => 'IDR',
        'payment_gateway' => 'midtrans',
        'payment_gateway_reference' => $orderId,
        'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/ORDER-SAME-PLAN-001',
        'manual_provider_id' => null,
        'manual_provider_name' => null,
        'manual_account_name' => null,
        'manual_account_number' => null,
        'notes' => null,
        'paid_at' => null,
        'expires_at' => now()->addDay(),
    ]);

    $statusCode = '200';
    $grossAmount = '99000.00';
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.'SB-MID-SERVER-KEY');

    $this->postJson('/webhooks/midtrans/notification', [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'settlement',
        'transaction_id' => 'TX-SAME-PLAN-001',
        'payment_type' => 'bank_transfer',
    ])->assertOk();

    $pendingPayment->refresh();
    expect((string) $pendingPayment->status)->toBe('paid');

    $latestActiveSubscription = WorkshopSubscription::query()
        ->where('tenant_id', 'ws_alpha')
        ->where('status', 'active')
        ->orderByDesc('created_at')
        ->first();

    expect($latestActiveSubscription)->not->toBeNull();
    expect((string) ($latestActiveSubscription?->id ?? ''))->toBe($previousSubscriptionId);
    expect((int) ($latestActiveSubscription?->plan_price_id ?? 0))->toBe($planPriceId);
    expect($latestActiveSubscription?->expired_at?->toDateTimeString())
        ->toBe($previousExpiredAt?->copy()->addMonthsNoOverflow(1)->toDateTimeString());
});

test('owner workshops switch plan midtrans can continue existing pending payment safely', function () {
    provisionOwnerWorkshopsAccess('ws_alpha');

    Plan::query()->where('slug', 'plan-ws_alpha')->update(['max_workshops' => 5]);
    $currentPlanPriceId = (int) PlanPrice::query()
        ->whereHas('plan', fn ($query) => $query->where('slug', 'plan-ws_alpha'))
        ->value('id');

    $targetPlan = Plan::query()->create([
        'name' => 'Plan Midtrans Existing Pending',
        'slug' => 'plan-midtrans-existing-pending-ws-alpha',
        'max_workshops' => 25,
        'max_users_per_ws' => 35,
        'has_ai_feature' => true,
        'has_notification' => true,
        'has_loyalty' => true,
        'has_trial' => false,
        'trial_duration_days' => 0,
        'is_active' => true,
    ]);

    $targetPrice = PlanPrice::query()->create([
        'plan_id' => (int) $targetPlan->id,
        'label' => 'Plan Existing Pending - 1 Bulan',
        'duration_months' => 1,
        'price' => 249000,
        'discount_pct' => 0,
        'is_active' => true,
    ]);

    PlatformPaymentMethod::query()->create([
        'code' => 'midtrans',
        'label' => 'Midtrans',
        'is_enabled' => true,
        'midtrans_environment' => 'sandbox',
        'midtrans_merchant_id' => 'G123456789',
        'midtrans_server_key' => 'SB-MID-SERVER-KEY',
        'midtrans_client_key' => 'SB-MID-CLIENT-KEY',
        'sort_order' => 10,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $existingPending = TenantPlanSwitchPayment::query()->create([
        'tenant_id' => 'ws_alpha',
        'requested_by_user_id' => (string) $user->id,
        'current_plan_price_id' => $currentPlanPriceId,
        'target_plan_price_id' => (int) $targetPrice->id,
        'payment_method' => 'midtrans',
        'status' => 'pending',
        'amount' => 249000,
        'currency' => 'IDR',
        'payment_gateway' => 'midtrans',
        'payment_gateway_reference' => null,
        'payment_url' => null,
        'manual_provider_id' => null,
        'manual_provider_name' => null,
        'manual_account_name' => null,
        'manual_account_number' => null,
        'notes' => null,
        'paid_at' => null,
        'expires_at' => now()->addDay(),
    ]);

    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token-existing',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/ORDER-EXISTING',
        ], 201),
    ]);

    $this->actingAs($user)
        ->post('/owner/ws_alpha/workshops/switch-plan', [
            'plan_price_id' => (int) $targetPrice->id,
            'payment_method' => 'midtrans',
        ])
        ->assertRedirect();

    $existingPending->refresh();
    expect((string) $existingPending->status)->toBe('pending');
    expect((string) $existingPending->payment_gateway_reference)->not->toBe('');
    expect((string) $existingPending->payment_url)->toBe('https://app.sandbox.midtrans.com/snap/v2/vtweb/ORDER-EXISTING');

    Http::assertSentCount(1);
});
