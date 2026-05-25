<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Plan\StorePlanRequest;
use App\Http\Requests\Platform\Plan\TogglePlanStatusRequest;
use App\Http\Requests\Platform\Plan\UpdatePlanRequest;
use App\Services\Platform\PlatformPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(Request $request, PlatformPlanService $platformPlanService): Response
    {
        return Inertia::render('Platform/Plans', [
            'user' => $request->user()?->only('name', 'email'),
            ...$platformPlanService->buildPageData($request),
        ]);
    }

    public function store(
        StorePlanRequest $request,
        PlatformPlanService $platformPlanService,
    ): RedirectResponse {
        $platformPlanService->createPlan($request->validated());

        return back()->with('status', 'Plan baru berhasil ditambahkan.');
    }

    public function update(
        UpdatePlanRequest $request,
        int $plan,
        PlatformPlanService $platformPlanService,
    ): RedirectResponse {
        $platformPlanService->updatePlan($plan, $request->validated());

        return back()->with('status', 'Plan berhasil diperbarui.');
    }

    public function updateStatus(
        TogglePlanStatusRequest $request,
        int $plan,
        PlatformPlanService $platformPlanService,
    ): RedirectResponse {
        $isActive = (bool) $request->validated('is_active');
        $platformPlanService->updatePlanStatus($plan, $isActive);

        return back()->with(
            'status',
            $isActive
                ? 'Status plan berhasil diaktifkan.'
                : 'Status plan berhasil dinonaktifkan.',
        );
    }
}

