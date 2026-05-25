<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Workshop\ConfirmOwnerWorkshopPaymentRequest;
use App\Http\Requests\Owner\Workshop\SwitchOwnerActiveWorkshopRequest;
use App\Http\Requests\Owner\Workshop\SwitchOwnerWorkshopPlanRequest;
use App\Http\Requests\Owner\Workshop\StoreOwnerWorkshopRequest;
use App\Http\Requests\Owner\Workshop\UpdateOwnerWorkshopRequest;
use App\Services\Billing\TenantPlanSwitchPaymentService;
use App\Services\Owner\OwnerWorkshopService;
use App\Services\Owner\OwnerWorkshopSwitcherService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkshopController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerWorkshopService $ownerWorkshopService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        return Inertia::render('Owner/Workshops', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerWorkshopService->buildPageData(
                $request,
                $tenantId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerWorkshopRequest $request,
        string $tenant,
        OwnerWorkshopService $ownerWorkshopService,
        TenantPlanResolver $planResolver,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $ownerWorkshopService->createWorkshop($tenantId, $request->validated(), $planResolver);

        return back()->with('status', 'Bengkel baru berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerWorkshopRequest $request,
        string $tenant,
        string $workshop,
        OwnerWorkshopService $ownerWorkshopService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $ownerWorkshopService->updateWorkshop($tenantId, $workshop, $request->validated());

        return back()->with('status', 'Data bengkel berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $workshop,
        OwnerWorkshopService $ownerWorkshopService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $ownerWorkshopService->deleteWorkshop($tenantId, $workshop);

        return back()->with('status', 'Bengkel berhasil dihapus.');
    }

    public function switchPlan(
        SwitchOwnerWorkshopPlanRequest $request,
        string $tenant,
        OwnerWorkshopService $ownerWorkshopService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $switchResult = $ownerWorkshopService->switchPlan(
            $tenantId,
            $request->validated(),
            $request->user(),
        );

        $response = back()->with('status', (string) ($switchResult['status_message'] ?? 'Permintaan upgrade plan diproses.'));
        $paymentRedirectUrl = trim((string) ($switchResult['payment_redirect_url'] ?? ''));
        if ($paymentRedirectUrl !== '') {
            $response->with('payment_redirect_url', $paymentRedirectUrl);
        }
        $paymentSnapToken = trim((string) ($switchResult['payment_snap_token'] ?? ''));
        if ($paymentSnapToken !== '') {
            $response->with('payment_snap_token', $paymentSnapToken);
        }
        $paymentOpenMode = trim((string) ($switchResult['payment_open_mode'] ?? ''));
        if ($paymentOpenMode !== '') {
            $response->with('payment_open_mode', $paymentOpenMode);
        }
        $paymentOrderId = trim((string) ($switchResult['payment_order_id'] ?? ''));
        if ($paymentOrderId !== '') {
            $response->with('payment_order_id', $paymentOrderId);
        }

        return $response;
    }

    public function switchActiveWorkshop(
        SwitchOwnerActiveWorkshopRequest $request,
        string $tenant,
        OwnerWorkshopSwitcherService $workshopSwitcherService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $workshopId = (string) ($request->validated('workshop_id') ?? '');

        $activeWorkshop = $workshopSwitcherService->switchActiveWorkshop($request, $tenantId, $workshopId);

        return back()->with(
            'status',
            'Bengkel aktif diganti ke '.(string) ($activeWorkshop['name'] ?? 'bengkel terpilih').'.',
        );
    }

    public function continuePendingPayment(
        Request $request,
        string $tenant,
        OwnerWorkshopService $ownerWorkshopService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $pendingPayment = $ownerWorkshopService->resolvePendingMidtransPaymentData($tenantId);
        $redirectUrl = trim((string) data_get($pendingPayment, 'redirect_url', ''));
        $orderId = trim((string) data_get($pendingPayment, 'order_id', ''));

        $response = back();
        if ($redirectUrl !== '') {
            $response
                ->with('status', 'Tagihan Midtrans pending ditemukan. Lanjutkan pembayaran.')
                ->with('payment_redirect_url', $redirectUrl)
                ->with('payment_open_mode', 'redirect');
            if ($orderId !== '') {
                $response->with('payment_order_id', $orderId);
            }

            return $response;
        }

        return $response->with('status', 'Tidak ada tagihan Midtrans pending.');
    }

    public function confirmMidtransPayment(
        ConfirmOwnerWorkshopPaymentRequest $request,
        string $tenant,
        TenantPlanSwitchPaymentService $tenantPlanSwitchPaymentService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $validated = $request->validated();
        $orderId = trim((string) ($validated['order_id'] ?? ''));
        $isSilent = (bool) ($validated['silent'] ?? false);

        try {
            $payment = $tenantPlanSwitchPaymentService->syncMidtransPaymentByOrderId($tenantId, $orderId);
        } catch (\Throwable) {
            return $isSilent
                ? back()
                : back()->with('status', 'Sinkronisasi status pembayaran sedang bermasalah. Silakan coba lagi.');
        }

        if (! $payment) {
            return $isSilent
                ? back()
                : back()->with('status', 'Tagihan Midtrans tidak ditemukan.');
        }

        $paymentStatus = (string) ($payment->status ?? '');
        if ($paymentStatus === 'paid') {
            return $isSilent
                ? back()
                : back()->with('status', 'Pembayaran berhasil diverifikasi. Paket sudah diperbarui.');
        }

        if ($paymentStatus === 'pending') {
            return $isSilent
                ? back()
                : back()->with('status', 'Pembayaran masih pending. Silakan selesaikan sesuai instruksi Midtrans.');
        }

        return $isSilent
            ? back()
            : back()->with('status', 'Status pembayaran terbaru: '.$paymentStatus.'.');
    }
}
