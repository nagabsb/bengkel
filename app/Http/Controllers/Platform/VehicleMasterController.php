<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\VehicleMaster\ImportPlatformVehicleMasterRequest;
use App\Http\Requests\Platform\VehicleMaster\SyncPlatformVehicleMasterRequest;
use App\Services\Platform\PlatformVehicleMasterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class VehicleMasterController extends Controller
{
    public function index(Request $request, PlatformVehicleMasterService $platformVehicleMasterService): Response
    {
        return Inertia::render('Platform/VehicleMasters', [
            'user' => $request->user()?->only('name', 'email'),
            ...$platformVehicleMasterService->buildPageData($request),
        ]);
    }

    public function sync(
        SyncPlatformVehicleMasterRequest $request,
        PlatformVehicleMasterService $platformVehicleMasterService,
    ): RedirectResponse {
        try {
            $summary = $platformVehicleMasterService->syncFromPath($request->validated());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'sync_path' => $exception->getMessage(),
            ]);
        }

        $brandCount = (int) ($summary['brands_created'] ?? 0) + (int) ($summary['brands_updated'] ?? 0);
        $modelCount = (int) ($summary['models_created'] ?? 0) + (int) ($summary['models_updated'] ?? 0);

        return back()->with(
            'status',
            "Sinkron master kendaraan berhasil. {$brandCount} merek dan {$modelCount} model diproses.",
        );
    }

    public function import(
        ImportPlatformVehicleMasterRequest $request,
        PlatformVehicleMasterService $platformVehicleMasterService,
    ): RedirectResponse {
        try {
            $summary = $platformVehicleMasterService->syncFromUpload($request->validated());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'import_file' => $exception->getMessage(),
            ]);
        }

        $brandCount = (int) ($summary['brands_created'] ?? 0) + (int) ($summary['brands_updated'] ?? 0);
        $modelCount = (int) ($summary['models_created'] ?? 0) + (int) ($summary['models_updated'] ?? 0);

        return back()->with(
            'status',
            "Import master kendaraan berhasil. {$brandCount} merek dan {$modelCount} model diproses.",
        );
    }

    public function template(PlatformVehicleMasterService $platformVehicleMasterService): StreamedResponse
    {
        $payload = $platformVehicleMasterService->buildTemplatePayload();

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }, 'vehicle_master_template.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function export(Request $request, PlatformVehicleMasterService $platformVehicleMasterService): StreamedResponse
    {
        $activeOnly = $request->boolean('active_only', true);
        $payload = $platformVehicleMasterService->buildExportPayload($activeOnly);
        $filename = $activeOnly
            ? 'vehicle_master_export_active.json'
            : 'vehicle_master_export_all.json';

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
