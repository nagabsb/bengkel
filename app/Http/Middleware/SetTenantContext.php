<?php

namespace App\Http\Middleware;

use App\Services\Owner\OwnerWorkshopSwitcherService;
use App\Services\Tenant\TenantSubdomainService;
use App\Support\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OwnerWorkshopSwitcherService $workshopSwitcherService,
        private readonly TenantSubdomainService $tenantSubdomainService,
    ) {}

    /**
     * @param  string  $routeKey  Route param key, defaults to "tenant"
     */
    public function handle(Request $request, Closure $next, string $routeKey = 'tenant'): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $routeTenantId = $request->route($routeKey);
        $tenantId = is_string($routeTenantId) ? trim($routeTenantId) : '';
        $requestedSubdomain = $this->tenantSubdomainService->extractTenantSubdomainFromRequestHost($request);
        $subdomainTenantId = $requestedSubdomain !== null
            ? trim((string) ($this->tenantSubdomainService->resolveTenantIdFromSubdomain($requestedSubdomain) ?? ''))
            : '';

        if ($requestedSubdomain !== null && $subdomainTenantId === '') {
            abort(404, 'Tenant tidak ditemukan pada subdomain ini.');
        }

        if ($tenantId === '' && $subdomainTenantId !== '') {
            $tenantId = $subdomainTenantId;
            if ($request->route()) {
                $request->route()->setParameter($routeKey, $tenantId);
            }
        }

        if ($tenantId === '') {
            abort(404, 'Tenant tidak ditemukan.');
        }

        if ($subdomainTenantId !== '' && $subdomainTenantId !== $tenantId) {
            abort(404, 'Tenant tidak ditemukan pada subdomain ini.');
        }

        if (! $this->canAccessTenant($user, $tenantId)) {
            abort(403, 'Akses owner hanya untuk user tenant terkait.');
        }

        $this->tenantContext->setTenantId($tenantId);
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('tenant_workshop_id', $tenantId);

        $workshopSwitcherState = $this->workshopSwitcherService->resolveSwitcherState($request, $tenantId);
        $request->attributes->set('owner_workshop_switcher', $workshopSwitcherState);

        return $next($request);
    }

    private function canAccessTenant(mixed $user, string $tenantId): bool
    {
        if ($this->isPlatformScopeUser($user)) {
            return false;
        }

        $directTenantId = data_get($user, 'tenant_id');
        if (is_string($directTenantId) || is_int($directTenantId)) {
            return (string) $directTenantId === $tenantId;
        }

        $tenantIds = data_get($user, 'tenant_ids');
        if (is_array($tenantIds)) {
            return in_array($tenantId, array_map(static fn ($id) => (string) $id, $tenantIds), true);
        }

        return false;
    }

    private function isPlatformScopeUser(mixed $user): bool
    {
        if (! is_object($user) || ! method_exists($user, 'can')) {
            return false;
        }

        try {
            return $user->can('platform.tenants.view')
                || $user->can('platform.tenants.manage')
                || $user->can('platform.billing.view')
                || $user->can('platform.billing.manage');
        } catch (\Throwable) {
            return false;
        }
    }
}
