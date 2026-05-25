<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\Owner\OwnerDashboardService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerDashboardService $ownerDashboardService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);
        $currentUri = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());
        if ($queryString !== '') {
            $currentUri .= '?'.$queryString;
        }

        $dashboardData = $ownerDashboardService->buildDashboardData(
            $tenantId,
            $activeWorkshopId,
            $planResolver,
            $request->user(),
            $currentUri,
        );

        $user = $request->user();

        return Inertia::render('Owner/Dashboard', [
            'user' => $user?->only('name', 'email', 'role', 'user_type'),
            'roleLabel' => $this->resolveRoleLabel($user),
            'tenantId' => $dashboardData['tenantId'],
            'package' => $dashboardData['package'],
            'menuItems' => $dashboardData['menuItems'],
            'dashboardSubtitle' => $dashboardData['dashboardSubtitle'],
            'stats' => $dashboardData['stats'],
            'chart' => $dashboardData['chart'],
            'categories' => $dashboardData['categories'],
            'table' => $dashboardData['table'],
            'activities' => $dashboardData['activities'],
            'visibility' => $dashboardData['visibility'] ?? [],
        ]);
    }

    private function resolveRoleLabel(?Authenticatable $user): string
    {
        $role = strtolower(trim((string) data_get($user, 'role')));
        if ($role === '') {
            $role = strtolower(trim((string) data_get($user, 'user_type')));
        }

        return match ($role) {
            'superadmin' => 'Superadmin',
            'owner' => 'Owner',
            'admin' => 'Admin',
            'kasir' => 'Kasir',
            'mekanik' => 'Mekanik',
            default => 'Owner',
        };
    }
}
