<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TenantSubdomainService
{
    /**
     * @var array<string, string|null>
     */
    private array $tenantIdBySubdomainCache = [];

    public function resolveTenantIdFromRequestHost(Request $request): ?string
    {
        $subdomain = $this->extractTenantSubdomainFromRequestHost($request);
        if ($subdomain === null) {
            return null;
        }

        return $this->resolveTenantIdFromSubdomain($subdomain);
    }

    public function extractTenantSubdomainFromRequestHost(Request $request): ?string
    {
        return $this->extractTenantSubdomainFromHost($this->resolveRequestHost($request));
    }

    public function resolveTenantIdFromSubdomain(string $subdomain): ?string
    {
        $normalizedSubdomain = trim(strtolower($subdomain));
        if ($normalizedSubdomain === '') {
            return null;
        }

        if (array_key_exists($normalizedSubdomain, $this->tenantIdBySubdomainCache)) {
            return $this->tenantIdBySubdomainCache[$normalizedSubdomain];
        }

        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'subdomain')) {
            $this->tenantIdBySubdomainCache[$normalizedSubdomain] = null;

            return null;
        }

        $tenantId = Tenant::query()
            ->where('subdomain', $normalizedSubdomain)
            ->value('id');

        $resolvedTenantId = is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
        $this->tenantIdBySubdomainCache[$normalizedSubdomain] = $resolvedTenantId;

        return $resolvedTenantId;
    }

    public function buildTenantAbsoluteUrl(string $tenantId, string $path): ?string
    {
        $normalizedTenantId = trim($tenantId);
        if ($normalizedTenantId === '') {
            return null;
        }

        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'subdomain')) {
            return null;
        }

        $subdomain = trim((string) Tenant::query()
            ->where('id', $normalizedTenantId)
            ->value('subdomain'));

        if ($subdomain === '') {
            return null;
        }

        $rootHost = $this->resolveRootHost();
        if ($rootHost === '') {
            return null;
        }

        $scheme = $this->resolveAppScheme();
        $normalizedPath = '/'.ltrim($path, '/');
        $normalizedPath = preg_replace('#/+#', '/', $normalizedPath);
        $normalizedPath = is_string($normalizedPath) && $normalizedPath !== '' ? $normalizedPath : '/';

        return "{$scheme}://{$subdomain}.{$rootHost}{$normalizedPath}";
    }

    private function extractTenantSubdomainFromHost(string $host): ?string
    {
        $normalizedHost = trim(strtolower($host));
        $rootHost = $this->resolveRootHost();

        if ($normalizedHost === '' || $rootHost === '' || $normalizedHost === $rootHost) {
            return null;
        }

        $suffix = '.'.$rootHost;
        if (! str_ends_with($normalizedHost, $suffix)) {
            return null;
        }

        $subdomain = substr($normalizedHost, 0, -strlen($suffix));
        if (! is_string($subdomain)) {
            return null;
        }

        $normalizedSubdomain = trim($subdomain, '.');
        if ($normalizedSubdomain === '' || str_contains($normalizedSubdomain, '.')) {
            return null;
        }

        return $normalizedSubdomain;
    }

    public function resolveRootHost(): string
    {
        $appUrl = trim((string) config('app.url', ''));
        $host = trim((string) parse_url($appUrl, PHP_URL_HOST));
        if ($host === '') {
            try {
                $requestHost = trim((string) request()->getHost());
                if ($requestHost !== '') {
                    $host = $requestHost;
                }
            } catch (\Throwable) {
                // Ignore request fallback when request instance is unavailable.
            }
        }

        return strtolower($host);
    }

    private function resolveRequestHost(Request $request): string
    {
        $host = trim((string) $request->header('host', ''));
        if ($host === '') {
            $host = trim((string) $request->server->get('HTTP_HOST', ''));
        }

        if ($host === '') {
            $host = trim((string) $request->getHost());
        }

        if (str_contains($host, ':')) {
            [$hostWithoutPort] = explode(':', $host, 2);
            $host = trim($hostWithoutPort);
        }

        return $host;
    }

    private function resolveAppScheme(): string
    {
        $appUrl = trim((string) config('app.url', ''));
        $appScheme = trim((string) parse_url($appUrl, PHP_URL_SCHEME));

        try {
            $requestScheme = trim((string) request()->getScheme());
            if (strtolower($requestScheme) === 'https') {
                return strtolower($requestScheme);
            }
        } catch (\Throwable) {
            // Ignore request fallback when request instance is unavailable.
        }

        if ($appScheme !== '') {
            return strtolower($appScheme);
        }

        try {
            $requestScheme = trim((string) request()->getScheme());
            if ($requestScheme !== '') {
                return strtolower($requestScheme);
            }
        } catch (\Throwable) {
            // Ignore request fallback when request instance is unavailable.
        }

        return 'https';
    }
}
