<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SparePart\StoreOwnerSparePartRequest;
use App\Http\Requests\Owner\SparePart\UpdateOwnerSparePartRequest;
use App\Services\Owner\OwnerSparePartService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SparePartController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerSparePartService $ownerSparePartService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        return Inertia::render('Owner/Spareparts', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerSparePartService->buildPageData(
                $request,
                $tenantId,
                $activeWorkshopId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerSparePartRequest $request,
        string $tenant,
        OwnerSparePartService $ownerSparePartService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerSparePartService->createSparePart($tenantId, $activeWorkshopId, $request->validated());

        return back()->with('status', 'Sparepart baru berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerSparePartRequest $request,
        string $tenant,
        string $sparepart,
        OwnerSparePartService $ownerSparePartService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerSparePartService->updateSparePart($tenantId, $activeWorkshopId, $sparepart, $request->validated());

        return back()->with('status', 'Data sparepart berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $sparepart,
        OwnerSparePartService $ownerSparePartService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerSparePartService->deleteSparePart($tenantId, $activeWorkshopId, $sparepart);

        return back()->with('status', 'Sparepart berhasil dihapus.');
    }
}
