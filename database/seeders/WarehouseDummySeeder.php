<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Workshop;
use Illuminate\Database\Seeder;

class WarehouseDummySeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, code_suffix: string, note: string}>
     */
    private const WAREHOUSE_TEMPLATES = [
        ['name' => 'Gudang Utama', 'code_suffix' => 'MAIN', 'note' => 'Gudang utama penyimpanan stok harian.'],
        ['name' => 'Gudang Fast Moving', 'code_suffix' => 'FAST', 'note' => 'Fokus untuk sparepart cepat keluar.'],
        ['name' => 'Gudang Ban & Kaki-kaki', 'code_suffix' => 'TIRE', 'note' => 'Khusus ban, kampas rem, dan kaki-kaki.'],
        ['name' => 'Gudang Oli & Fluida', 'code_suffix' => 'OIL', 'note' => 'Khusus pelumas, coolant, dan cairan servis.'],
    ];

    public function run(): void
    {
        $tenants = Tenant::query()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get(['id', 'code']);

        if ($tenants->isEmpty()) {
            $this->command?->warn('Tidak ada tenant aktif. Seeder warehouse dummy dilewati.');

            return;
        }

        $faker = fake('id_ID');
        $totalCreated = 0;
        $totalRestored = 0;

        foreach ($tenants as $tenant) {
            $tenantId = (string) $tenant->id;
            $tenantCode = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', (string) $tenant->code));
            $tenantCode = $tenantCode !== '' ? substr($tenantCode, 0, 4) : 'TEN';

            $workshops = Workshop::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->get(['id', 'code', 'name']);

            if ($workshops->isEmpty()) {
                $this->command?->line("Tenant {$tenantId}: tidak ada workshop aktif (skip).");
                continue;
            }

            foreach ($workshops as $workshop) {
                $workshopId = (string) $workshop->id;
                $workshopCode = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', (string) $workshop->code));
                $workshopCode = $workshopCode !== '' ? substr($workshopCode, 0, 6) : 'WS';

                foreach (self::WAREHOUSE_TEMPLATES as $index => $template) {
                    $name = (string) $template['name'];
                    $code = $tenantCode.'-'.$workshopCode.'-'.$template['code_suffix'];
                    if (strlen($code) > 40) {
                        $code = substr($code, 0, 40);
                    }

                    $warehouse = Warehouse::withTrashed()
                        ->where('tenant_id', $tenantId)
                        ->where('workshop_id', $workshopId)
                        ->where('name', $name)
                        ->first();

                    if ($warehouse) {
                        if ($warehouse->trashed()) {
                            $warehouse->restore();
                            $totalRestored++;
                        }

                        $warehouse->forceFill([
                            'code' => $code,
                            'address' => $faker->address(),
                            'notes' => (string) $template['note'],
                            'is_active' => true,
                        ])->save();

                        continue;
                    }

                    Warehouse::query()->create([
                        'tenant_id' => $tenantId,
                        'workshop_id' => $workshopId,
                        'name' => $name,
                        'code' => $code,
                        'address' => $faker->address(),
                        'notes' => (string) $template['note'],
                        'is_active' => true,
                    ]);

                    $totalCreated++;
                }

                $this->command?->line(
                    "Workshop {$workshopId} ({$workshop->name}): master gudang dicek/sinkron.",
                );
            }
        }

        $this->command?->info("Selesai. Warehouse dummy dibuat: {$totalCreated}, dipulihkan: {$totalRestored}.");
    }
}

