<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Vehicle\StoreOwnerVehicleRequest;
use App\Http\Requests\Owner\Vehicle\UpdateOwnerVehicleRequest;
use App\Services\Owner\OwnerVehicleService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerVehicleService $ownerVehicleService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        return Inertia::render('Owner/Vehicles', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerVehicleService->buildPageData(
                $request,
                $tenantId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerVehicleRequest $request,
        string $tenant,
        OwnerVehicleService $ownerVehicleService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $ownerVehicleService->createVehicle($tenantId, $request->validated());

        return back()->with('status', 'Master kendaraan berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerVehicleRequest $request,
        string $tenant,
        string $vehicle,
        OwnerVehicleService $ownerVehicleService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $ownerVehicleService->updateVehicle($tenantId, $vehicle, $request->validated());

        return back()->with('status', 'Master kendaraan berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $vehicle,
        OwnerVehicleService $ownerVehicleService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $ownerVehicleService->deleteVehicle($tenantId, $vehicle);

        return back()->with('status', 'Master kendaraan berhasil dihapus.');
    }

    public function sync(
        Request $request,
        string $tenant,
        OwnerVehicleService $ownerVehicleService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $summary = $ownerVehicleService->syncFromPlatform($tenantId);

        $processed = (int) ($summary['created'] ?? 0) + (int) ($summary['updated'] ?? 0) + (int) ($summary['reactivated'] ?? 0);

        return back()->with(
            'status',
            "Sinkron master kendaraan selesai. {$processed} data diproses.",
        );
    }
}

