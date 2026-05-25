<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Invoice\MarkOwnerInvoiceReminderRequest;
use App\Http\Requests\Owner\Invoice\StoreOwnerInvoicePaymentRequest;
use App\Http\Requests\Owner\Invoice\UpdateOwnerInvoiceDueDateRequest;
use App\Services\Owner\OwnerInvoiceService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerInvoiceService $ownerInvoiceService,
        TenantPlanResolver $planResolver,
    ): Response {
        return $this->renderPage(
            $request,
            $tenant,
            $ownerInvoiceService,
            $planResolver,
            'invoices',
        );
    }

    public function payments(
        Request $request,
        string $tenant,
        OwnerInvoiceService $ownerInvoiceService,
        TenantPlanResolver $planResolver,
    ): Response {
        return $this->renderPage(
            $request,
            $tenant,
            $ownerInvoiceService,
            $planResolver,
            'payments',
        );
    }

    public function receivables(
        Request $request,
        string $tenant,
        OwnerInvoiceService $ownerInvoiceService,
        TenantPlanResolver $planResolver,
    ): Response {
        return $this->renderPage(
            $request,
            $tenant,
            $ownerInvoiceService,
            $planResolver,
            'receivables',
        );
    }

    public function storePayment(
        StoreOwnerInvoicePaymentRequest $request,
        string $tenant,
        string $invoice,
        OwnerInvoiceService $ownerInvoiceService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerInvoiceService->createPayment(
            $tenantId,
            $activeWorkshopId,
            $invoice,
            $request->validated(),
            $request->user(),
        );

        return back()->with('status', 'Pembayaran invoice berhasil disimpan.');
    }

    public function updateDueDate(
        UpdateOwnerInvoiceDueDateRequest $request,
        string $tenant,
        string $invoice,
        OwnerInvoiceService $ownerInvoiceService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerInvoiceService->updateDueDate(
            $tenantId,
            $activeWorkshopId,
            $invoice,
            $request->validated(),
        );

        return back()->with('status', 'Jatuh tempo invoice berhasil diperbarui.');
    }

    public function markReminder(
        MarkOwnerInvoiceReminderRequest $request,
        string $tenant,
        string $invoice,
        OwnerInvoiceService $ownerInvoiceService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerInvoiceService->markReminderSent(
            $tenantId,
            $activeWorkshopId,
            $invoice,
        );

        return back()->with('status', 'Reminder piutang berhasil ditandai terkirim.');
    }

    private function renderPage(
        Request $request,
        string $tenant,
        OwnerInvoiceService $ownerInvoiceService,
        TenantPlanResolver $planResolver,
        string $activeTab,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        return Inertia::render('Owner/Invoices', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerInvoiceService->buildPageData(
                $request,
                $tenantId,
                $activeWorkshopId,
                $planResolver,
                $request->user(),
                $activeTab,
            ),
        ]);
    }
}
