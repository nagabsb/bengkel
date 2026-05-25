<?php

namespace App\Services\Platform;

use App\Models\VehicleMasterBrand;
use App\Models\VehicleMasterModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class VehicleMasterSyncService
{
    /**
     * @return array<string, int|string>
     */
    public function syncFromJsonFile(string $filePath, bool $deactivateMissing = false): array
    {
        if (! is_file($filePath)) {
            throw new InvalidArgumentException("File JSON tidak ditemukan: {$filePath}");
        }

        $rawJson = file_get_contents($filePath);
        if (! is_string($rawJson) || trim($rawJson) === '') {
            throw new InvalidArgumentException('File JSON kosong atau tidak bisa dibaca.');
        }

        $rawJson = $this->stripUtf8Bom($rawJson);

        try {
            /** @var mixed $decodedPayload */
            $decodedPayload = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('Format JSON tidak valid: '.$exception->getMessage());
        }

        $brandRows = data_get($decodedPayload, 'brands', []);
        if (! is_array($brandRows)) {
            throw new InvalidArgumentException('Format JSON harus memiliki key `brands` berupa array.');
        }

        if (count($brandRows) === 0) {
            throw new InvalidArgumentException('Data `brands` kosong. Sinkronisasi dibatalkan.');
        }

        $syncedAt = now();
        $summary = [
            'brands_created' => 0,
            'brands_updated' => 0,
            'brands_skipped' => 0,
            'models_created' => 0,
            'models_updated' => 0,
            'models_skipped' => 0,
            'brands_deactivated' => 0,
            'models_deactivated' => 0,
            'source_file' => $filePath,
        ];

        DB::transaction(function () use (
            $brandRows,
            $deactivateMissing,
            $syncedAt,
            &$summary,
        ): void {
            $touchedBrandIds = [];
            $touchedModelIds = [];

            foreach ($brandRows as $brandRow) {
                $preparedBrand = $this->prepareBrandPayload($brandRow);
                if ($preparedBrand === null) {
                    $summary['brands_skipped']++;

                    continue;
                }

                [$brand, $isBrandCreated] = $this->upsertBrand($preparedBrand, $syncedAt);
                $touchedBrandIds[] = (int) $brand->id;

                if ($isBrandCreated) {
                    $summary['brands_created']++;
                } else {
                    $summary['brands_updated']++;
                }

                $modelRows = data_get($brandRow, 'models', []);
                if (! is_array($modelRows)) {
                    continue;
                }

                foreach ($modelRows as $modelRow) {
                    $preparedModel = $this->prepareModelPayload(
                        $modelRow,
                        (int) $brand->id,
                        (string) $preparedBrand['vehicle_type'],
                        (string) $preparedBrand['source'],
                    );

                    if ($preparedModel === null) {
                        $summary['models_skipped']++;

                        continue;
                    }

                    [$model, $isModelCreated] = $this->upsertModel($preparedModel, $syncedAt);
                    $touchedModelIds[] = (int) $model->id;

                    if ($isModelCreated) {
                        $summary['models_created']++;
                    } else {
                        $summary['models_updated']++;
                    }
                }
            }

            if ($deactivateMissing && count($touchedBrandIds) > 0) {
                $summary['brands_deactivated'] = VehicleMasterBrand::query()
                    ->whereNotIn('id', array_values(array_unique($touchedBrandIds)))
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            if ($deactivateMissing && count($touchedModelIds) > 0) {
                $summary['models_deactivated'] = VehicleMasterModel::query()
                    ->whereNotIn('id', array_values(array_unique($touchedModelIds)))
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });

        return $summary;
    }

    /**
     * @param  mixed  $brandRow
     * @return array<string, mixed>|null
     */
    private function prepareBrandPayload(mixed $brandRow): ?array
    {
        if (is_string($brandRow)) {
            $brandRow = [
                'name' => $brandRow,
            ];
        }

        if (! is_array($brandRow)) {
            return null;
        }

        $name = trim((string) data_get($brandRow, 'name', ''));
        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'brand-'.substr(sha1($name), 0, 12);
        }

        $source = trim((string) data_get($brandRow, 'source', ''));
        $externalId = trim((string) data_get($brandRow, 'external_id', ''));

        return [
            'name' => $name,
            'slug' => $slug,
            'vehicle_type' => $this->normalizeVehicleType(
                (string) data_get($brandRow, 'vehicle_type', 'universal'),
                true,
            ),
            'source' => $source !== '' ? $source : 'json-sync',
            'external_id' => $externalId !== '' ? $externalId : null,
            'is_active' => $this->normalizeBoolean(data_get($brandRow, 'is_active', true)),
        ];
    }

    /**
     * @param  mixed  $modelRow
     * @return array<string, mixed>|null
     */
    private function prepareModelPayload(
        mixed $modelRow,
        int $brandId,
        string $brandVehicleType,
        string $fallbackSource,
    ): ?array {
        if (is_string($modelRow)) {
            $modelRow = [
                'name' => $modelRow,
            ];
        }

        if (! is_array($modelRow)) {
            return null;
        }

        $name = trim((string) data_get($modelRow, 'name', ''));
        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'model-'.substr(sha1($name), 0, 12);
        }

        $source = trim((string) data_get($modelRow, 'source', ''));
        $externalId = trim((string) data_get($modelRow, 'external_id', ''));
        $defaultVehicleType = in_array($brandVehicleType, ['motor', 'mobil'], true) ? $brandVehicleType : 'motor';

        return [
            'vehicle_master_brand_id' => $brandId,
            'name' => $name,
            'slug' => $slug,
            'vehicle_type' => $this->normalizeVehicleType(
                (string) data_get($modelRow, 'vehicle_type', $defaultVehicleType),
                false,
            ),
            'source' => $source !== '' ? $source : $fallbackSource,
            'external_id' => $externalId !== '' ? $externalId : null,
            'is_active' => $this->normalizeBoolean(data_get($modelRow, 'is_active', true)),
        ];
    }

    /**
     * @param  array<string, mixed>  $preparedBrand
     * @return array{0: VehicleMasterBrand, 1: bool}
     */
    private function upsertBrand(array $preparedBrand, Carbon $syncedAt): array
    {
        $brand = VehicleMasterBrand::query()
            ->firstOrNew(['slug' => (string) $preparedBrand['slug']]);

        $isCreated = ! $brand->exists;

        $brand->fill([
            'name' => (string) $preparedBrand['name'],
            'vehicle_type' => (string) $preparedBrand['vehicle_type'],
            'external_id' => $preparedBrand['external_id'],
            'source' => (string) $preparedBrand['source'],
            'is_active' => (bool) $preparedBrand['is_active'],
            'synced_at' => $syncedAt,
        ]);
        $brand->save();

        return [$brand, $isCreated];
    }

    /**
     * @param  array<string, mixed>  $preparedModel
     * @return array{0: VehicleMasterModel, 1: bool}
     */
    private function upsertModel(array $preparedModel, Carbon $syncedAt): array
    {
        $model = VehicleMasterModel::query()
            ->firstOrNew([
                'vehicle_master_brand_id' => (int) $preparedModel['vehicle_master_brand_id'],
                'slug' => (string) $preparedModel['slug'],
                'vehicle_type' => (string) $preparedModel['vehicle_type'],
            ]);

        $isCreated = ! $model->exists;

        $model->fill([
            'name' => (string) $preparedModel['name'],
            'external_id' => $preparedModel['external_id'],
            'source' => (string) $preparedModel['source'],
            'is_active' => (bool) $preparedModel['is_active'],
            'synced_at' => $syncedAt,
        ]);
        $model->save();

        return [$model, $isCreated];
    }

    private function normalizeVehicleType(string $value, bool $allowUniversal): string
    {
        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['motor', 'mobil'], true)) {
            return $normalized;
        }

        if ($allowUniversal) {
            return 'universal';
        }

        return 'motor';
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'aktif', 'active'], true);
    }

    private function stripUtf8Bom(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }

        return $value;
    }
}
