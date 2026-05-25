<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\HandleMidtransNotificationRequest;
use App\Services\Billing\TenantPlanSwitchPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MidtransWebhookController extends Controller
{
    public function notification(
        HandleMidtransNotificationRequest $request,
        TenantPlanSwitchPaymentService $tenantPlanSwitchPaymentService,
    ): JsonResponse {
        try {
            $tenantPlanSwitchPaymentService->handleMidtransNotification($request->validated());
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Notifikasi Midtrans tidak valid.',
            ], 403);
        } catch (\Throwable $exception) {
            Log::error('Gagal memproses notifikasi Midtrans.', [
                'tenant_id' => null,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal memproses notifikasi Midtrans.',
            ], 500);
        }

        return response()->json([
            'message' => 'OK',
        ]);
    }
}

