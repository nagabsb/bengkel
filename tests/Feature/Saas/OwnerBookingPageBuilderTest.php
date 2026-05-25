<?php

use App\Models\BookingPageSetting;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\PlanMenu;
use App\Models\PlanPrice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function provisionOwnerBookingBuilderAccess(string $tenantId, string $tenantName = 'Tenant Demo'): void
{
    Tenant::query()->forceCreate([
        'id' => $tenantId,
        'name' => $tenantName,
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

    $bookingBuilderMenu = Menu::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'menu_type' => 'system',
        'label' => 'Booking Builder',
        'route' => 'owner.bookings.builder',
        'icon' => 'calendar',
        'sort_order' => 23,
        'is_active' => true,
    ]);

    PlanMenu::query()->create([
        'plan_id' => (int) $plan->id,
        'menu_id' => (int) $bookingBuilderMenu->id,
    ]);

    $bookingBuilderMenu->permissions()->syncWithoutDetaching([
        (int) Permission::findOrCreate('bookings.manage', 'web')->id,
    ]);
}

test('owner booking page builder uses active workshop name for tenant profile and default headline', function () {
    $tenantId = 'ws_booking_builder_1';
    provisionOwnerBookingBuilderAccess($tenantId, 'AutoServ Tenant Demo');

    $workshop = new Workshop([
        'tenant_id' => $tenantId,
        'name' => 'Bengkel Maju Motor',
        'code' => 'MJU-MTR',
        'is_active' => true,
    ]);
    $workshop->id = $tenantId;
    $workshop->save();

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
    ]);
    $user->givePermissionTo(Permission::findOrCreate('bookings.manage', 'web'));

    $this->actingAs($user)
        ->get("/owner/{$tenantId}/bookings/builder")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/BookingPageBuilder')
            ->where('tenantProfile.name', 'Bengkel Maju Motor')
            ->where('builderConfig.headline', 'Bengkel Maju Motor')
            ->where('builderConfig.cta_size', 'medium'),
        );
});

test('owner booking page builder upgrades legacy tenant-name headline to workshop name', function () {
    $tenantId = 'ws_booking_builder_2';
    provisionOwnerBookingBuilderAccess($tenantId, 'AutoServ Tenant Demo');

    $workshop = new Workshop([
        'tenant_id' => $tenantId,
        'name' => 'Bengkel Prima Jaya',
        'code' => 'PRM-JYA',
        'is_active' => true,
    ]);
    $workshop->id = $tenantId;
    $workshop->save();

    BookingPageSetting::query()->create([
        'tenant_id' => $tenantId,
        'headline' => 'AutoServ Tenant Demo',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
    ]);
    $user->givePermissionTo(Permission::findOrCreate('bookings.manage', 'web'));

    $this->actingAs($user)
        ->get("/owner/{$tenantId}/bookings/builder")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/BookingPageBuilder')
            ->where('tenantProfile.name', 'Bengkel Prima Jaya')
            ->where('builderConfig.headline', 'Bengkel Prima Jaya')
            ->where('builderConfig.cta_size', 'medium'),
        );
});
