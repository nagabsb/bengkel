<?php

namespace Database\Seeders;

use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WarehouseSparePartStock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SparePartDummySeeder extends Seeder
{
    private const MAX_PARTS_PER_TENANT = 150;

    public function run(): void
    {
        if (! Schema::hasTable('spare_parts')) {
            $this->command?->warn('Tabel spare_parts belum ada. Seeder sparepart dummy dilewati.');

            return;
        }

        $tenants = Tenant::query()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get(['id', 'code']);

        if ($tenants->isEmpty()) {
            $this->command?->warn('Tidak ada tenant aktif. Seeder sparepart dummy dilewati.');

            return;
        }

        $catalog = array_slice($this->buildPartCatalog(), 0, self::MAX_PARTS_PER_TENANT);
        if ($catalog === []) {
            $this->command?->warn('Katalog sparepart kosong. Seeder dibatalkan.');

            return;
        }

        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $tenantId = (string) $tenant->id;
            $tenantCodePrefix = $this->resolveTenantCodePrefix((string) $tenant->code);

            $suppliers = Supplier::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);

            $warehousesByWorkshop = $this->resolveWarehousesByWorkshop($tenantId);

            $existingParts = SparePart::withTrashed()
                ->where('tenant_id', $tenantId)
                ->get(['id', 'name', 'sku']);

            $existingByName = $existingParts
                ->keyBy(fn (SparePart $part): string => $this->normalizeName((string) $part->name));

            $existingSkuSet = $existingParts
                ->pluck('sku')
                ->map(fn (mixed $sku): string => strtoupper(trim((string) $sku)))
                ->filter(fn (string $sku): bool => $sku !== '')
                ->flip();

            $createdThisTenant = 0;

            foreach ($catalog as $index => $partTemplate) {
                $partName = (string) $partTemplate['name'];
                $normalizedName = $this->normalizeName($partName);
                if ($normalizedName === '' || $existingByName->has($normalizedName)) {
                    continue;
                }

                $purchasePrice = $this->generatePrice(
                    (int) $partTemplate['purchase_min'],
                    (int) $partTemplate['purchase_max'],
                );
                $category = (string) $partTemplate['category'];
                $sellingPrice = $this->generateSellingPrice($purchasePrice, $category);
                $minimumStock = random_int(
                    (int) $partTemplate['min_stock_min'],
                    (int) $partTemplate['min_stock_max'],
                );
                $stock = random_int(
                    $minimumStock,
                    $minimumStock + (int) $partTemplate['stock_extra_max'],
                );

                $sparePart = SparePart::query()->create([
                    'tenant_id' => $tenantId,
                    'supplier_id' => $this->chooseSupplierId($suppliers, $partName, $category),
                    'name' => $partName,
                    'sku' => $this->generateUniqueSku(
                        $existingSkuSet,
                        $tenantCodePrefix,
                        $category,
                        $index + 1,
                    ),
                    'category' => $category,
                    'unit' => (string) $partTemplate['unit'],
                    'purchase_price' => $purchasePrice,
                    'selling_price' => $sellingPrice,
                    'stock' => $stock,
                    'minimum_stock' => $minimumStock,
                    'notes' => $this->generateStockNote($stock, $minimumStock),
                    'is_active' => true,
                ]);

                $this->seedWarehouseStock(
                    $tenantId,
                    $sparePart,
                    $stock,
                    $minimumStock,
                    $category,
                    $warehousesByWorkshop,
                );

                $existingByName->put($normalizedName, $sparePart);
                $createdThisTenant++;
            }

            $totalCreated += $createdThisTenant;
            $this->command?->info("Tenant {$tenantId}: +{$createdThisTenant} sparepart logis.");
        }

        $this->command?->info("Selesai. Total sparepart logis dibuat: {$totalCreated}.");
    }

    /**
     * @return array<int, array{name: string, category: string, unit: string, purchase_min: int, purchase_max: int, min_stock_min: int, min_stock_max: int, stock_extra_max: int}>
     */
    private function buildPartCatalog(): array
    {
        $catalog = [
            $this->part('Oli Mesin Matic 10W-30 0.8L', 'Pelumas', 'botol', 32000, 56000, 10, 24, 46),
            $this->part('Oli Mesin Matic 10W-30 1L', 'Pelumas', 'botol', 38000, 68000, 8, 20, 40),
            $this->part('Oli Mesin Sport 10W-40 1L', 'Pelumas', 'botol', 45000, 84000, 8, 18, 36),
            $this->part('Oli Gardan Matic 120ml', 'Pelumas', 'botol', 8000, 18000, 14, 30, 65),
            $this->part('Oli Mesin Mobil Bensin 5W-30 4L', 'Pelumas', 'kaleng', 220000, 450000, 4, 10, 16),
            $this->part('Oli Mesin Mobil Diesel 15W-40 5L', 'Pelumas', 'kaleng', 260000, 520000, 4, 10, 16),
            $this->part('Minyak Rem DOT3 250ml', 'Fluida', 'botol', 12000, 32000, 8, 18, 30),
            $this->part('Minyak Rem DOT4 250ml', 'Fluida', 'botol', 16000, 42000, 8, 18, 30),
            $this->part('Coolant Radiator Ready to Use 1L', 'Fluida', 'botol', 20000, 52000, 6, 14, 24),
            $this->part('ATF Matic 1L', 'Fluida', 'botol', 55000, 120000, 3, 8, 14),
            $this->part('Filter Oli Motor Universal', 'Filter', 'pcs', 11000, 36000, 10, 22, 38),
            $this->part('Filter Oli Mobil Small', 'Filter', 'pcs', 32000, 85000, 4, 10, 16),
            $this->part('Filter Udara Motor Busa', 'Filter', 'pcs', 18000, 52000, 7, 14, 22),
            $this->part('Filter Udara Mobil MPV', 'Filter', 'pcs', 60000, 160000, 3, 8, 12),
            $this->part('Filter Kabin Mobil Carbon', 'Filter', 'pcs', 55000, 150000, 2, 6, 10),
            $this->part('Busi Standar Nickel', 'Pengapian', 'pcs', 10000, 32000, 12, 26, 40),
            $this->part('Busi Iridium Premium', 'Pengapian', 'pcs', 30000, 95000, 7, 16, 24),
            $this->part('Koil Pengapian Motor Injeksi', 'Pengapian', 'pcs', 85000, 250000, 2, 6, 8),
            $this->part('Aki MF Motor 5Ah', 'Kelistrikan', 'pcs', 170000, 320000, 2, 6, 8),
            $this->part('Aki MF Mobil 35Ah', 'Kelistrikan', 'pcs', 520000, 980000, 1, 3, 4),
            $this->part('Sekring Blade Mini Set', 'Kelistrikan', 'set', 10000, 32000, 10, 22, 34),
            $this->part('Bohlam Halogen H4 12V', 'Kelistrikan', 'pcs', 23000, 75000, 5, 12, 18),
            $this->part('Kampas Rem Depan Motor Matic', 'Pengereman', 'set', 32000, 120000, 8, 18, 26),
            $this->part('Kampas Rem Belakang Motor Matic', 'Pengereman', 'set', 28000, 98000, 8, 18, 26),
            $this->part('Kampas Rem Depan Mobil', 'Pengereman', 'set', 120000, 450000, 3, 8, 12),
            $this->part('Piringan Cakram Depan Motor', 'Pengereman', 'pcs', 90000, 320000, 2, 6, 8),
            $this->part('Ban Motor Tubeless 80/90-14', 'Ban', 'pcs', 180000, 320000, 2, 6, 8),
            $this->part('Ban Motor Tubeless 90/90-14', 'Ban', 'pcs', 220000, 380000, 2, 6, 8),
            $this->part('Ban Mobil 185/65R15', 'Ban', 'pcs', 520000, 980000, 1, 4, 6),
            $this->part('Pentil Tubeless Brass', 'Ban', 'pcs', 3000, 12000, 18, 36, 65),
            $this->part('V-Belt CVT 743', 'Transmisi', 'pcs', 70000, 210000, 4, 10, 18),
            $this->part('Roller CVT 13gr Set', 'Transmisi', 'set', 30000, 90000, 6, 14, 20),
            $this->part('Kampas Ganda CVT', 'Transmisi', 'set', 85000, 260000, 4, 10, 16),
            $this->part('Rantai Motor 428H', 'Transmisi', 'pcs', 90000, 220000, 4, 10, 16),
            $this->part('Kabel Kopling Motor', 'Transmisi', 'pcs', 18000, 70000, 4, 10, 16),
            $this->part('Shockbreaker Depan Motor Set', 'Kaki-kaki', 'set', 180000, 600000, 2, 5, 7),
            $this->part('Bearing Roda Depan Motor', 'Kaki-kaki', 'pcs', 18000, 70000, 8, 18, 24),
            $this->part('Tie Rod End Mobil', 'Kaki-kaki', 'pcs', 70000, 240000, 2, 6, 10),
            $this->part('Bushing Arm Mobil', 'Kaki-kaki', 'pcs', 45000, 180000, 2, 6, 10),
            $this->part('Gasket Head Motor', 'Mesin', 'pcs', 28000, 100000, 4, 10, 14),
            $this->part('Seal Kruk As Kiri', 'Mesin', 'pcs', 10000, 42000, 6, 14, 20),
            $this->part('Piston Kit 52.4mm', 'Mesin', 'set', 80000, 300000, 2, 6, 8),
            $this->part('Water Pump Mobil', 'Mesin', 'pcs', 280000, 900000, 1, 4, 6),
            $this->part('Timing Belt Mobil 1.5L', 'Mesin', 'pcs', 220000, 760000, 1, 4, 6),
            $this->part('Wiper Blade 16 inch', 'Aksesori', 'pcs', 22000, 90000, 4, 10, 14),
            $this->part('Cable Tie 20cm Pack', 'Aksesori', 'dus', 14000, 52000, 3, 8, 12),
            $this->part('Spion Motor Kanan', 'Body & Eksterior', 'pcs', 22000, 90000, 3, 8, 12),
            $this->part('Handle Rem Motor', 'Body & Eksterior', 'pcs', 12000, 52000, 5, 12, 16),
        ];

        $motorMaticModels = [
            'Honda BeAT',
            'Honda Vario 125',
            'Honda Vario 160',
            'Honda PCX 160',
            'Honda Scoopy',
            'Honda Genio',
            'Yamaha NMAX',
            'Yamaha Aerox 155',
            'Yamaha Lexi',
            'Yamaha Mio M3',
            'Yamaha Grand Filano',
            'Suzuki Address',
            'Suzuki Avenis',
            'Yamaha Fazzio',
        ];

        foreach ($motorMaticModels as $model) {
            $catalog[] = $this->part("Filter Udara {$model}", 'Filter', 'pcs', 22000, 70000, 3, 8, 12);
            $catalog[] = $this->part("V-Belt CVT {$model}", 'Transmisi', 'pcs', 85000, 250000, 2, 6, 10);
            $catalog[] = $this->part("Kampas Rem Depan {$model}", 'Pengereman', 'set', 38000, 135000, 3, 8, 12);
            $catalog[] = $this->part("Busi {$model}", 'Pengapian', 'pcs', 12000, 62000, 4, 10, 16);
        }

        $motorChainModels = [
            'Honda Supra X 125',
            'Honda CB150R',
            'Yamaha Jupiter Z1',
            'Yamaha MX King',
        ];

        foreach ($motorChainModels as $model) {
            $catalog[] = $this->part("Rantai Set {$model}", 'Transmisi', 'set', 110000, 320000, 2, 6, 10);
            $catalog[] = $this->part("Kampas Kopling {$model}", 'Transmisi', 'set', 70000, 240000, 2, 6, 10);
        }

        $carModels = [
            'Toyota Avanza',
            'Daihatsu Xenia',
            'Honda Brio',
            'Mitsubishi Xpander',
            'Suzuki Ertiga',
            'Suzuki XL7',
            'Toyota Innova',
            'Toyota Rush',
            'Honda HR-V',
            'Honda BR-V',
            'Toyota Fortuner',
            'Mitsubishi Pajero Sport',
            'Daihatsu Terios',
        ];

        foreach ($carModels as $model) {
            $catalog[] = $this->part("Filter Oli {$model}", 'Filter', 'pcs', 42000, 140000, 2, 6, 10);
            $catalog[] = $this->part("Filter Udara {$model}", 'Filter', 'pcs', 70000, 190000, 2, 6, 10);
            $catalog[] = $this->part("Filter Kabin {$model}", 'Filter', 'pcs', 50000, 170000, 2, 6, 10);
            $catalog[] = $this->part("Kampas Rem Depan {$model}", 'Pengereman', 'set', 160000, 520000, 1, 4, 8);
        }

        return $this->dedupeCatalog($catalog);
    }

    /**
     * @return array{name: string, category: string, unit: string, purchase_min: int, purchase_max: int, min_stock_min: int, min_stock_max: int, stock_extra_max: int}
     */
    private function part(
        string $name,
        string $category,
        string $unit,
        int $purchaseMin,
        int $purchaseMax,
        int $minStockMin,
        int $minStockMax,
        int $stockExtraMax,
    ): array {
        return [
            'name' => trim($name),
            'category' => trim($category),
            'unit' => trim($unit),
            'purchase_min' => $purchaseMin,
            'purchase_max' => $purchaseMax,
            'min_stock_min' => $minStockMin,
            'min_stock_max' => $minStockMax,
            'stock_extra_max' => $stockExtraMax,
        ];
    }

    /**
     * @param  array<int, array{name: string, category: string, unit: string, purchase_min: int, purchase_max: int, min_stock_min: int, min_stock_max: int, stock_extra_max: int}>  $catalog
     * @return array<int, array{name: string, category: string, unit: string, purchase_min: int, purchase_max: int, min_stock_min: int, min_stock_max: int, stock_extra_max: int}>
     */
    private function dedupeCatalog(array $catalog): array
    {
        $seen = [];
        $result = [];

        foreach ($catalog as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $normalizedName = $this->normalizeName($name);
            if ($normalizedName === '' || isset($seen[$normalizedName])) {
                continue;
            }

            $seen[$normalizedName] = true;
            $result[] = $entry;
        }

        return $result;
    }

    private function normalizeName(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    private function resolveTenantCodePrefix(string $tenantCode): string
    {
        $raw = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $tenantCode));

        return substr($raw !== '' ? $raw : 'TEN', 0, 4);
    }

    private function generatePrice(int $minimum, int $maximum): int
    {
        return $this->roundPrice(random_int($minimum, $maximum));
    }

    private function generateSellingPrice(int $purchasePrice, string $category): int
    {
        [$minMarkup, $maxMarkup] = match (strtolower(trim($category))) {
            'ban' => [10, 22],
            'kelistrikan' => [12, 28],
            'pelumas', 'fluida', 'filter' => [18, 35],
            'pengereman', 'transmisi', 'kaki-kaki', 'mesin' => [20, 40],
            default => [15, 30],
        };

        $markup = random_int($minMarkup, $maxMarkup) / 100;
        $selling = (int) round($purchasePrice * (1 + $markup));

        return max($this->roundPrice($selling), $purchasePrice);
    }

    private function roundPrice(int $value): int
    {
        if ($value < 10000) {
            return (int) (round($value / 100) * 100);
        }

        if ($value < 100000) {
            return (int) (round($value / 500) * 500);
        }

        return (int) (round($value / 1000) * 1000);
    }

    /**
     * @param  Collection<int, Supplier>  $suppliers
     */
    private function chooseSupplierId(Collection $suppliers, string $partName, string $category): ?string
    {
        if ($suppliers->isEmpty()) {
            return null;
        }

        if (random_int(1, 100) <= 10) {
            return null;
        }

        $keywords = $this->resolvePartKeywords($partName, $category);

        $bestMatch = $suppliers
            ->map(function (Supplier $supplier) use ($keywords): array {
                $supplierName = strtolower((string) $supplier->name);
                $score = 0;

                foreach ($keywords as $keyword) {
                    if ($keyword !== '' && str_contains($supplierName, $keyword)) {
                        $score++;
                    }
                }

                return [
                    'supplier_id' => (string) $supplier->id,
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->first();

        $supplierId = trim((string) ($bestMatch['supplier_id'] ?? ''));
        if ($supplierId === '') {
            return (string) ($suppliers->random()->id ?? '');
        }

        return $supplierId;
    }

    /**
     * @return array<int, string>
     */
    private function resolvePartKeywords(string $partName, string $category): array
    {
        $context = strtolower(trim($partName.' '.$category));
        $keywords = ['sparepart', 'parts', 'motor', 'auto'];

        if (str_contains($context, 'oli') || str_contains($context, 'pelumas') || str_contains($context, 'fluida') || str_contains($context, 'coolant')) {
            $keywords = [...$keywords, 'oli', 'lubricant'];
        }
        if (str_contains($context, 'ban') || str_contains($context, 'tubeless')) {
            $keywords = [...$keywords, 'ban', 'tire'];
        }
        if (str_contains($context, 'aki') || str_contains($context, 'listrik') || str_contains($context, 'lampu')) {
            $keywords = [...$keywords, 'aki', 'battery', 'listrik'];
        }
        if (str_contains($context, 'rem') || str_contains($context, 'cakram')) {
            $keywords = [...$keywords, 'rem', 'brake'];
        }
        if (str_contains($context, 'filter')) {
            $keywords = [...$keywords, 'filter'];
        }
        if (str_contains($context, 'mesin') || str_contains($context, 'gasket') || str_contains($context, 'timing')) {
            $keywords = [...$keywords, 'mesin', 'teknik', 'engine'];
        }

        return collect($keywords)
            ->map(fn (string $keyword): string => trim(strtolower($keyword)))
            ->filter(fn (string $keyword): bool => $keyword !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, int>  $existingSkuSet
     */
    private function generateUniqueSku(
        Collection $existingSkuSet,
        string $tenantCodePrefix,
        string $category,
        int $sequence,
    ): string {
        $categoryPrefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $category) ?: 'PRT', 0, 3));

        do {
            $runningCode = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $randomCode = strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
            $sku = "{$tenantCodePrefix}-{$categoryPrefix}-{$runningCode}-{$randomCode}";
            $normalizedSku = strtoupper(trim($sku));
            $sequence++;
        } while ($existingSkuSet->has($normalizedSku));

        $existingSkuSet->put($normalizedSku, 1);

        return $sku;
    }

    private function generateStockNote(int $stock, int $minimumStock): string
    {
        if ($stock <= $minimumStock) {
            return 'Stok menipis, jadwalkan restock pada PO berikutnya.';
        }

        if ($stock <= $minimumStock + 4) {
            return 'Stok aman tipis, pantau pemakaian mingguan.';
        }

        return 'Stok aman untuk kebutuhan servis harian.';
    }

    /**
     * @return Collection<string, Collection<int, Warehouse>>
     */
    private function resolveWarehousesByWorkshop(string $tenantId): Collection
    {
        if (! Schema::hasTable('warehouses')) {
            return collect();
        }

        return Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'workshop_id', 'name', 'code'])
            ->groupBy(fn (Warehouse $warehouse): string => (string) $warehouse->workshop_id);
    }

    /**
     * @param  Collection<string, Collection<int, Warehouse>>  $warehousesByWorkshop
     */
    private function seedWarehouseStock(
        string $tenantId,
        SparePart $sparePart,
        int $stock,
        int $minimumStock,
        string $category,
        Collection $warehousesByWorkshop,
    ): void {
        if (! Schema::hasTable('warehouse_spare_part_stocks') || $warehousesByWorkshop->isEmpty()) {
            return;
        }

        $workshopIds = $warehousesByWorkshop->keys()->values();
        $workshopCount = $workshopIds->count();
        if ($workshopCount === 0) {
            return;
        }

        $stockChunks = $this->splitQuantity($stock, $workshopCount, 0.68);
        $minimumChunks = $this->splitQuantity($minimumStock, $workshopCount, 0.7);

        foreach ($workshopIds as $index => $workshopId) {
            $warehouses = $warehousesByWorkshop->get((string) $workshopId);
            if (! $warehouses instanceof Collection || $warehouses->isEmpty()) {
                continue;
            }

            $targetWarehouse = $this->pickWarehouseByCategory($warehouses, $category);
            if (! $targetWarehouse instanceof Warehouse) {
                continue;
            }

            WarehouseSparePartStock::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'workshop_id' => (string) $workshopId,
                    'warehouse_id' => (string) $targetWarehouse->id,
                    'spare_part_id' => (string) $sparePart->id,
                ],
                [
                    'stock' => max((int) ($stockChunks[$index] ?? 0), 0),
                    'minimum_stock' => max((int) ($minimumChunks[$index] ?? 0), 0),
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Warehouse>  $warehouses
     */
    private function pickWarehouseByCategory(Collection $warehouses, string $category): ?Warehouse
    {
        $keywords = match (strtolower(trim($category))) {
            'pelumas', 'fluida' => ['oli', 'fluid'],
            'ban', 'kaki-kaki' => ['ban', 'tire', 'kaki'],
            default => ['fast', 'utama', 'main'],
        };

        foreach ($keywords as $keyword) {
            /** @var Warehouse|null $found */
            $found = $warehouses->first(function (Warehouse $warehouse) use ($keyword): bool {
                $haystack = strtolower((string) $warehouse->name.' '.(string) $warehouse->code);

                return $keyword !== '' && str_contains($haystack, $keyword);
            });

            if ($found instanceof Warehouse) {
                return $found;
            }
        }

        /** @var Warehouse|null $fallback */
        $fallback = $warehouses->first();

        return $fallback;
    }

    /**
     * @return array<int, int>
     */
    private function splitQuantity(int $total, int $chunks, float $firstWeight): array
    {
        if ($chunks <= 1) {
            return [max($total, 0)];
        }

        $total = max($total, 0);
        $result = array_fill(0, $chunks, 0);

        $first = (int) floor($total * $firstWeight);
        $first = max(0, min($first, $total));
        $result[0] = $first;

        $remaining = $total - $first;
        $base = intdiv($remaining, $chunks - 1);
        $extra = $remaining % ($chunks - 1);

        for ($i = 1; $i < $chunks; $i++) {
            $result[$i] = $base + ($i <= $extra ? 1 : 0);
        }

        return $result;
    }
}
