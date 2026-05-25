<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Expense\StoreOwnerExpenseRequest;
use App\Http\Requests\Owner\Expense\UpdateOwnerExpenseRequest;
use App\Services\Owner\OwnerExpenseService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerExpenseService $ownerExpenseService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        return Inertia::render('Owner/Expenses', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerExpenseService->buildPageData(
                $request,
                $tenantId,
                $activeWorkshopId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerExpenseRequest $request,
        string $tenant,
        OwnerExpenseService $ownerExpenseService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerExpenseService->createExpense(
            $tenantId,
            $activeWorkshopId,
            $request->validated(),
            $request->user(),
        );

        return back()->with('status', 'Pengeluaran baru berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerExpenseRequest $request,
        string $tenant,
        string $expense,
        OwnerExpenseService $ownerExpenseService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerExpenseService->updateExpense($tenantId, $activeWorkshopId, $expense, $request->validated());

        return back()->with('status', 'Data pengeluaran berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $expense,
        OwnerExpenseService $ownerExpenseService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerExpenseService->deleteExpense($tenantId, $activeWorkshopId, $expense);

        return back()->with('status', 'Pengeluaran berhasil dihapus.');
    }
}
