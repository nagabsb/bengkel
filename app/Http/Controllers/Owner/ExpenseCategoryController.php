<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ExpenseCategory\StoreOwnerExpenseCategoryRequest;
use App\Http\Requests\Owner\ExpenseCategory\UpdateOwnerExpenseCategoryRequest;
use App\Services\Owner\OwnerExpenseCategoryService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerExpenseCategoryService $service,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        return Inertia::render('Owner/ExpenseCategories', [
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
        StoreOwnerExpenseCategoryRequest $request,
        string $tenant,
        OwnerExpenseCategoryService $service,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $service->createCategory($tenantId, $request->validated());

        return back()->with('status', 'Kategori pengeluaran baru berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerExpenseCategoryRequest $request,
        string $tenant,
        string $expense_category,
        OwnerExpenseCategoryService $service,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $service->updateCategory($tenantId, $expense_category, $request->validated());

        return back()->with('status', 'Data kategori pengeluaran berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $expense_category,
        OwnerExpenseCategoryService $service,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $service->deleteCategory($tenantId, $expense_category);

        return back()->with('status', 'Kategori pengeluaran berhasil dihapus.');
    }
}
