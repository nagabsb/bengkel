<?php

use App\Models\Menu;
use App\Models\Plan;
use App\Models\PlanMenu;
use App\Models\PlanPrice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkshopSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function provisionOwnerDashboardVisibilityContext(string $tenantId): void
{
    Tenant::query()->forceCreate([
        'id' => $tenantId,
        'name' => 'Tenant '.$tenantId,
        'code' => strtoupper(str_replace('_', '-', $tenantId)),
        'is_active' => true,
    ]);

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

    $menu->permissions()->syncWithoutDetaching([
        (int) Permission::findOrCreate('owner.dashboard.view', 'web')->id,
    ]);
}

test('cashier dashboard shows finance widgets based on permissions', function () {
    $tenantId = 'ws_kasir';
    provisionOwnerDashboardVisibilityContext($tenantId);

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
        'role' => 'kasir',
        'user_type' => 'kasir',
    ]);

    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('service_orders.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('finance.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('expenses.view', 'web'));

    $this->actingAs($user)
        ->get("/owner/{$tenantId}/dashboard")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Dashboard')
            ->where('roleLabel', 'Kasir')
            ->where('visibility.showChart', true)
            ->where('visibility.showCategories', true)
            ->where('visibility.showTable', true)
            ->where('visibility.showActivities', true)
            ->has('stats', 3)
            ->where('stats.0.title', 'Pendapatan Bulan Ini')
            ->where('stats.1.title', 'Servis Selesai Bulan Ini')
            ->where('stats.2.title', 'Pengeluaran Bulan Ini')
            ->where('chart.title', 'Grafik Pendapatan'),
        );
});

test('mechanic dashboard hides finance widgets and keeps service widgets', function () {
    $tenantId = 'ws_mekanik';
    provisionOwnerDashboardVisibilityContext($tenantId);

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
        'role' => 'mekanik',
        'user_type' => 'mekanik',
    ]);

    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('service_orders.view', 'web'));

    $this->actingAs($user)
        ->get("/owner/{$tenantId}/dashboard")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Dashboard')
            ->where('roleLabel', 'Mekanik')
            ->where('visibility.showChart', true)
            ->where('visibility.showCategories', false)
            ->where('visibility.showTable', true)
            ->where('visibility.showActivities', true)
            ->has('stats', 1)
            ->where('stats.0.title', 'Servis Selesai Bulan Ini')
            ->where('chart.title', 'Grafik Servis Selesai')
            ->where('categories', []),
        );
});

