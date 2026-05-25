<?php

namespace Database\Seeders;

use App\Models\SparePartUnit;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SparePartUnitDummySeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, symbol: string, description: string}>
     */
    private const UNIT_CATALOG = [
        ['name' => 'pcs', 'symbol' => 'pcs', 'description' => 'Satuan per buah.'],
        ['name' => 'set', 'symbol' => 'set', 'description' => 'Satuan per set/kit.'],
        ['name' => 'botol', 'symbol' => 'btl', 'description' => 'Satuan kemasan botol.'],
        ['name' => 'kaleng', 'symbol' => 'klg', 'description' => 'Satuan kemasan kaleng.'],
        ['name' => 'liter', 'symbol' => 'L', 'description' => 'Satuan volume liter.'],
        ['name' => 'ml', 'symbol' => 'ml', 'description' => 'Satuan volume mililiter.'],
        ['name' => 'tube', 'symbol' => 'tube', 'description' => 'Satuan kemasan tube.'],
        ['name' => 'pasang', 'symbol' => 'psg', 'description' => 'Satuan per pasangan.'],
        ['name' => 'roll', 'symbol' => 'roll', 'description' => 'Satuan gulung/roll.'],
        ['name' => 'dus', 'symbol' => 'dus', 'description' => 'Satuan per dus/karton.'],
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
            $this->command?->warn('Tidak ada tenant aktif. Seeder satuan sparepart dummy dilewati.');

            return;
        }

        $totalCreated = 0;
        $totalRestored = 0;

        foreach ($tenantIds as $tenantId) {
            $createdThisTenant = 0;
            $restoredThisTenant = 0;

            foreach (self::UNIT_CATALOG as $unitData) {
                $name = (string) $unitData['name'];

                $unit = SparePartUnit::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $name)
                    ->first();

                if ($unit) {
                    if ($unit->trashed()) {
                        $unit->restore();
                        $totalRestored++;
                        $restoredThisTenant++;
                    }

                    $unit->forceFill([
                        'symbol' => (string) $unitData['symbol'],
                        'description' => (string) $unitData['description'],
                        'is_active' => true,
                    ])->save();

                    continue;
                }

                SparePartUnit::query()->create([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'symbol' => (string) $unitData['symbol'],
                    'description' => (string) $unitData['description'],
                    'is_active' => true,
                ]);

                $totalCreated++;
                $createdThisTenant++;
            }

            $this->command?->info(
                "Tenant {$tenantId}: +{$createdThisTenant} satuan, restore {$restoredThisTenant}.",
            );
        }

        $this->command?->info("Selesai. Satuan sparepart dummy dibuat: {$totalCreated}, dipulihkan: {$totalRestored}.");
    }
}

