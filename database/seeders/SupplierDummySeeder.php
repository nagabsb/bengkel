<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Tenant;
use Faker\Generator;
use Illuminate\Database\Seeder;

class SupplierDummySeeder extends Seeder
{
    private const TARGET_SUPPLIERS_PER_TENANT = 25;

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
            $this->command?->warn('Tidak ada tenant aktif. Seeder supplier dummy dilewati.');

            return;
        }

        $faker = fake('id_ID');
        $totalCreated = 0;

        foreach ($tenantIds as $tenantId) {
            $existingCount = Supplier::query()
                ->where('tenant_id', $tenantId)
                ->count();

            $toCreate = max(self::TARGET_SUPPLIERS_PER_TENANT - $existingCount, 0);
            if ($toCreate === 0) {
                $this->command?->line("Tenant {$tenantId}: sudah {$existingCount} supplier (skip).");
                continue;
            }

            for ($index = 0; $index < $toCreate; $index++) {
                $supplierName = $this->generateSupplierName($faker);
                $picName = $faker->boolean(70) ? $this->generatePlainPersonName($faker) : null;

                Supplier::query()->create([
                    'tenant_id' => $tenantId,
                    'name' => $supplierName,
                    'phone' => $faker->boolean(85) ? $this->generateIndonesianPhoneNumber() : null,
                    'email' => $faker->boolean(60) ? $this->generateSupplierEmail($faker, $supplierName) : null,
                    'address' => $faker->boolean(75) ? $faker->address() : null,
                    'pic_name' => $picName,
                    'pic_phone' => $picName !== null && $faker->boolean(80)
                        ? $this->generateIndonesianPhoneNumber()
                        : null,
                    'notes' => $faker->boolean(25) ? $faker->sentence(10) : null,
                    'is_active' => true,
                ]);
            }

            $totalCreated += $toCreate;
            $this->command?->info("Tenant {$tenantId}: +{$toCreate} supplier dummy.");
        }

        $this->command?->info("Selesai. Total supplier dummy dibuat: {$totalCreated}.");
    }

    private function generateSupplierName(Generator $faker): string
    {
        $prefixes = ['CV', 'PT', 'UD', 'Toko', 'Sumber', 'Mitra', 'Berkah', 'Sentosa'];
        $keyword = $faker->randomElement([
            'Sparepart',
            'Oli',
            'Jaya Motor',
            'Auto Parts',
            'Ban & Aki',
            'Prima Teknik',
            'Diesel Service',
            'Suku Cadang',
        ]);

        return trim($faker->randomElement($prefixes).' '.$faker->company().' '.$keyword);
    }

    private function generateSupplierEmail(Generator $faker, string $supplierName): string
    {
        $domain = $faker->randomElement([
            'gmail.com',
            'yahoo.co.id',
            'outlook.com',
            'supplier.id',
        ]);
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/', '.', $supplierName));
        $slug = trim((string) preg_replace('/\.+/', '.', $slug), '.');
        $username = $slug !== '' ? $slug : strtolower($faker->lexify('supplier????'));

        return substr($username, 0, 40).$faker->numberBetween(1, 99).'@'.$domain;
    }

    private function generateIndonesianPhoneNumber(): string
    {
        $prefixes = [
            '0811', '0812', '0813', '0814', '0815', '0816',
            '0821', '0822', '0823',
            '0851', '0852', '0853',
            '0877', '0878',
            '0881', '0882', '0883', '0885', '0886',
            '0895', '0896', '0897', '0898', '0899',
        ];

        $prefix = $prefixes[array_rand($prefixes)];
        $targetLength = random_int(10, 13);
        $remainingDigits = max($targetLength - strlen($prefix), 4);

        $suffix = '';
        for ($index = 0; $index < $remainingDigits; $index++) {
            $suffix .= (string) random_int(0, 9);
        }

        return $prefix.$suffix;
    }

    private function generatePlainPersonName(Generator $faker): string
    {
        $fullName = trim((string) $faker->firstName().' '.(string) $faker->lastName());
        $normalizedName = preg_replace('/\s+/', ' ', $fullName);

        return is_string($normalizedName) && trim($normalizedName) !== ''
            ? trim($normalizedName)
            : 'PIC Supplier';
    }
}
