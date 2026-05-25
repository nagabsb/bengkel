<?php

namespace App\Services\Platform;

use App\Models\VehicleMasterBrand;
use App\Models\VehicleMasterModel;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PlatformVehicleMasterService
{
    public function __construct(
        private readonly VehicleMasterSyncService $vehicleMasterSyncService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(Request $request): array
    {
        $search = trim((string) $request->query('brand_search', ''));
        $sortBy = $this->resolveSortBy((string) $request->query('brand_sort_by', 'name'));
        $sortDir = $this->resolveSortDirection((string) $request->query('brand_sort_dir', 'asc'));
        $perPage = $this->resolvePerPage((int) $request->query('brand_per_page', 10));
        $cursor = trim((string) $request->query('brand_cursor', ''));

        $summary = [
            'brands_total' => (int) VehicleMasterBrand::query()->count(),
            'brands_active' => (int) VehicleMasterBrand::query()->where('is_active', true)->count(),
            'models_total' => (int) VehicleMasterModel::query()->count(),
            'models_active' => (int) VehicleMasterModel::query()->where('is_active', true)->count(),
            'last_synced_at' => VehicleMasterBrand::query()->max('synced_at'),
        ];

        $sortableColumn = [
            'name' => 'name',
            'vehicle_type' => 'vehicle_type',
            'models_total' => 'models_total',
            'synced_at' => 'synced_at',
            'created_at' => 'created_at',
        ][$sortBy] ?? 'name';

        $paginator = $this->cursorPaginateWithFallback(
            VehicleMasterBrand::query()
                ->withCount('models as models_total')
                ->withCount([
                    'models as models_active' => fn (Builder $query): Builder => $query->where('is_active', true),
                ])
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $nested) use ($search): void {
                        $nested
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('source', 'like', "%{$search}%")
                            ->orWhereHas('models', function (Builder $modelQuery) use ($search): void {
                                $modelQuery->where('name', 'like', "%{$search}%");
                            });
                    });
                })
                ->orderBy($sortableColumn, $sortDir)
                ->orderBy('id', $sortDir),
            $perPage,
            ['id', 'name', 'slug', 'vehicle_type', 'source', 'is_active', 'synced_at', 'created_at', 'updated_at'],
            $cursor,
        );

        $rows = collect($paginator->items())
            ->map(function (VehicleMasterBrand $brand): array {
                $modelPreview = VehicleMasterModel::query()
                    ->where('vehicle_master_brand_id', (int) $brand->id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->limit(8)
                    ->pluck('name')
                    ->map(fn ($name): string => (string) $name)
                    ->values()
                    ->all();

                return [
                    'id' => (int) $brand->id,
                    'name' => (string) $brand->name,
                    'slug' => (string) $brand->slug,
                    'vehicle_type' => (string) $brand->vehicle_type,
                    'source' => (string) ($brand->source ?? 'json-sync'),
                    'models_total' => (int) ($brand->models_total ?? 0),
                    'models_active' => (int) ($brand->models_active ?? 0),
                    'model_preview' => $modelPreview,
                    'is_active' => (bool) $brand->is_active,
                    'synced_at' => $brand->synced_at,
                    'created_at' => $brand->created_at,
                    'updated_at' => $brand->updated_at,
                ];
            })
            ->values();

        return [
            'vehicleMasterSummary' => $summary,
            'brands' => [
                'mode' => 'cursor',
                'data' => $rows->all(),
                'per_page' => $paginator->perPage(),
                'total' => $summary['brands_total'],
                'from' => $rows->isEmpty() ? 0 : 1,
                'to' => $rows->count(),
                'current_cursor' => $paginator->cursor()?->encode(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
            'brandFilters' => [
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'per_page' => $perPage,
                'cursor' => $cursor !== '' ? $cursor : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, int|string>
     */
    public function syncFromPath(array $validated): array
    {
        $rawPath = trim(strip_tags((string) ($validated['sync_path'] ?? '')));
        $deactivateMissing = (bool) ($validated['deactivate_missing'] ?? false);

        $resolvedPath = $this->resolvePath($rawPath);

        return $this->vehicleMasterSyncService->syncFromJsonFile($resolvedPath, $deactivateMissing);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, int|string>
     */
    public function syncFromUpload(array $validated): array
    {
        /** @var UploadedFile|null $importFile */
        $importFile = $validated['import_file'] ?? null;
        $deactivateMissing = (bool) ($validated['deactivate_missing'] ?? false);

        if (! $importFile instanceof UploadedFile) {
            throw new \InvalidArgumentException('File JSON tidak valid.');
        }

        $realPath = $importFile->getRealPath();
        if (! is_string($realPath) || trim($realPath) === '') {
            throw new \InvalidArgumentException('Gagal membaca file upload JSON.');
        }

        return $this->vehicleMasterSyncService->syncFromJsonFile($realPath, $deactivateMissing);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTemplatePayload(): array
    {
        $diskTemplate = $this->buildTemplatePayloadFromDisk();
        if ($diskTemplate !== null) {
            return $diskTemplate;
        }

        return [
            'source' => 'platform-template',
            'generated_at' => now()->toIso8601String(),
            'brands' => [
                [
                    'name' => 'Honda',
                    'vehicle_type' => 'universal',
                    'external_id' => 'honda',
                    'is_active' => true,
                    'models' => [
                        ['name' => 'Beat', 'vehicle_type' => 'motor', 'is_active' => true],
                        ['name' => 'Vario 160', 'vehicle_type' => 'motor', 'is_active' => true],
                        ['name' => 'Brio', 'vehicle_type' => 'mobil', 'is_active' => true],
                    ],
                ],
                [
                    'name' => 'Toyota',
                    'vehicle_type' => 'mobil',
                    'external_id' => 'toyota',
                    'is_active' => true,
                    'models' => [
                        ['name' => 'Avanza', 'vehicle_type' => 'mobil', 'is_active' => true],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildTemplatePayloadFromDisk(): ?array
    {
        $templatePath = base_path('database/seeders/data/vehicle_master_template_id_full.json');
        if (! is_file($templatePath)) {
            return null;
        }

        $rawTemplate = file_get_contents($templatePath);
        if (! is_string($rawTemplate) || trim($rawTemplate) === '') {
            return null;
        }

        $rawTemplate = $this->stripUtf8Bom($rawTemplate);

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($rawTemplate, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $brands = data_get($decoded, 'brands');
        if (! is_array($brands) || count($brands) === 0) {
            return null;
        }

        return [
            'source' => (string) data_get($decoded, 'source', 'platform-template'),
            'generated_at' => now()->toIso8601String(),
            'brands' => $brands,
        ];
    }

    private function stripUtf8Bom(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildExportPayload(bool $activeOnly = true): array
    {
        $brandQuery = VehicleMasterBrand::query()
            ->with([
                'models' => function (Builder $query) use ($activeOnly): void {
                    if ($activeOnly) {
                        $query->where('is_active', true);
                    }

                    $query->orderBy('name');
                },
            ])
            ->orderBy('name');

        if ($activeOnly) {
            $brandQuery->where('is_active', true);
        }

        $brands = $brandQuery
            ->get(['id', 'name', 'vehicle_type', 'external_id', 'source', 'is_active', 'synced_at'])
            ->map(function (VehicleMasterBrand $brand): array {
                return [
                    'name' => (string) $brand->name,
                    'vehicle_type' => (string) $brand->vehicle_type,
                    'external_id' => $brand->external_id !== null ? (string) $brand->external_id : null,
                    'source' => (string) ($brand->source ?? 'json-sync'),
                    'is_active' => (bool) $brand->is_active,
                    'synced_at' => $this->formatNullableDate($brand->synced_at),
                    'models' => $brand->models
                        ->map(function (VehicleMasterModel $model): array {
                            return [
                                'name' => (string) $model->name,
                                'vehicle_type' => (string) $model->vehicle_type,
                                'external_id' => $model->external_id !== null ? (string) $model->external_id : null,
                                'source' => (string) ($model->source ?? 'json-sync'),
                                'is_active' => (bool) $model->is_active,
                                'synced_at' => $this->formatNullableDate($model->synced_at),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'source' => 'platform-export',
            'generated_at' => now()->toIso8601String(),
            'active_only' => $activeOnly,
            'brands_total' => count($brands),
            'brands' => $brands,
        ];
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function formatNullableDate(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return null;
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['name', 'vehicle_type', 'models_total', 'synced_at', 'created_at'], true)
            ? $sortBy
            : 'name';
    }

    private function resolveSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
    }

    private function resolvePerPage(int $perPage): int
    {
        return in_array($perPage, [10, 20, 50], true) ? $perPage : 10;
    }

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'brand_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'brand_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
