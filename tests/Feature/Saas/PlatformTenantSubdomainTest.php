<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function createPlatformTenantManager(): User
{
    $user = User::factory()->create([
        'is_superadmin' => true,
        'user_type' => 'superadmin',
    ]);
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    return $user;
}

test('tenant store auto-generates subdomain from tenant name', function () {
    $superadmin = createPlatformTenantManager();

    $this->actingAs($superadmin)
        ->post('/platform/tenants', [
            'name' => 'Bengkel Maju Bersama',
            'code' => 'BMB-001',
            'phone' => '081234567890',
            'address' => 'Jl. Raya No. 45',
            'owner_name' => 'Owner Bengkel',
            'owner_email' => 'coba@bengkel.test',
            'owner_password' => 'password123',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tenants', [
        'name' => 'Bengkel Maju Bersama',
        'code' => 'BMB-001',
        'subdomain' => 'bengkel-maju-bersama',
        'phone' => '081234567890',
        'address' => 'Jl. Raya No. 45',
    ]);
});

test('tenant store rejects duplicate auto-generated subdomain', function () {
    $superadmin = createPlatformTenantManager();

    Tenant::query()->create([
        'name' => 'Bengkel Maju Bersama',
        'code' => 'BMB-001',
        'subdomain' => 'bengkel-maju-bersama',
        'is_active' => true,
    ]);

    $this->actingAs($superadmin)
        ->from('/platform/tenants')
        ->post('/platform/tenants', [
            'name' => 'Bengkel Maju Bersama',
            'code' => 'BMB-002',
            'phone' => '081222233344',
            'owner_name' => 'Owner Kedua',
            'owner_email' => 'owner-kedua@bengkel.test',
            'owner_password' => 'password123',
            'is_active' => true,
        ])
        ->assertRedirect('/platform/tenants')
        ->assertSessionHasErrors([
            'subdomain' => "Subdomain otomatis 'bengkel-maju-bersama' sudah terdaftar. Ubah subdomain secara manual.",
        ]);
});

test('tenant store allows manual subdomain when tenant name is duplicated', function () {
    $superadmin = createPlatformTenantManager();

    Tenant::query()->create([
        'name' => 'Bengkel Maju Bersama',
        'code' => 'BMB-001',
        'subdomain' => 'bengkel-maju-bersama',
        'is_active' => true,
    ]);

    $this->actingAs($superadmin)
        ->post('/platform/tenants', [
            'name' => 'Bengkel Maju Bersama',
            'code' => 'BMB-002',
            'subdomain' => 'bengkel-maju-bersama-2',
            'phone' => '081299988877',
            'address' => 'Jl. Melati No. 7',
            'owner_name' => 'Owner Ketiga',
            'owner_email' => 'owner-ketiga@bengkel.test',
            'owner_password' => 'password123',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tenants', [
        'name' => 'Bengkel Maju Bersama',
        'code' => 'BMB-002',
        'subdomain' => 'bengkel-maju-bersama-2',
        'phone' => '081299988877',
        'address' => 'Jl. Melati No. 7',
        ]);
});
