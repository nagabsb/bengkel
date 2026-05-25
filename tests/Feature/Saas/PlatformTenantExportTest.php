<?php

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkshopSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('superadmin can export tenant list to excel', function () {
    $superadmin = User::factory()->create();
    $superadmin->givePermissionTo(Permission::findOrCreate('platform.tenants.view', 'web'));

    $tenant = Tenant::query()->forceCreate([
        'id' => '01JTENANTALPHA000000000001',
        'name' => 'Tenant Alpha',
        'code' => 'TEN-ALPHA',
        'is_active' => true,
    ]);

    User::query()->create([
        'tenant_id' => (string) $tenant->id,
        'name' => 'Owner Alpha',
        'email' => 'owner.alpha@example.com',
        'password' => 'password',
        'role' => 'owner',
        'user_type' => 'owner',
        'is_superadmin' => false,
        'is_owner' => true,
        'email_verified_at' => now(),
    ]);

    $plan = Plan::query()->create([
        'name' => 'Growth',
        'slug' => 'growth',
        'max_workshops' => 2,
        'max_users_per_ws' => 10,
        'has_ai_feature' => true,
        'has_notification' => true,
        'has_loyalty' => false,
        'has_trial' => true,
        'trial_duration_days' => 14,
        'is_active' => true,
    ]);

    $planPrice = PlanPrice::query()->create([
        'plan_id' => (int) $plan->id,
        'label' => 'Growth - 1 Bulan',
        'duration_months' => 1,
        'price' => 199000,
        'discount_pct' => 0,
        'is_active' => true,
    ]);

    WorkshopSubscription::query()->create([
        'tenant_id' => (string) $tenant->id,
        'plan_price_id' => (int) $planPrice->id,
        'status' => 'active',
        'started_at' => '2026-04-01 00:00:00',
        'expired_at' => '2026-05-01 00:00:00',
        'trial_ends_at' => null,
    ]);

    $response = $this->actingAs($superadmin)->get('/platform/tenants/export');

    $response
        ->assertOk()
        ->assertDownload()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $tempFile = tempnam(sys_get_temp_dir(), 'tenant-export-');
    file_put_contents($tempFile, $response->streamedContent());

    $spreadsheet = IOFactory::load($tempFile);
    $sheet = $spreadsheet->getActiveSheet();

    expect((string) $sheet->getCell('A1')->getValue())->toBe('Nama Tenant');
    expect((string) $sheet->getCell('B1')->getValue())->toBe('Nama Owner');
    expect((string) $sheet->getCell('C1')->getValue())->toBe('Email Owner');
    expect((string) $sheet->getCell('D1')->getValue())->toBe('Paket Aktif');
    expect((string) $sheet->getCell('E1')->getValue())->toBe('Tanggal Mulai Paket');
    expect((string) $sheet->getCell('A2')->getValue())->toBe('Tenant Alpha');
    expect((string) $sheet->getCell('B2')->getValue())->toBe('Owner Alpha');
    expect((string) $sheet->getCell('C2')->getValue())->toBe('owner.alpha@example.com');
    expect((string) $sheet->getCell('D2')->getValue())->toBe('Growth');
    expect((string) $sheet->getCell('E2')->getValue())->toBe('01/04/2026');

    $spreadsheet->disconnectWorksheets();
    @unlink($tempFile);
});

test('non privileged user cannot export tenant list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/platform/tenants/export')
        ->assertForbidden();
});
