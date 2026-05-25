<?php

use App\Models\Menu;
use App\Models\Plan;
use App\Models\PlanMenu;
use App\Models\PlanPrice;
use App\Models\Tenant;
use App\Models\TenantPrintSetting;
use App\Models\User;
use App\Models\WorkshopSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function provisionOwnerSettingsNotaAccess(string $tenantId): void
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

    $settingsRootMenu = Menu::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'menu_type' => 'system',
        'label' => 'Pengaturan',
        'route' => null,
        'icon' => 'settings',
        'sort_order' => 90,
        'is_active' => true,
    ]);

    $settingsNotaMenu = Menu::query()->create([
        'tenant_id' => null,
        'parent_id' => (int) $settingsRootMenu->id,
        'menu_type' => 'system',
        'label' => 'Nota',
        'route' => 'owner.settings?tab=nota',
        'icon' => 'printer',
        'sort_order' => 15,
        'is_active' => true,
    ]);

    PlanMenu::query()->create([
        'plan_id' => (int) $plan->id,
        'menu_id' => (int) $settingsRootMenu->id,
    ]);

    PlanMenu::query()->create([
        'plan_id' => (int) $plan->id,
        'menu_id' => (int) $settingsNotaMenu->id,
    ]);

    $settingsNotaMenu->permissions()->syncWithoutDetaching([
        (int) Permission::findOrCreate('users.manage', 'web')->id,
    ]);
}

test('owner settings nota tab can be accessed when menu and permissions are valid', function () {
    provisionOwnerSettingsNotaAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->get('/owner/ws_alpha/settings?tab=nota')
        ->assertOk();
});

test('owner can update tenant thermal print settings from settings nota tab', function () {
    provisionOwnerSettingsNotaAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->post('/owner/ws_alpha/settings/print', [
            'printer_name' => 'Thermal Kasir Depan',
            'print_type' => 'thermal',
            'paper_size' => '58mm',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tenant_print_settings', [
        'tenant_id' => 'ws_alpha',
        'printer_name' => 'Thermal Kasir Depan',
        'print_type' => 'thermal',
        'paper_size' => '58mm',
    ]);
});

test('owner print setting update validates paper size for thermal printer', function () {
    provisionOwnerSettingsNotaAccess('ws_alpha');

    $user = User::factory()->create([
        'tenant_id' => 'ws_alpha',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('owner.dashboard.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('users.manage', 'web'));

    $this->actingAs($user)
        ->from('/owner/ws_alpha/settings?tab=nota')
        ->post('/owner/ws_alpha/settings/print', [
            'printer_name' => 'Thermal Kasir Belakang',
            'print_type' => 'thermal',
            'paper_size' => 'A4',
        ])
        ->assertRedirect('/owner/ws_alpha/settings?tab=nota')
        ->assertSessionHasErrors([
            'paper_size' => 'Ukuran kertas thermal harus 58mm atau 80mm.',
        ]);

    expect(TenantPrintSetting::query()->where('tenant_id', 'ws_alpha')->exists())->toBeFalse();
});
