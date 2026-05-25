<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Settings\UpdateOwnerPrintSettingRequest;
use App\Http\Requests\Owner\SyncOwnerPermissionsRequest;
use App\Services\Owner\OwnerPrintSettingService;
use App\Services\Owner\OwnerSettingsService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerSettingsService $ownerSettingsService,
        OwnerPrintSettingService $ownerPrintSettingService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        return Inertia::render('Owner/Settings', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerSettingsService->buildPageData($request, $tenantId, $planResolver),
            ...$ownerPrintSettingService->buildPageData($tenantId),
        ]);
    }

    public function syncPermissions(
        SyncOwnerPermissionsRequest $request,
        string $tenant,
        OwnerSettingsService $ownerSettingsService,
        TenantPlanResolver $planResolver,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $ownerSettingsService->syncRolePermissions(
            $tenantId,
            $request->validated('role_permissions', []),
            $planResolver,
        );

        return back()->with('status', 'Permission tim berhasil disinkronkan sesuai scope owner.');
    }

    public function updatePrintSetting(
        UpdateOwnerPrintSettingRequest $request,
        string $tenant,
        OwnerPrintSettingService $ownerPrintSettingService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $ownerPrintSettingService->updatePrintSetting($tenantId, $request->validated());

        return back()->with('status', 'Pengaturan nota thermal berhasil diperbarui.');
    }

    public function downloadKioskInstaller(
        Request $request,
        string $tenant,
        OwnerPrintSettingService $ownerPrintSettingService,
    ): StreamedResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $download = $ownerPrintSettingService->buildWindowsKioskInstallerDownload($request, $tenantId);

        return response()->streamDownload(
            static function () use ($download): void {
                echo (string) ($download['content'] ?? '');
            },
            (string) ($download['filename'] ?? 'autoserv-kiosk-installer.cmd'),
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ],
        );
    }
}
