<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\BookingPageBuilder\UpdateOwnerBookingPageBuilderRequest;
use App\Services\Owner\OwnerBookingPageBuilderService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingPageBuilderController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerBookingPageBuilderService $ownerBookingPageBuilderService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        return Inertia::render('Owner/BookingPageBuilder', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerBookingPageBuilderService->buildPageData(
                $request,
                $tenantId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function update(
        UpdateOwnerBookingPageBuilderRequest $request,
        string $tenant,
        OwnerBookingPageBuilderService $ownerBookingPageBuilderService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        $ownerBookingPageBuilderService->updateBuilderSetting(
            $tenantId,
            $request->validated(),
        );

        return back()->with('status', 'Pengaturan Page Builder berhasil disimpan.');
    }
}
