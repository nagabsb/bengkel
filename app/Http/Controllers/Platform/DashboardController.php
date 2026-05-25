<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformDashboardService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        PlatformDashboardService $platformDashboardService,
        TenantPlanResolver $planResolver,
    ): Response {
        $dashboardData = $platformDashboardService->buildDashboardData($planResolver);

        return Inertia::render('Platform/Dashboard', [
            'user' => $request->user()?->only('name', 'email'),
            'tenants' => $dashboardData['tenants'],
            'dashboardSubtitle' => $dashboardData['dashboardSubtitle'],
            'stats' => $dashboardData['stats'],
            'chart' => $dashboardData['chart'],
            'categories' => $dashboardData['categories'],
            'table' => $dashboardData['table'],
            'activities' => $dashboardData['activities'],
        ]);
    }
}
