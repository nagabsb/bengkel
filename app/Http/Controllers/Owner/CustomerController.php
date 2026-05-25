<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Customer\StoreOwnerCustomerRequest;
use App\Http\Requests\Owner\Customer\UpdateOwnerCustomerRequest;
use App\Services\Owner\OwnerCustomerService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerCustomerService $ownerCustomerService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        return Inertia::render('Owner/Customers', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerCustomerService->buildPageData(
                $request,
                $tenantId,
                $activeWorkshopId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerCustomerRequest $request,
        string $tenant,
        OwnerCustomerService $ownerCustomerService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerCustomerService->createCustomer($tenantId, $activeWorkshopId, $request->validated());

        return back()->with('status', 'Customer baru berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerCustomerRequest $request,
        string $tenant,
        string $customer,
        OwnerCustomerService $ownerCustomerService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerCustomerService->updateCustomer($tenantId, $activeWorkshopId, $customer, $request->validated());

        return back()->with('status', 'Data customer berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $customer,
        OwnerCustomerService $ownerCustomerService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerCustomerService->deleteCustomer($tenantId, $activeWorkshopId, $customer);

        return back()->with('status', 'Customer berhasil dihapus.');
    }
}
