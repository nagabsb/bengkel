<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SparePartUnit\StoreOwnerSparePartUnitRequest;
use App\Http\Requests\Owner\SparePartUnit\UpdateOwnerSparePartUnitRequest;
use App\Services\Owner\OwnerSparePartUnitService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SparePartUnitController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerSparePartUnitService $service,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        return Inertia::render('Owner/SparePartUnits', [
            'user' => $request->user()?->only('name', 'email'),
            ...$service->buildPageData(
                $request,
                $tenantId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerSparePartUnitRequest $request,
        string $tenant,
        OwnerSparePartUnitService $service,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $service->createUnit($tenantId, $request->validated());

        return back()->with('status', 'Satuan sparepart baru berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerSparePartUnitRequest $request,
        string $tenant,
        string $sparepart_unit,
        OwnerSparePartUnitService $service,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $service->updateUnit($tenantId, $sparepart_unit, $request->validated());

        return back()->with('status', 'Data satuan sparepart berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $sparepart_unit,
        OwnerSparePartUnitService $service,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $service->deleteUnit($tenantId, $sparepart_unit);

        return back()->with('status', 'Satuan sparepart berhasil dihapus.');
    }
}

