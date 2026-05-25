<?php

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('superadmin can update application branding', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $this->actingAs($user)
        ->post('/platform/settings/application', [
            'app_name' => 'AutoServ Cloud',
            'app_logo' => UploadedFile::fake()->image('logo.png', 256, 256),
            'remove_logo' => false,
            'logo_background_enabled' => true,
            'logo_background_color' => '#059669',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('platform_settings', [
        'app_name' => 'AutoServ Cloud',
        'logo_background_enabled' => true,
        'logo_background_color' => '#059669',
    ]);

    $setting = PlatformSetting::query()->first();

    expect($setting)->not->toBeNull();
    expect($setting?->app_logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists((string) $setting?->app_logo_path);
});

test('application branding is shared to login page props', function () {
    PlatformSetting::query()->create([
        'app_name' => 'AutoServ Global',
        'app_logo_path' => null,
        'logo_background_enabled' => false,
        'logo_background_color' => '#047857',
    ]);

    $this->get('/login')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Index')
            ->where('appName', 'AutoServ Global')
            ->where('appLogoUrl', null)
            ->where('logoBackgroundEnabled', false)
            ->where('logoBackgroundColor', '#047857'),
        );
});

test('application logo url is shared to login page props', function () {
    $logoPath = 'platform/branding/autoserv-logo.png';
    Storage::fake('public');
    Storage::disk('public')->put($logoPath, 'fake-logo');

    PlatformSetting::query()->create([
        'app_name' => 'AutoServ Global',
        'app_logo_path' => $logoPath,
    ]);

    $this->get('/login')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Index')
            ->where('appLogoUrl', Storage::disk('public')->url($logoPath)),
        );
});

test('superadmin can disable logo background style', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('platform.tenants.manage', 'web'));

    $this->actingAs($user)
        ->post('/platform/settings/application', [
            'app_name' => 'AutoServ Plain',
            'logo_background_enabled' => false,
            'logo_background_color' => '#0EA5E9',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('platform_settings', [
        'app_name' => 'AutoServ Plain',
        'logo_background_enabled' => false,
        'logo_background_color' => '#0EA5E9',
    ]);
});

test('non privileged user cannot update application branding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/platform/settings/application', [
            'app_name' => 'Blocked Name',
        ])
        ->assertForbidden();
});
