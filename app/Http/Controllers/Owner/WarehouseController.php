<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Warehouse\StoreOwnerWarehouseRequest;
use App\Http\Requests\Owner\Warehouse\UpdateOwnerWarehouseRequest;
use App\Services\Owner\OwnerWarehouseService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerWarehouseService $ownerWarehouseService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        return Inertia::render('Owner/Warehouses', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerWarehouseService->buildPageData(
                $request,
                $tenantId,
                $activeWorkshopId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerWarehouseRequest $request,
        string $tenant,
        OwnerWarehouseService $ownerWarehouseService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerWarehouseService->createWarehouse($tenantId, $activeWorkshopId, $request->validated());

        return back()->with('status', 'Gudang baru berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerWarehouseRequest $request,
        string $tenant,
        string $warehouse,
        OwnerWarehouseService $ownerWarehouseService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerWarehouseService->updateWarehouse($tenantId, $activeWorkshopId, $warehouse, $request->validated());

        return back()->with('status', 'Data gudang berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $warehouse,
        OwnerWarehouseService $ownerWarehouseService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerWarehouseService->deleteWarehouse($tenantId, $activeWorkshopId, $warehouse);

        return back()->with('status', 'Gudang berhasil dihapus.');
    }
}
