<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstimateApproval\RespondServiceOrderEstimateApprovalRequest;
use App\Services\Owner\OwnerServiceOrderEstimateService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EstimateApprovalController extends Controller
{
    public function show(
        string $token,
        OwnerServiceOrderEstimateService $estimateService,
    ): Response {
        return Inertia::render('EstimateApproval', [
            ...$estimateService->buildApprovalPageData($token),
        ]);
    }

    public function respond(
        RespondServiceOrderEstimateApprovalRequest $request,
        string $token,
        OwnerServiceOrderEstimateService $estimateService,
    ): RedirectResponse {
        $result = $estimateService->respondToApproval(
            $token,
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with([
            'status' => (string) ($result['message'] ?? 'Approval estimasi berhasil diproses.'),
            'status_level' => 'success',
        ]);
    }
}
