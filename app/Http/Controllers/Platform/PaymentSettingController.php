<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Payment\UpdatePaymentSettingRequest;
use App\Services\Platform\PlatformPaymentSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSettingController extends Controller
{
    public function index(Request $request, PlatformPaymentSettingService $platformPaymentSettingService): Response
    {
        return Inertia::render('Platform/PaymentSettings', [
            'user' => $request->user()?->only('name', 'email'),
            ...$platformPaymentSettingService->buildPageData(),
        ]);
    }

    public function update(
        UpdatePaymentSettingRequest $request,
        PlatformPaymentSettingService $platformPaymentSettingService,
    ): RedirectResponse {
        $platformPaymentSettingService->updatePaymentSettings($request->validated());

        return back()->with('status', 'Pengaturan pembayaran berhasil diperbarui.');
    }
}
