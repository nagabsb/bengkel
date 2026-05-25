<?php

use App\Models\Menu;
use App\Models\Plan;
use App\Models\PlanMenu;
use App\Models\PlanPrice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function provisionOwnerDashboardAccessForLoginTest(string $tenantId): void
{
    Tenant::query()->forceCreate([
        'id' => $tenantId,
        'name' => 'Tenant '.$tenantId,
        'code' => strtoupper(substr(str_replace('-', '', $tenantId), 0, 12)),
        'is_active' => true,
    ]);

    $plan = Plan::query()->create([
        'name' => 'Plan '.$tenantId,
        'slug' => 'plan-login-'.strtolower(substr(str_replace('-', '', $tenantId), 0, 12)),
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

    $menu->permissions()->syncWithoutDetaching([
        (int) Permission::findOrCreate('owner.dashboard.view', 'web')->id,
    ]);
}

test('user can login with valid credentials', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('user cannot login with invalid credentials', function () {
    $user = User::factory()->create();

    $response = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'password-salah',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('login form validation message is displayed in indonesian', function () {
    $response = $this->from('/login')->post('/login', [
        'email' => '',
        'password' => '',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors([
        'email' => 'Alamat email wajib diisi.',
        'password' => 'Kata sandi wajib diisi.',
    ]);
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('superadmin user is redirected to platform dashboard after login', function () {
    $user = User::factory()->create([
        'role' => 'superadmin',
        'user_type' => 'superadmin',
        'is_superadmin' => true,
    ]);
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.view', 'web'));

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/platform/dashboard');
});

test('superadmin user cannot login from tenant subdomain', function () {
    config()->set('app.url', 'https://bengkel.test');

    $tenantId = (string) Str::ulid();
    provisionOwnerDashboardAccessForLoginTest($tenantId);

    Tenant::query()
        ->where('id', $tenantId)
        ->update(['subdomain' => 'autoserv-alpha']);

    $user = User::factory()->create([
        'role' => 'superadmin',
        'user_type' => 'superadmin',
        'is_superadmin' => true,
        'tenant_id' => null,
    ]);
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.view', 'web'));

    $response = $this
        ->from('https://autoserv-alpha.bengkel.test/login')
        ->post('https://autoserv-alpha.bengkel.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors([
        'email' => 'Email atau kata sandi tidak sesuai.',
    ]);
});

test('owner user is redirected to owner dashboard after login', function () {
    $workshopId = (string) Str::ulid();
    provisionOwnerDashboardAccessForLoginTest($workshopId);

    $workshop = new Workshop([
        'name' => 'Workshop Testing',
        'code' => 'WS-TST-01',
        'is_active' => true,
    ]);
    $workshop->id = $workshopId;
    $workshop->save();

    $user = User::factory()->create([
        'tenant_id' => $workshopId,
        'role' => 'owner',
        'user_type' => 'owner',
        'is_owner' => true,
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect("/owner/{$workshopId}/dashboard");
});

test('owner user is redirected to tenant subdomain dashboard after login when subdomain exists', function () {
    config()->set('app.url', 'https://bengkel.test');

    $workshopId = (string) Str::ulid();
    provisionOwnerDashboardAccessForLoginTest($workshopId);

    Tenant::query()
        ->where('id', $workshopId)
        ->update(['subdomain' => 'autoserv-alpha']);

    $workshop = new Workshop([
        'name' => 'Workshop Subdomain',
        'code' => 'WS-SUB-01',
        'is_active' => true,
    ]);
    $workshop->id = $workshopId;
    $workshop->save();

    $user = User::factory()->create([
        'tenant_id' => $workshopId,
        'role' => 'owner',
        'user_type' => 'owner',
        'is_owner' => true,
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $response = $this
        ->post('https://autoserv-alpha.bengkel.test/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('https://autoserv-alpha.bengkel.test/owner/dashboard');
});

test('tenant user cannot login from central domain when tenant subdomain exists', function () {
    config()->set('app.url', 'https://bengkel.test');

    $workshopId = (string) Str::ulid();
    provisionOwnerDashboardAccessForLoginTest($workshopId);

    Tenant::query()
        ->where('id', $workshopId)
        ->update(['subdomain' => 'autoserv-alpha']);

    $workshop = new Workshop([
        'name' => 'Workshop Subdomain Central Block',
        'code' => 'WS-SCB-01',
        'is_active' => true,
    ]);
    $workshop->id = $workshopId;
    $workshop->save();

    $user = User::factory()->create([
        'tenant_id' => $workshopId,
        'role' => 'owner',
        'user_type' => 'owner',
        'is_owner' => true,
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $response = $this
        ->withServerVariables([
            'HTTP_HOST' => 'bengkel.test',
            'HTTPS' => 'on',
        ])
        ->from('/login')
        ->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors([
        'email' => 'Email atau kata sandi tidak sesuai.',
    ]);
});

test('owner user falls back to generic dashboard when owner dashboard is inaccessible', function () {
    $workshopId = (string) Str::ulid();

    $workshop = new Workshop([
        'name' => 'Workshop Restricted',
        'code' => 'WS-RST-01',
        'is_active' => true,
    ]);
    $workshop->id = $workshopId;
    $workshop->save();

    $user = User::factory()->create([
        'tenant_id' => $workshopId,
        'role' => 'owner',
        'user_type' => 'owner',
        'is_owner' => true,
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
});

test('superadmin user with spatie role is redirected to platform dashboard after login', function () {
    $user = User::factory()->create([
        'role' => null,
        'user_type' => null,
        'is_superadmin' => false,
    ]);

    $role = Role::query()->create([
        'name' => 'superadmin',
        'guard_name' => 'web',
        'tenant_id' => null,
    ]);
    $role->givePermissionTo(Permission::findOrCreate('platform.tenants.view', 'web'));

    $user->assignRole($role);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/platform/dashboard');
});

test('owner user with spatie role is redirected to owner dashboard after login', function () {
    $workshopId = (string) Str::ulid();
    provisionOwnerDashboardAccessForLoginTest($workshopId);

    $workshop = new Workshop([
        'name' => 'Workshop Spatie',
        'code' => 'WS-SPT-01',
        'is_active' => true,
    ]);
    $workshop->id = $workshopId;
    $workshop->save();

    $user = User::factory()->create([
        'tenant_id' => $workshopId,
        'role' => null,
        'user_type' => null,
        'is_owner' => false,
    ]);

    $role = Role::query()->create([
        'name' => 'owner',
        'guard_name' => 'web',
        'tenant_id' => $workshopId,
    ]);
    $role->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));

    $user->assignRole($role);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect("/owner/{$workshopId}/dashboard");
});
