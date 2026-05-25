<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Booking\StoreOwnerBookingRequest;
use App\Http\Requests\Owner\Booking\UpdateOwnerBookingStatusRequest;
use App\Services\Owner\OwnerBookingService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerBookingService $ownerBookingService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        return Inertia::render('Owner/Bookings', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerBookingService->buildPageData(
                $request,
                $tenantId,
                (string) $request->attributes->get('tenant_workshop_id', $tenantId),
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerBookingRequest $request,
        string $tenant,
        OwnerBookingService $ownerBookingService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerBookingService->createBooking(
            $tenantId,
            $activeWorkshopId,
            $request->validated(),
            $request->user(),
        );

        return back()->with('status', 'Booking servis berhasil ditambahkan.');
    }

    public function updateStatus(
        UpdateOwnerBookingStatusRequest $request,
        string $tenant,
        string $booking,
        OwnerBookingService $ownerBookingService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $result = $ownerBookingService->updateBookingStatus(
            $tenantId,
            $activeWorkshopId,
            $booking,
            $request->validated(),
            $request->user(),
        );

        $message = (string) ($result['message'] ?? 'Status booking berhasil diperbarui.');
        $shouldRedirectToOrders = (bool) ($result['redirect_to_orders'] ?? false);
        $serviceOrderCode = trim((string) ($result['service_order_code'] ?? ''));

        if ($shouldRedirectToOrders && (bool) $request->user()?->can('service_orders.view')) {
            $redirectParams = ['tenant' => $tenant];

            if ($serviceOrderCode !== '') {
                $redirectParams['order_search'] = $serviceOrderCode;
            }

            return redirect()
                ->route('owner.orders.index', $redirectParams)
                ->with('status', $message);
        }

        return back()->with('status', $message);
    }
}
