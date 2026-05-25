<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ExpenseCategoryDummySeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, description: string}>
     */
    private const CATEGORY_CATALOG = [
        ['name' => 'BBM Operasional', 'description' => 'Pembelian bahan bakar kendaraan operasional.'],
        ['name' => 'Gaji & Tunjangan', 'description' => 'Pengeluaran gaji, tunjangan, dan lembur karyawan.'],
        ['name' => 'Insentif Mekanik', 'description' => 'Bonus atau komisi berdasarkan pekerjaan servis.'],
        ['name' => 'Internet & Telekomunikasi', 'description' => 'Biaya paket internet, telepon, dan komunikasi bisnis.'],
        ['name' => 'Kebersihan', 'description' => 'Biaya kebersihan area servis, toilet, dan ruang tunggu.'],
        ['name' => 'Keamanan', 'description' => 'Biaya security, CCTV, atau sistem keamanan bengkel.'],
        ['name' => 'Lain-lain', 'description' => 'Pengeluaran lain yang tidak termasuk kategori utama.'],
        ['name' => 'Listrik & Air', 'description' => 'Tagihan utilitas operasional bengkel.'],
        ['name' => 'Operasional Harian', 'description' => 'Biaya rutin harian bengkel seperti ATK, air minum, dan kebutuhan kecil lainnya.'],
        ['name' => 'Pemasaran', 'description' => 'Promosi, iklan digital, banner, dan aktivitas marketing lainnya.'],
        ['name' => 'Peralatan Kerja', 'description' => 'Pembelian alat kerja bengkel yang bukan untuk dijual kembali.'],
        ['name' => 'Perawatan Aset', 'description' => 'Perbaikan dan maintenance peralatan atau fasilitas bengkel.'],
        ['name' => 'Perizinan & Pajak', 'description' => 'Biaya administrasi legal, pajak, dan perizinan usaha.'],
        ['name' => 'Sewa Tempat', 'description' => 'Biaya sewa bangunan atau lahan bengkel.'],
        ['name' => 'Transportasi & Kurir', 'description' => 'Biaya antar-jemput, pengiriman barang, atau operasional kendaraan internal.'],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('expense_categories')) {
            $this->command?->warn('Tabel expense_categories belum tersedia. Seeder kategori pengeluaran dilewati.');

            return;
        }

        $tenantIds = Tenant::query()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->pluck('id')
            ->map(fn (mixed $tenantId): string => (string) $tenantId)
            ->filter(fn (string $tenantId): bool => $tenantId !== '')
            ->values();

        if ($tenantIds->isEmpty()) {
            $this->command?->warn('Tidak ada tenant aktif. Seeder kategori pengeluaran dilewati.');

            return;
        }

        $totalCreated = 0;
        $totalRestored = 0;

        foreach ($tenantIds as $tenantId) {
            $createdThisTenant = 0;
            $restoredThisTenant = 0;

            foreach (self::CATEGORY_CATALOG as $categoryData) {
                $name = trim((string) $categoryData['name']);
                if ($name === '') {
                    continue;
                }

                $category = ExpenseCategory::withTrashed()
                    ->withoutGlobalScopes()
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
                        'description' => trim((string) $categoryData['description']),
                        'is_active' => true,
                    ])->save();

                    continue;
                }

                ExpenseCategory::query()
                    ->withoutGlobalScopes()
                    ->create([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'description' => trim((string) $categoryData['description']),
                    'is_active' => true,
                ]);

                $totalCreated++;
                $createdThisTenant++;
            }

            $this->command?->info(
                "Tenant {$tenantId}: +{$createdThisTenant} kategori pengeluaran, restore {$restoredThisTenant}.",
            );
        }

        $this->command?->info(
            "Selesai. Kategori pengeluaran dibuat: {$totalCreated}, dipulihkan: {$totalRestored}.",
        );
    }
}
