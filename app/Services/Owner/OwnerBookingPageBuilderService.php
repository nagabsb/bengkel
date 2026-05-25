<?php

namespace App\Services\Owner;

use App\Models\BookingPageSetting;
use App\Models\Tenant;
use App\Models\Workshop;
use App\Services\Tenant\TenantSubdomainService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OwnerBookingPageBuilderService
{
    public function __construct(
        private readonly OwnerMenuService $ownerMenuService,
        private readonly TenantSubdomainService $tenantSubdomainService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(
        Request $request,
        string $tenantId,
        TenantPlanResolver $planResolver,
        ?Authenticatable $user,
    ): array {
        $package = $planResolver->forTenantId($tenantId);
        $planId = data_get($package, 'plan.id');

        $menuTree = $this->ownerMenuService->buildOwnerMenuTree(
            $tenantId,
            $planId,
            hasPlanMenuTable: Schema::hasTable('plan_menu'),
        );

        $menuItems = $this->ownerMenuService->buildSidebarMenuItems(
            $menuTree,
            $tenantId,
            $user,
            $this->resolveCurrentUri($request),
        );

        $tenantProfile = $this->resolveTenantProfile($tenantId);
        $builderSetting = $this->resolveBuilderSetting($tenantId);

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'tenantProfile' => [
                'name' => $tenantProfile['name'],
                'subdomain' => $tenantProfile['subdomain'],
                'public_booking_url' => $this->resolvePublicBookingUrl($tenantId),
            ],
            'builderConfig' => $this->resolveBuilderConfig($builderSetting, $tenantProfile['name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPublicPageData(Request $request): array
    {
        $tenantId = $this->resolvePublicTenantId($request);
        if ($tenantId === null) {
            return [
                'isAvailable' => false,
                'availabilityMessage' => 'Tenant pada link booking tidak ditemukan.',
                'tenantId' => '',
                'tenantHint' => '',
                'tenantProfile' => [
                    'name' => 'Tenant',
                    'subdomain' => '',
                    'public_booking_url' => '',
                ],
                'builderConfig' => $this->resolveBuilderConfig(null, 'Tenant'),
                'publicBooking' => [
                    'submit_path' => '/booking',
                    'workshop_id' => '',
                    'workshop_name' => '',
                ],
            ];
        }

        $tenantProfile = $this->resolveTenantProfile($tenantId);
        $builderSetting = $this->resolveBuilderSetting($tenantId);
        $builderConfig = $this->resolveBuilderConfig($builderSetting, $tenantProfile['name']);
        $publicWorkshop = $this->resolvePublicBookingWorkshop($tenantId);
        $isActive = (bool) ($builderConfig['is_active'] ?? true);
        $hasWorkshop = $publicWorkshop['id'] !== '';
        $isAvailable = $isActive && $hasWorkshop;

        return [
            'isAvailable' => $isAvailable,
            'availabilityMessage' => $this->resolvePublicAvailabilityMessage($isActive, $hasWorkshop),
            'tenantId' => $tenantId,
            'tenantHint' => $tenantProfile['subdomain'] !== '' ? $tenantProfile['subdomain'] : $tenantId,
            'tenantProfile' => [
                'name' => $tenantProfile['name'],
                'subdomain' => $tenantProfile['subdomain'],
                'public_booking_url' => $this->resolvePublicBookingUrl($tenantId),
            ],
            'builderConfig' => $builderConfig,
            'publicBooking' => [
                'submit_path' => '/booking',
                'workshop_id' => $publicWorkshop['id'],
                'workshop_name' => $publicWorkshop['name'],
            ],
        ];
    }

    /**
     * @return array{tenant_id: string, workshop_id: string}
     */
    public function resolvePublicBookingContext(Request $request): array
    {
        $tenantId = $this->resolvePublicTenantId($request);
        if ($tenantId === null) {
            throw ValidationException::withMessages([
                'create_booking' => 'Tenant pada link booking tidak ditemukan.',
            ]);
        }

        $publicWorkshop = $this->resolvePublicBookingWorkshop($tenantId);
        if ($publicWorkshop['id'] === '') {
            throw ValidationException::withMessages([
                'create_booking' => 'Workshop aktif belum tersedia untuk tenant ini.',
            ]);
        }

        $builderSetting = $this->resolveBuilderSetting($tenantId);
        $isActive = (bool) ($builderSetting?->is_active ?? true);
        if (! $isActive) {
            throw ValidationException::withMessages([
                'create_booking' => 'Halaman booking publik sedang dinonaktifkan.',
            ]);
        }

        return [
            'tenant_id' => $tenantId,
            'workshop_id' => $publicWorkshop['id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateBuilderSetting(string $tenantId, array $validated): void
    {
        $this->assertBuilderTableReady('update_builder', 'Tabel page builder belum siap.');

        $galleryColumnReady = Schema::hasColumn('booking_page_settings', 'gallery_images');
        $currentGalleryPaths = $galleryColumnReady
            ? $this->resolveStoredGalleryPaths($tenantId)
            : [];
        $nextGalleryPaths = $galleryColumnReady
            ? $this->resolveNextGalleryPaths($tenantId, $validated, $currentGalleryPaths)
            : [];

        $payload = [
            'mode' => (string) ($validated['mode'] ?? 'tech'),
            'primary_color' => (string) ($validated['primary_color'] ?? '#0F766E'),
            'font_preset' => (string) ($validated['font_preset'] ?? 'modern'),
            'radius_preset' => (string) ($validated['radius_preset'] ?? 'medium'),
            'subheadline' => (string) ($validated['subheadline'] ?? 'Atur jadwal servis bengkel Anda tanpa antre panjang.'),
            'cta_label' => (string) ($validated['cta_label'] ?? 'Booking Sekarang'),
            'cta_size' => (string) ($validated['cta_size'] ?? 'medium'),
            'trust_badge' => (string) ($validated['trust_badge'] ?? 'Dipercaya pelanggan aktif setiap hari.'),
            'gallery_images' => $nextGalleryPaths,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];

        if (! Schema::hasColumn('booking_page_settings', 'cta_size')) {
            unset($payload['cta_size']);
        }

        if (! $galleryColumnReady) {
            unset($payload['gallery_images']);
        }

        DB::transaction(function () use ($tenantId, $payload): void {
            BookingPageSetting::query()->updateOrCreate(
                ['tenant_id' => $tenantId],
                [
                    'tenant_id' => $tenantId,
                    ...$payload,
                ],
            );
        });

        if ($galleryColumnReady) {
            $deletedGalleryPaths = array_values(array_diff($currentGalleryPaths, $nextGalleryPaths));
            if (count($deletedGalleryPaths) > 0) {
                Storage::disk('public')->delete($deletedGalleryPaths);
            }
        }
    }

    /**
     * @return array{name: string, subdomain: string}
     */
    private function resolveTenantProfile(string $tenantId): array
    {
        $fallbackProfile = [
            'name' => 'Tenant',
            'subdomain' => '',
        ];

        if (! Schema::hasTable('tenants')) {
            return $fallbackProfile;
        }

        $tenant = Tenant::query()
            ->where('id', $tenantId)
            ->first(['name', 'subdomain']);

        if (! $tenant) {
            return $fallbackProfile;
        }

        $tenantName = trim((string) ($tenant->name ?? '')) ?: 'Tenant';

        return [
            'name' => $tenantName,
            'subdomain' => trim((string) ($tenant->subdomain ?? '')),
        ];
    }

    private function resolveBuilderSetting(string $tenantId): ?BookingPageSetting
    {
        if (! Schema::hasTable('booking_page_settings')) {
            return null;
        }

        $selectColumns = [
            'tenant_id',
            'mode',
            'primary_color',
            'font_preset',
            'radius_preset',
            'headline',
            'subheadline',
            'cta_label',
            'trust_badge',
            'is_active',
        ];

        if (Schema::hasColumn('booking_page_settings', 'cta_size')) {
            $selectColumns[] = 'cta_size';
        }

        if (Schema::hasColumn('booking_page_settings', 'gallery_images')) {
            $selectColumns[] = 'gallery_images';
        }

        return BookingPageSetting::query()
            ->where('tenant_id', $tenantId)
            ->first($selectColumns);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveBuilderConfig(?BookingPageSetting $setting, string $displayName): array
    {
        $headline = $displayName !== ''
            ? $displayName
            : 'Booking Servis Cepat & Mudah';
        $galleryPaths = $this->normalizeGalleryPaths($setting?->gallery_images ?? []);

        return [
            'mode' => trim((string) ($setting?->mode ?? 'tech')) ?: 'tech',
            'primary_color' => strtoupper(trim((string) ($setting?->primary_color ?? '#0F766E'))) ?: '#0F766E',
            'font_preset' => trim((string) ($setting?->font_preset ?? 'modern')) ?: 'modern',
            'radius_preset' => trim((string) ($setting?->radius_preset ?? 'medium')) ?: 'medium',
            'headline' => $headline,
            'subheadline' => trim((string) ($setting?->subheadline ?? 'Atur jadwal servis bengkel Anda tanpa antre panjang.'))
                ?: 'Atur jadwal servis bengkel Anda tanpa antre panjang.',
            'cta_label' => trim((string) ($setting?->cta_label ?? 'Booking Sekarang')) ?: 'Booking Sekarang',
            'cta_size' => $this->normalizeCtaSize((string) ($setting?->cta_size ?? 'medium')),
            'trust_badge' => trim((string) ($setting?->trust_badge ?? 'Dipercaya pelanggan aktif setiap hari.'))
                ?: 'Dipercaya pelanggan aktif setiap hari.',
            'gallery_image_paths' => $galleryPaths,
            'gallery_images' => $this->resolveGalleryImageUrls($galleryPaths),
            'is_active' => (bool) ($setting?->is_active ?? true),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveStoredGalleryPaths(string $tenantId): array
    {
        $setting = BookingPageSetting::query()
            ->where('tenant_id', $tenantId)
            ->first(['gallery_images']);

        return $this->normalizeGalleryPaths($setting?->gallery_images ?? []);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, string>  $currentGalleryPaths
     * @return array<int, string>
     */
    private function resolveNextGalleryPaths(string $tenantId, array $validated, array $currentGalleryPaths): array
    {
        $requestedExistingPaths = $this->normalizeGalleryPaths($validated['existing_gallery_paths'] ?? []);
        $persistedGalleryLookup = array_flip($currentGalleryPaths);

        $keptGalleryPaths = array_values(array_filter(
            $requestedExistingPaths,
            static fn (string $path): bool => isset($persistedGalleryLookup[$path]),
        ));

        $newGalleryPaths = [];
        $uploadedGalleryImages = $validated['gallery_images'] ?? [];
        if (is_array($uploadedGalleryImages)) {
            foreach ($uploadedGalleryImages as $uploadedGalleryImage) {
                if (! $uploadedGalleryImage instanceof UploadedFile) {
                    continue;
                }

                $newGalleryPaths[] = $this->convertAndStoreGalleryImageAsWebp($tenantId, $uploadedGalleryImage);
            }
        }

        $combinedGalleryPaths = array_values(array_unique([
            ...$keptGalleryPaths,
            ...$newGalleryPaths,
        ]));

        return array_slice($combinedGalleryPaths, 0, 4);
    }

    private function convertAndStoreGalleryImageAsWebp(string $tenantId, UploadedFile $uploadedGalleryImage): string
    {
        if (! function_exists('imagewebp')) {
            throw ValidationException::withMessages([
                'gallery_images' => 'Server belum mendukung kompresi gambar WebP.',
            ]);
        }

        $sourcePath = $uploadedGalleryImage->getRealPath();
        if ($sourcePath === false || $sourcePath === '') {
            throw ValidationException::withMessages([
                'gallery_images' => 'Gagal membaca file gambar galeri.',
            ]);
        }

        $imageMeta = @getimagesize($sourcePath);
        if (! is_array($imageMeta)) {
            throw ValidationException::withMessages([
                'gallery_images' => 'Format gambar galeri tidak didukung.',
            ]);
        }

        $mime = strtolower(trim((string) ($imageMeta['mime'] ?? '')));
        $sourceImage = $this->createImageResourceFromMime($sourcePath, $mime);

        if (! is_object($sourceImage)) {
            throw ValidationException::withMessages([
                'gallery_images' => 'Gagal memproses gambar galeri.',
            ]);
        }

        $targetImage = $sourceImage;

        try {
            $sourceWidth = max(1, (int) imagesx($sourceImage));
            $sourceHeight = max(1, (int) imagesy($sourceImage));
            $maxDimension = 1920;

            if ($sourceWidth > $maxDimension || $sourceHeight > $maxDimension) {
                $scale = min($maxDimension / $sourceWidth, $maxDimension / $sourceHeight);
                $targetWidth = max(1, (int) floor($sourceWidth * $scale));
                $targetHeight = max(1, (int) floor($sourceHeight * $scale));

                $resizedImage = imagecreatetruecolor($targetWidth, $targetHeight);
                if (! is_object($resizedImage)) {
                    throw ValidationException::withMessages([
                        'gallery_images' => 'Gagal melakukan kompresi gambar galeri.',
                    ]);
                }

                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
                imagefill($resizedImage, 0, 0, $transparent);

                imagecopyresampled(
                    $resizedImage,
                    $sourceImage,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $sourceWidth,
                    $sourceHeight,
                );

                $targetImage = $resizedImage;
            }

            ob_start();
            $encoded = @imagewebp($targetImage, null, 82);
            $binaryImage = ob_get_clean();

            if (! $encoded || ! is_string($binaryImage) || $binaryImage === '') {
                throw ValidationException::withMessages([
                    'gallery_images' => 'Gagal mengompres gambar galeri ke WebP.',
                ]);
            }

            $path = "tenants/{$tenantId}/booking/gallery/".Str::uuid().'.webp';
            Storage::disk('public')->put($path, $binaryImage);

            return $path;
        } finally {
            if (is_object($targetImage) && $targetImage !== $sourceImage) {
                imagedestroy($targetImage);
            }

            if (is_object($sourceImage)) {
                imagedestroy($sourceImage);
            }
        }
    }

    private function createImageResourceFromMime(string $sourcePath, string $mime): mixed
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    private function normalizeGalleryPaths(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn ($path): string => trim((string) $path),
                $value,
            ), static fn (string $path): bool => $path !== ''));
        }

        if (is_string($value)) {
            $decodedValue = json_decode($value, true);
            if (is_array($decodedValue)) {
                return $this->normalizeGalleryPaths($decodedValue);
            }
        }

        return [];
    }

    /**
     * @param  array<int, string>  $galleryPaths
     * @return array<int, string>
     */
    private function resolveGalleryImageUrls(array $galleryPaths): array
    {
        return array_values(array_map(
            static fn (string $path): string => Storage::disk('public')->url($path),
            $galleryPaths,
        ));
    }

    private function resolvePublicBookingUrl(string $tenantId): string
    {
        $absoluteUrl = $this->tenantSubdomainService->buildTenantAbsoluteUrl($tenantId, '/booking');
        if (is_string($absoluteUrl) && trim($absoluteUrl) !== '') {
            return trim($absoluteUrl);
        }

        $rootHost = trim($this->tenantSubdomainService->resolveRootHost());
        if ($rootHost === '') {
            return '';
        }

        return "https://{$rootHost}/booking?tenant=".rawurlencode($tenantId);
    }

    private function assertBuilderTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('booking_page_settings')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function resolveCurrentUri(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }

    private function normalizeCtaSize(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['small', 'medium', 'large'], true)
            ? $normalized
            : 'medium';
    }

    private function resolvePublicTenantId(Request $request): ?string
    {
        $tenantFromSubdomain = $this->tenantSubdomainService->resolveTenantIdFromRequestHost($request);
        if (is_string($tenantFromSubdomain) && trim($tenantFromSubdomain) !== '') {
            return trim($tenantFromSubdomain);
        }

        $tenantHint = trim((string) $request->input('tenant', $request->query('tenant', '')));
        if ($tenantHint === '') {
            return null;
        }

        if (! Schema::hasTable('tenants')) {
            return null;
        }

        $resolvedTenantId = Tenant::query()
            ->where('id', $tenantHint)
            ->orWhere('subdomain', $tenantHint)
            ->value('id');

        if (is_string($resolvedTenantId) && trim($resolvedTenantId) !== '') {
            return trim($resolvedTenantId);
        }

        return null;
    }

    /**
     * @return array{id: string, name: string}
     */
    private function resolvePublicBookingWorkshop(string $tenantId): array
    {
        if ($tenantId === '' || ! Schema::hasTable('workshops')) {
            return [
                'id' => '',
                'name' => '',
            ];
        }

        $workshop = Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('created_at')
            ->first(['id', 'name']);

        if (! $workshop) {
            return [
                'id' => '',
                'name' => '',
            ];
        }

        return [
            'id' => (string) $workshop->id,
            'name' => trim((string) $workshop->name),
        ];
    }

    private function resolvePublicAvailabilityMessage(bool $isActive, bool $hasWorkshop): string
    {
        if (! $isActive) {
            return 'Halaman booking publik sedang dinonaktifkan oleh tenant.';
        }

        if (! $hasWorkshop) {
            return 'Workshop aktif belum tersedia untuk menerima booking.';
        }

        return '';
    }
}
