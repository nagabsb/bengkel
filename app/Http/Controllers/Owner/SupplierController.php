<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Supplier\StoreOwnerSupplierRequest;
use App\Http\Requests\Owner\Supplier\UpdateOwnerSupplierRequest;
use App\Services\Owner\OwnerSupplierService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerSupplierService $ownerSupplierService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        return Inertia::render('Owner/Suppliers', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerSupplierService->buildPageData(
                $request,
                $tenantId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerSupplierRequest $request,
        string $tenant,
        OwnerSupplierService $ownerSupplierService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $ownerSupplierService->createSupplier($tenantId, $request->validated());

        return back()->with('status', 'Supplier baru berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerSupplierRequest $request,
        string $tenant,
        string $supplier,
        OwnerSupplierService $ownerSupplierService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $ownerSupplierService->updateSupplier($tenantId, $supplier, $request->validated());

        return back()->with('status', 'Data supplier berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $supplier,
        OwnerSupplierService $ownerSupplierService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $ownerSupplierService->deleteSupplier($tenantId, $supplier);

        return back()->with('status', 'Supplier berhasil dihapus.');
    }
}
