<?php

namespace App\Http\Middleware;

use App\Services\Owner\OwnerRouteAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerMenuAccess
{
    public function __construct(
        private readonly OwnerRouteAccessService $ownerRouteAccessService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($routeName === '' || ! str_starts_with($routeName, 'owner.')) {
            return $next($request);
        }

        $tenantFromAttribute = $request->attributes->get('tenant_id');
        $tenantFromRoute = $request->route('tenant');
        $tenantId = is_scalar($tenantFromAttribute)
            ? (string) $tenantFromAttribute
            : (is_scalar($tenantFromRoute) ? (string) $tenantFromRoute : '');

        if ($tenantId === '') {
            abort(404, 'Tenant tidak ditemukan.');
        }

        if (! $this->ownerRouteAccessService->canAccess($request, $tenantId, $request->user())) {
            abort(403, 'Menu ini tidak tersedia pada plan aktif tenant.');
        }

        return $next($request);
    }
}
