<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Workshop;
use Faker\Generator;
use Illuminate\Database\Seeder;

class CustomerDummySeeder extends Seeder
{
    private const TARGET_CUSTOMERS_PER_TENANT = 60;

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
            $this->command?->warn('Tidak ada tenant aktif. Seeder customer dummy dilewati.');

            return;
        }

        $faker = fake('id_ID');
        $totalCreated = 0;

        foreach ($tenantIds as $tenantId) {
            $workshopIds = Workshop::query()
                ->where('tenant_id', $tenantId)
                ->pluck('id')
                ->map(fn (mixed $workshopId): string => (string) $workshopId)
                ->filter(fn (string $workshopId): bool => $workshopId !== '')
                ->values();

            if ($workshopIds->isEmpty()) {
                $this->command?->line("Tenant {$tenantId}: tidak ada workshop aktif (skip).");
                continue;
            }

            $existingCount = Customer::query()
                ->where('tenant_id', $tenantId)
                ->count();

            $toCreate = max(self::TARGET_CUSTOMERS_PER_TENANT - $existingCount, 0);
            if ($toCreate === 0) {
                $this->command?->line("Tenant {$tenantId}: sudah {$existingCount} customer (skip).");
                continue;
            }

            for ($index = 0; $index < $toCreate; $index++) {
                $workshopId = (string) ($workshopIds->random() ?? $tenantId);
                Customer::query()->create([
                    'tenant_id' => $tenantId,
                    'workshop_id' => $workshopId,
                    'name' => $this->generatePlainCustomerName($faker),
                    'phone' => $faker->boolean(80) ? $this->generateIndonesianMobileNumber() : null,
                    'email' => $faker->boolean(65) ? $faker->unique()->safeEmail() : null,
                    'address' => $faker->boolean(70) ? $faker->address() : null,
                    'notes' => $faker->boolean(25) ? $faker->sentence(8) : null,
                    'is_active' => true,
                ]);
            }

            $totalCreated += $toCreate;
            $this->command?->info("Tenant {$tenantId}: +{$toCreate} customer dummy.");
        }

        $this->command?->info("Selesai. Total customer dummy dibuat: {$totalCreated}.");
    }

    private function generateIndonesianMobileNumber(): string
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

    private function generatePlainCustomerName(Generator $faker): string
    {
        $fullName = trim((string) $faker->firstName().' '.(string) $faker->lastName());
        $normalizedName = preg_replace('/\s+/', ' ', $fullName);

        return is_string($normalizedName) && trim($normalizedName) !== ''
            ? trim($normalizedName)
            : 'Pelanggan';
    }
}
