<?php

namespace Database\Seeders;

use App\Models\SparePartCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SparePartCategoryDummySeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, description: string}>
     */
    private const CATEGORY_CATALOG = [
        ['name' => 'Pelumas', 'description' => 'Oli mesin, oli gardan, dan pelumas lain.'],
        ['name' => 'Fluida', 'description' => 'Cairan rem, coolant, dan fluida pendukung.'],
        ['name' => 'Filter', 'description' => 'Filter oli, filter udara, dan filter kabin.'],
        ['name' => 'Pengapian', 'description' => 'Komponen sistem pengapian seperti busi dan koil.'],
        ['name' => 'Kelistrikan', 'description' => 'Aki, lampu, sekring, dan part kelistrikan.'],
        ['name' => 'Pengereman', 'description' => 'Kampas rem, cakram, dan komponen rem.'],
        ['name' => 'Ban', 'description' => 'Ban, pentil, dan perlengkapan roda.'],
        ['name' => 'Transmisi', 'description' => 'Rantai, V-belt, gir, roller, dan sejenisnya.'],
        ['name' => 'Kaki-kaki', 'description' => 'Shockbreaker, bearing, dan komponen kaki-kaki.'],
        ['name' => 'Mesin', 'description' => 'Seal, gasket, kabel mesin, dan part internal.'],
        ['name' => 'Aksesori', 'description' => 'Part tambahan non-utama seperti wiper.'],
        ['name' => 'Body & Eksterior', 'description' => 'Part bodi luar dan komponen pendukung.'],
    ];

    public function run(): void
    {
        $tenantIds = Tenant::query()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->pluck('id')
            ->map(fn (mixed $tenantId): string => (string) $tenantId)
            ->filter(fn (string $tenantId): bool => $tenantId !== '')
            ->values();

        if ($tenantIds->isEmpty()) {
            $this->command?->warn('Tidak ada tenant aktif. Seeder kategori sparepart dummy dilewati.');

            return;
        }

        $totalCreated = 0;
        $totalRestored = 0;

        foreach ($tenantIds as $tenantId) {
            $createdThisTenant = 0;
            $restoredThisTenant = 0;

            foreach (self::CATEGORY_CATALOG as $categoryData) {
                $name = (string) $categoryData['name'];

                $category = SparePartCategory::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $name)
                    ->first();

                if ($category) {
                    if ($category->trashed()) {
                        $category->restore();
                        $totalRestored++;
                        $restoredThisTenant++;
                    }

                    $category->forceFill([
                        'description' => (string) $categoryData['description'],
                        'is_active' => true,
                    ])->save();

                    continue;
                }

                SparePartCategory::query()->create([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'description' => (string) $categoryData['description'],
                    'is_active' => true,
                ]);

                $totalCreated++;
                $createdThisTenant++;
            }

            $this->command?->info(
                "Tenant {$tenantId}: +{$createdThisTenant} kategori, restore {$restoredThisTenant}.",
            );
        }

        $this->command?->info("Selesai. Kategori sparepart dummy dibuat: {$totalCreated}, dipulihkan: {$totalRestored}.");
    }
}

