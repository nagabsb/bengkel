<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Owner\OwnerRouteAccessService;
use App\Services\Tenant\TenantSubdomainService;

class DashboardRedirectService
{
    public function __construct(
        private readonly OwnerRouteAccessService $ownerRouteAccessService,
        private readonly TenantSubdomainService $tenantSubdomainService,
    ) {}

    public function resolveForUser(?User $user): string
    {
        if (! $user) {
            return route('dashboard', absolute: false);
        }

        if ($this->userCan($user, 'platform.tenants.view')) {
            return route('platform.dashboard', absolute: false);
        }

        $tenantId = data_get($user, 'tenant_id');
        if ($this->userCan($user, 'owner.dashboard.view') && is_string($tenantId) && $tenantId !== '') {
            if ($this->ownerRouteAccessService->canAccessRouteName('owner.dashboard', [], $tenantId, $user)) {
                $subdomainOwnerUrl = $this->tenantSubdomainService->buildTenantAbsoluteUrl(
                    $tenantId,
                    '/owner/dashboard',
                );

                if (is_string($subdomainOwnerUrl) && $subdomainOwnerUrl !== '') {
                    return $subdomainOwnerUrl;
                }

                return route('owner.dashboard', ['tenant' => $tenantId], false);
            }
        }

        return route('dashboard', absolute: false);
    }

    private function userCan(User $user, string $permission): bool
    {
        if (! method_exists($user, 'can')) {
            return false;
        }

        try {
            return $user->can($permission);
        } catch (\Throwable) {
            return false;
        }
    }
}


