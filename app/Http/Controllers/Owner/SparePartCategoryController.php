<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SparePartCategory\StoreOwnerSparePartCategoryRequest;
use App\Http\Requests\Owner\SparePartCategory\UpdateOwnerSparePartCategoryRequest;
use App\Services\Owner\OwnerSparePartCategoryService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SparePartCategoryController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerSparePartCategoryService $service,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        return Inertia::render('Owner/SparePartCategories', [
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
        StoreOwnerSparePartCategoryRequest $request,
        string $tenant,
        OwnerSparePartCategoryService $service,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $service->createCategory($tenantId, $request->validated());

        return back()->with('status', 'Kategori sparepart baru berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerSparePartCategoryRequest $request,
        string $tenant,
        string $sparepart_category,
        OwnerSparePartCategoryService $service,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $service->updateCategory($tenantId, $sparepart_category, $request->validated());

        return back()->with('status', 'Data kategori sparepart berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $sparepart_category,
        OwnerSparePartCategoryService $service,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $service->deleteCategory($tenantId, $sparepart_category);

        return back()->with('status', 'Kategori sparepart berhasil dihapus.');
    }
}

