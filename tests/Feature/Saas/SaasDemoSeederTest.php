<?php

use App\Models\User;
use Database\Seeders\SaasDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('saas demo seeder is idempotent and keeps rbac pivot unique', function () {
    $this->seed(SaasDemoSeeder::class);
    $this->seed(SaasDemoSeeder::class);

    $permissions = DB::table('permissions')->get(['name']);
    $rolePermissionPairs = DB::table('role_has_permissions')->get(['role_id', 'permission_id']);

    expect($permissions->count())->toBe($permissions->unique('name')->count());
    expect($permissions->count())->toBeGreaterThanOrEqual(14);
    expect(DB::table('roles')->count())->toBe(5);
    expect($rolePermissionPairs->count())->toBe(
        $rolePermissionPairs->unique(fn ($pair): string => (string) $pair->role_id.'|'.(string) $pair->permission_id)->count(),
    );
    expect($rolePermissionPairs->count())->toBeGreaterThan(0);
    expect(DB::table('model_has_roles')->count())->toBe(5);
    expect(User::query()->count())->toBe(5);
    expect(DB::table('plans')->count())->toBe(3);
    expect(DB::table('plan_prices')->count())->toBe(3);
    expect(DB::table('workshop_subscriptions')->count())->toBe(1);

    $superadmin = User::query()->where('email', 'superadmin@autoserv.test')->firstOrFail();
    expect($superadmin->hasRole('superadmin'))->toBeTrue();

    $workshopId = DB::table('workshops')->where('code', 'ASV-001')->value('id');
    $activeSubscription = DB::table('workshop_subscriptions')
        ->where('tenant_id', $workshopId)
        ->where('status', 'active')
        ->first();

    expect($activeSubscription)->not->toBeNull();
});
