<?php

namespace App\Services\Owner;

use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerWorkshopSwitcherService
{
    public const ALL_WORKSHOPS_ID = '__all__';

    /**
     * @return array<string, mixed>|null
     */
    public function resolveSwitcherState(Request $request, string $tenantId): ?array
    {
        if (! Schema::hasTable('workshops')) {
            $request->attributes->set('tenant_workshop_id', $tenantId);

            return null;
        }

        $workshops = $this->resolveTenantWorkshops($tenantId);
        if ($workshops->isEmpty()) {
            $request->attributes->set('tenant_workshop_id', $tenantId);

            return null;
        }

        $workshopOptions = $this->prependAllWorkshopOption($workshops);
        $activeWorkshopId = $this->resolveActiveWorkshopId($request, $tenantId, $workshops);
        $isAllWorkshopsActive = self::isAllWorkshopsId($activeWorkshopId);
        $activeWorkshop = $isAllWorkshopsActive
            ? [
                'id' => self::ALL_WORKSHOPS_ID,
                'name' => 'Semua Bengkel',
                'code' => 'GLOBAL',
                'is_primary' => false,
                'is_all' => true,
            ]
            : ($workshops->firstWhere('id', $activeWorkshopId) ?? $workshops->first());

        $request->attributes->set(
            'tenant_workshop_id',
            $isAllWorkshopsActive
                ? self::ALL_WORKSHOPS_ID
                : (string) ($activeWorkshop['id'] ?? $tenantId),
        );

        $switchRoute = null;
        if (Route::has('owner.workshops.switch-active')) {
            $switchRoute = route('owner.workshops.switch-active', ['tenant' => $tenantId], false);
        }

        return [
            'tenant_id' => $tenantId,
            'active_workshop_id' => $isAllWorkshopsActive
                ? self::ALL_WORKSHOPS_ID
                : (string) ($activeWorkshop['id'] ?? ''),
            'active_workshop_name' => (string) ($activeWorkshop['name'] ?? ''),
            'active_workshop_code' => (string) ($activeWorkshop['code'] ?? ''),
            'can_switch' => $workshops->count() > 1,
            'switch_route' => $switchRoute,
            'workshops' => $workshopOptions->values()->all(),
        ];
    }

    /**
     * @return array{id: string, name: string, code: string}
     */
    public function switchActiveWorkshop(Request $request, string $tenantId, string $workshopId): array
    {
        $normalizedWorkshopId = trim($workshopId);
        if ($normalizedWorkshopId === '') {
            throw ValidationException::withMessages([
                'workshop_id' => 'Bengkel tidak valid.',
            ]);
        }

        if (self::isAllWorkshopsId($normalizedWorkshopId)) {
            $request->session()->put($this->sessionKey($tenantId), self::ALL_WORKSHOPS_ID);
            $request->attributes->set('tenant_workshop_id', self::ALL_WORKSHOPS_ID);

            return [
                'id' => self::ALL_WORKSHOPS_ID,
                'name' => 'Semua Bengkel',
                'code' => 'GLOBAL',
            ];
        }

        $workshop = Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $normalizedWorkshopId)
            ->where('is_active', true)
            ->first();

        if (! $workshop) {
            throw ValidationException::withMessages([
                'workshop_id' => 'Bengkel tidak ditemukan atau tidak aktif.',
            ]);
        }

        $request->session()->put($this->sessionKey($tenantId), (string) $workshop->id);
        $request->attributes->set('tenant_workshop_id', (string) $workshop->id);

        return [
            'id' => (string) $workshop->id,
            'name' => (string) $workshop->name,
            'code' => (string) $workshop->code,
        ];
    }

    /**
     * @return Collection<int, array{id: string, name: string, code: string, is_primary: bool}>
     */
    private function resolveTenantWorkshops(string $tenantId): Collection
    {
        return Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$tenantId])
            ->orderBy('name')
            ->orderBy('created_at')
            ->get(['id', 'name', 'code'])
            ->map(fn (Workshop $workshop): array => [
                'id' => (string) $workshop->id,
                'name' => trim((string) $workshop->name),
                'code' => trim((string) $workshop->code),
                'is_primary' => (string) $workshop->id === $tenantId,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array{id: string, name: string, code: string, is_primary: bool}>  $workshops
     */
    private function resolveActiveWorkshopId(Request $request, string $tenantId, Collection $workshops): string
    {
        $availableWorkshopIds = $workshops
            ->pluck('id')
            ->map(fn (mixed $workshopId): string => trim((string) $workshopId))
            ->filter(fn (string $workshopId): bool => $workshopId !== '')
            ->values();

        $sessionWorkshopId = trim((string) $request->session()->get($this->sessionKey($tenantId), ''));
        if (self::isAllWorkshopsId($sessionWorkshopId) && $workshops->count() > 1) {
            return self::ALL_WORKSHOPS_ID;
        }

        if ($sessionWorkshopId !== '' && $availableWorkshopIds->contains($sessionWorkshopId)) {
            return $sessionWorkshopId;
        }

        if ($workshops->count() > 1) {
            $request->session()->put($this->sessionKey($tenantId), self::ALL_WORKSHOPS_ID);

            return self::ALL_WORKSHOPS_ID;
        }

        $fallbackWorkshopId = $availableWorkshopIds->first(fn (string $workshopId): bool => $workshopId === $tenantId);
        if (! is_string($fallbackWorkshopId) || $fallbackWorkshopId === '') {
            $fallbackWorkshopId = (string) ($availableWorkshopIds->first() ?? $tenantId);
        }

        $request->session()->put($this->sessionKey($tenantId), $fallbackWorkshopId);

        return $fallbackWorkshopId;
    }

    /**
     * @param  Collection<int, array{id: string, name: string, code: string, is_primary: bool}>  $workshops
     * @return Collection<int, array{id: string, name: string, code: string, is_primary: bool, is_all?: bool}>
     */
    private function prependAllWorkshopOption(Collection $workshops): Collection
    {
        if ($workshops->count() <= 1) {
            return $workshops->values();
        }

        return collect([[
            'id' => self::ALL_WORKSHOPS_ID,
            'name' => 'Semua Bengkel',
            'code' => 'GLOBAL',
            'is_primary' => false,
            'is_all' => true,
        ]])->concat($workshops->values());
    }

    public static function isAllWorkshopsId(?string $workshopId): bool
    {
        return trim((string) $workshopId) === self::ALL_WORKSHOPS_ID;
    }

    private function sessionKey(string $tenantId): string
    {
        return "owner.active_workshop.{$tenantId}";
    }
}
