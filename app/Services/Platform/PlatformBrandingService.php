<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PlatformBrandingService
{
    private const CACHE_KEY = 'platform.branding';

    private const DEFAULT_LOGO_BACKGROUND_COLOR = '#10B981';

    /**
     * @return array{
     *     appName: string,
     *     appLogoUrl: ?string,
     *     logoBackgroundEnabled: bool,
     *     logoBackgroundColor: string
     * }
     */
    public function sharedProps(): array
    {
        $branding = $this->resolveBranding();

        return [
            'appName' => $branding['app_name'],
            'appLogoUrl' => $branding['app_logo_url'],
            'logoBackgroundEnabled' => $branding['logo_background_enabled'],
            'logoBackgroundColor' => $branding['logo_background_color'],
        ];
    }

    /**
     * @return array{
     *     branding: array{
     *         app_name: string,
     *         app_logo_url: ?string,
     *         logo_background_enabled: bool,
     *         logo_background_color: string
     *     }
     * }
     */
    public function buildPageData(): array
    {
        return [
            'branding' => $this->resolveBranding(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateBranding(array $validated): void
    {
        if (! Schema::hasTable('platform_settings')) {
            throw ValidationException::withMessages([
                'app_name' => 'Tabel pengaturan platform belum siap.',
            ]);
        }

        $setting = $this->firstOrCreateSetting();

        $oldLogoPath = $this->normalizePath($setting->app_logo_path);
        $nextLogoPath = $oldLogoPath;
        $removeLogo = (bool) ($validated['remove_logo'] ?? false);

        if ($removeLogo) {
            $nextLogoPath = null;
        }

        $uploadedLogo = $validated['app_logo'] ?? null;
        if ($uploadedLogo instanceof UploadedFile) {
            $nextLogoPath = $uploadedLogo->store('platform/branding', 'public');
        }

        $payload = [
            'app_name' => $this->sanitizeAppName((string) ($validated['app_name'] ?? '')),
            'app_logo_path' => $nextLogoPath,
        ];

        if ($this->logoBackgroundColumnsReady()) {
            $payload['logo_background_enabled'] = (bool) ($validated['logo_background_enabled'] ?? true);
            $payload['logo_background_color'] = $this->sanitizeLogoBackgroundColor(
                (string) ($validated['logo_background_color'] ?? self::DEFAULT_LOGO_BACKGROUND_COLOR),
            );
        }

        $setting->forceFill($payload)->save();

        if ($oldLogoPath && $oldLogoPath !== $nextLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *     app_name: string,
     *     app_logo_url: ?string,
     *     logo_background_enabled: bool,
     *     logo_background_color: string
     * }
     */
    private function resolveBranding(): array
    {
        if (! Schema::hasTable('platform_settings')) {
            return $this->fallbackBranding();
        }

        return Cache::remember(self::CACHE_KEY, 3600, function (): array {
            $setting = $this->firstOrCreateSetting();
            $logoPath = $this->normalizePath($setting->app_logo_path);

            $logoBackgroundEnabled = $this->logoBackgroundColumnsReady()
                ? (bool) ($setting->logo_background_enabled ?? true)
                : true;
            $logoBackgroundColor = $this->logoBackgroundColumnsReady()
                ? $this->sanitizeLogoBackgroundColor((string) ($setting->logo_background_color ?? self::DEFAULT_LOGO_BACKGROUND_COLOR))
                : self::DEFAULT_LOGO_BACKGROUND_COLOR;

            return [
                'app_name' => $this->sanitizeAppName((string) $setting->app_name),
                'app_logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
                'logo_background_enabled' => $logoBackgroundEnabled,
                'logo_background_color' => $logoBackgroundColor,
            ];
        });
    }

    private function firstOrCreateSetting(): PlatformSetting
    {
        $setting = PlatformSetting::query()->first();
        if ($setting) {
            return $setting;
        }

        $payload = [
            'app_name' => $this->fallbackBranding()['app_name'],
            'app_logo_path' => null,
        ];

        if ($this->logoBackgroundColumnsReady()) {
            $payload['logo_background_enabled'] = true;
            $payload['logo_background_color'] = self::DEFAULT_LOGO_BACKGROUND_COLOR;
        }

        return PlatformSetting::query()->create($payload);
    }

    /**
     * @return array{
     *     app_name: string,
     *     app_logo_url: null,
     *     logo_background_enabled: bool,
     *     logo_background_color: string
     * }
     */
    private function fallbackBranding(): array
    {
        $fallbackName = trim((string) config('app.name', 'AutoServ'));

        return [
            'app_name' => $fallbackName !== '' ? $fallbackName : 'AutoServ',
            'app_logo_url' => null,
            'logo_background_enabled' => true,
            'logo_background_color' => self::DEFAULT_LOGO_BACKGROUND_COLOR,
        ];
    }

    private function sanitizeAppName(string $appName): string
    {
        $sanitized = trim(strip_tags($appName));

        return $sanitized !== '' ? $sanitized : $this->fallbackBranding()['app_name'];
    }

    private function normalizePath(mixed $value): ?string
    {
        $path = trim((string) ($value ?? ''));

        return $path !== '' ? $path : null;
    }

    private function sanitizeLogoBackgroundColor(string $hexColor): string
    {
        $normalized = strtoupper(trim($hexColor));
        if (preg_match('/^#[A-F0-9]{6}$/', $normalized) === 1) {
            return $normalized;
        }

        return self::DEFAULT_LOGO_BACKGROUND_COLOR;
    }

    private function logoBackgroundColumnsReady(): bool
    {
        return Schema::hasTable('platform_settings')
            && Schema::hasColumn('platform_settings', 'logo_background_enabled')
            && Schema::hasColumn('platform_settings', 'logo_background_color');
    }
}
