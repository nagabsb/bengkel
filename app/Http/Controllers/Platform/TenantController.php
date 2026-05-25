<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Tenant\ExportPlatformTenantRequest;
use App\Http\Requests\Platform\Tenant\StoreTenantRequest;
use App\Http\Requests\Platform\Tenant\ToggleTenantStatusRequest;
use App\Http\Requests\Platform\Tenant\UpdateTenantRequest;
use App\Services\Platform\PlatformTenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantController extends Controller
{
    public function index(Request $request, PlatformTenantService $platformTenantService): Response
    {
        return Inertia::render('Platform/Tenants', [
            'user' => $request->user()?->only('name', 'email'),
            ...$platformTenantService->buildPageData($request),
        ]);
    }

    public function store(
        StoreTenantRequest $request,
        PlatformTenantService $platformTenantService,
    ): RedirectResponse {
        $platformTenantService->createTenant($request->validated());

        return back()->with('status', 'Tenant baru berhasil ditambahkan.');
    }

    public function update(
        UpdateTenantRequest $request,
        string $tenant,
        PlatformTenantService $platformTenantService,
    ): RedirectResponse {
        $platformTenantService->updateTenant($tenant, $request->validated());

        return back()->with('status', 'Tenant berhasil diperbarui.');
    }

    public function updateStatus(
        ToggleTenantStatusRequest $request,
        string $tenant,
        PlatformTenantService $platformTenantService,
    ): RedirectResponse {
        $isActive = (bool) $request->validated('is_active');
        $platformTenantService->updateTenantStatus($tenant, $isActive);

        return back()->with(
            'status',
            $isActive
                ? 'Status tenant berhasil diaktifkan.'
                : 'Status tenant berhasil dinonaktifkan.',
        );
    }

    public function export(
        ExportPlatformTenantRequest $request,
        PlatformTenantService $platformTenantService,
    ): StreamedResponse {
        $filters = $platformTenantService->normalizeExportFilters($request->validated());
        $rows = $platformTenantService->buildTenantExportRows($filters);
        $spreadsheet = $platformTenantService->buildTenantExportSpreadsheet($rows);
        $generatedAt = now()->format('Ymd_His');
        $filename = "daftar_tenant_{$generatedAt}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
