<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ServiceOrder\StoreOwnerServiceOrderRequest;
use App\Http\Requests\Owner\ServiceOrder\UpdateOwnerServiceOrderStatusRequest;
use App\Services\Owner\OwnerServiceOrderService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ServiceOrderController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerServiceOrderService $ownerServiceOrderService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        return Inertia::render('Owner/ServiceOrders', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerServiceOrderService->buildPageData(
                $request,
                $tenantId,
                $activeWorkshopId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerServiceOrderRequest $request,
        string $tenant,
        OwnerServiceOrderService $ownerServiceOrderService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerServiceOrderService->createServiceOrder(
            $tenantId,
            $activeWorkshopId,
            $request->validated(),
            $request->user(),
        );

        return back()->with('status', 'Data servis berhasil disimpan.');
    }

    public function updateStatus(
        UpdateOwnerServiceOrderStatusRequest $request,
        string $tenant,
        string $order,
        OwnerServiceOrderService $ownerServiceOrderService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);
        $validated = $request->validated();

        Log::info('owner.orders.update-status.request', [
            'tenant_id' => $tenantId,
            'workshop_id' => $activeWorkshopId,
            'order_id' => $order,
            'status' => (string) ($validated['status'] ?? ''),
            'user_id' => (string) ($request->user()?->getAuthIdentifier() ?? ''),
        ]);

        try {
            $result = $ownerServiceOrderService->updateOrderStatus(
                $tenantId,
                $activeWorkshopId,
                $order,
                $validated,
                $request->user(),
            );
        } catch (ValidationException $validationException) {
            Log::warning('owner.orders.update-status.validation-failed', [
                'tenant_id' => $tenantId,
                'workshop_id' => $activeWorkshopId,
                'order_id' => $order,
                'errors' => $validationException->errors(),
                'user_id' => (string) ($request->user()?->getAuthIdentifier() ?? ''),
            ]);

            throw $validationException;
        } catch (Throwable $throwable) {
            Log::error('owner.orders.update-status.unhandled', [
                'tenant_id' => $tenantId,
                'workshop_id' => $activeWorkshopId,
                'order_id' => $order,
                'status' => (string) ($validated['status'] ?? ''),
                'message' => $throwable->getMessage(),
                'user_id' => (string) ($request->user()?->getAuthIdentifier() ?? ''),
            ]);

            return back()->withErrors([
                'update_order_status' => 'Status servis gagal diperbarui. Silakan coba lagi.',
            ]);
        }

        return back()->with(
            'status',
            (string) ($result['message'] ?? 'Status servis berhasil diperbarui.'),
        );
    }
}
