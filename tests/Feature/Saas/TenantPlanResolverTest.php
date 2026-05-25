<?php

use App\Support\Billing\TenantPlanResolver;
use Database\Seeders\SaasDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('tenant plan resolver returns active package for workshop', function () {
    $this->seed(SaasDemoSeeder::class);

    $workshopId = DB::table('workshops')->where('code', 'ASV-001')->value('id');
    expect($workshopId)->not->toBeNull();

    $resolved = app(TenantPlanResolver::class)->forWorkshopId((string) $workshopId);

    expect($resolved)->not->toBeNull();
    expect($resolved['status'])->toBe('active');
    expect($resolved['plan']['slug'])->toBe('growth');
    expect($resolved['price']['label'])->toBe('Growth - 1 Bulan');
});
