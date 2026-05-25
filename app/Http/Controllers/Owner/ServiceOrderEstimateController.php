<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ServiceOrder\GenerateOwnerServiceOrderDiagnosisAiRequest;
use App\Http\Requests\Owner\ServiceOrder\GenerateOwnerServiceOrderEstimateAiRequest;
use App\Http\Requests\Owner\ServiceOrder\StoreOwnerServiceOrderEstimateRequest;
use App\Services\Owner\OwnerServiceOrderDiagnosisAiService;
use App\Services\Owner\OwnerServiceOrderEstimateAiService;
use App\Services\Owner\OwnerServiceOrderEstimateService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;

class ServiceOrderEstimateController extends Controller
{
    public function generateDiagnosisDraft(
        GenerateOwnerServiceOrderDiagnosisAiRequest $request,
        string $tenant,
        string $order,
        OwnerServiceOrderDiagnosisAiService $diagnosisAiService,
        TenantPlanResolver $planResolver,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);
        $hasAiFeature = (bool) data_get(
            $planResolver->forTenantId($tenantId),
            'plan.has_ai_feature',
            false,
        );

        if (! $hasAiFeature) {
            return back()->withErrors([
                'diagnosis_ai' => 'Fitur AI belum tersedia pada paket aktif tenant. Silakan upgrade paket untuk memakai diagnosa AI.',
            ]);
        }

        $result = $diagnosisAiService->generateDraft(
            $tenantId,
            $activeWorkshopId,
            $order,
            $request->validated(),
            $request->user(),
        );

        return back()->with([
            'status' => 'Draft diagnosa AI berhasil dibuat. Silakan review sebelum dibagikan ke customer.',
            'status_level' => 'success',
            'ai_diagnosis_draft' => $result,
        ]);
    }

    public function generateAiDraft(
        GenerateOwnerServiceOrderEstimateAiRequest $request,
        string $tenant,
        string $order,
        OwnerServiceOrderEstimateAiService $estimateAiService,
        TenantPlanResolver $planResolver,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);
        $hasAiFeature = (bool) data_get(
            $planResolver->forTenantId($tenantId),
            'plan.has_ai_feature',
            false,
        );

        if (! $hasAiFeature) {
            return back()->withErrors([
                'estimate_ai' => 'Fitur AI belum tersedia pada paket aktif tenant. Silakan upgrade paket untuk memakai generate estimasi AI.',
            ]);
        }

        $result = $estimateAiService->generateDraft(
            $tenantId,
            $activeWorkshopId,
            $order,
            $request->validated(),
            $request->user(),
        );

        return back()->with([
            'status' => 'Draft estimasi AI berhasil dibuat. Silakan review sebelum disimpan.',
            'status_level' => 'success',
            'ai_estimate_draft' => $result,
        ]);
    }

    public function store(
        StoreOwnerServiceOrderEstimateRequest $request,
        string $tenant,
        string $order,
        OwnerServiceOrderEstimateService $estimateService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $result = $estimateService->createEstimate(
            $tenantId,
            $activeWorkshopId,
            $order,
            $request->validated(),
            $request->user(),
        );

        return back()->with([
            'status' => (string) ($result['message'] ?? 'Estimasi berhasil disimpan.'),
            'status_level' => 'success',
            'estimate_approval_link' => (string) ($result['approval_link'] ?? ''),
            'estimate_code' => (string) ($result['estimate_code'] ?? ''),
            'estimate_status' => (string) ($result['estimate_status'] ?? ''),
        ]);
    }
}
