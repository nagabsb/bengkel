<?php

namespace App\Services\Dashboard;

use App\Services\Auth\DashboardRedirectService;
use Illuminate\Http\Request;

class DashboardService
{
    public function __construct(
        private readonly DashboardRedirectService $dashboardRedirectService,
    ) {
    }

    /**
     * @return array{target:string,user:array<string,mixed>|null}
     */
    public function buildDefaultDashboard(Request $request): array
    {
        return [
            'target' => $this->dashboardRedirectService->resolveForUser($request->user()),
            'user' => $request->user()?->only('name', 'email'),
        ];
    }
}

