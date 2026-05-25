<?php

namespace App\Console\Commands;

use App\Services\Platform\VehicleMasterSyncService;
use Illuminate\Console\Command;

class SyncVehicleMasterData extends Command
{
    protected $signature = 'vehicle-master:sync
        {--path=database/data/vehicle_master_data.json : Path file JSON master kendaraan}
        {--deactivate-missing : Nonaktifkan data yang tidak ditemukan pada file terbaru}';

    protected $description = 'Sinkronisasi master merek dan model kendaraan dari file JSON pusat.';

    public function handle(VehicleMasterSyncService $syncService): int
    {
        $rawPath = trim((string) $this->option('path'));
        if ($rawPath === '') {
            $this->error('Opsi --path wajib diisi.');

            return self::FAILURE;
        }

        $resolvedPath = $this->resolvePath($rawPath);
        $deactivateMissing = (bool) $this->option('deactivate-missing');

        try {
            $summary = $syncService->syncFromJsonFile($resolvedPath, $deactivateMissing);
        } catch (\Throwable $throwable) {
            $this->error('Sinkronisasi gagal: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $this->info('Sinkronisasi master kendaraan selesai.');
        $this->table(['Metrik', 'Nilai'], [
            ['File sumber', (string) ($summary['source_file'] ?? '-')],
            ['Brand dibuat', (string) ($summary['brands_created'] ?? 0)],
            ['Brand diperbarui', (string) ($summary['brands_updated'] ?? 0)],
            ['Brand dilewati', (string) ($summary['brands_skipped'] ?? 0)],
            ['Model dibuat', (string) ($summary['models_created'] ?? 0)],
            ['Model diperbarui', (string) ($summary['models_updated'] ?? 0)],
            ['Model dilewati', (string) ($summary['models_skipped'] ?? 0)],
            ['Brand dinonaktifkan', (string) ($summary['brands_deactivated'] ?? 0)],
            ['Model dinonaktifkan', (string) ($summary['models_deactivated'] ?? 0)],
        ]);

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}

