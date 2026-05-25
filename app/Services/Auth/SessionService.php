<?php

namespace App\Services\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Tenant\TenantSubdomainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionService
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    private const GENERIC_LOGIN_ERROR_MESSAGE = 'Email atau kata sandi tidak sesuai.';

    public function __construct(
        private readonly TenantSubdomainService $tenantSubdomainService,
    ) {}

    /**
     * @return array{redirect: string}
     */
    public function login(LoginRequest $request, DashboardRedirectService $dashboardRedirectService): array
    {
        $credentials = $request->validated();
        $throttleKey = $this->resolveLoginThrottleKey($request, (string) ($credentials['email'] ?? ''));

        $this->ensureIsNotRateLimited($throttleKey);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => self::GENERIC_LOGIN_ERROR_MESSAGE,
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $authenticatedUser = $request->user();

        if ($authenticatedUser instanceof User) {
            $this->ensureUserCanLoginFromCurrentDomain($request, $authenticatedUser);
        }

        return [
            'redirect' => $dashboardRedirectService->resolveForUser($request->user()),
        ];
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function ensureIsNotRateLimited(string $throttleKey): void
    {
        if (! RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($throttleKey);

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    private function resolveLoginThrottleKey(Request $request, string $email): string
    {
        return Str::lower(trim($email)).'|'.$request->ip();
    }

    private function ensureUserCanLoginFromCurrentDomain(Request $request, User $user): void
    {
        $tenantId = trim((string) data_get($user, 'tenant_id', ''));
        $requestedSubdomain = $this->tenantSubdomainService->extractTenantSubdomainFromRequestHost($request);
        $requestedSubdomainTenantId = $requestedSubdomain !== null
            ? trim((string) ($this->tenantSubdomainService->resolveTenantIdFromSubdomain($requestedSubdomain) ?? ''))
            : '';
        $isPlatformScopeUser = $this->isPlatformScopeUser($user);

        if ($requestedSubdomain !== null && $requestedSubdomainTenantId === '') {
            $this->rejectAuthenticatedLoginAttempt(
                $request,
                $user,
                'unknown_tenant_subdomain',
            );
        }

        if ($isPlatformScopeUser) {
            if ($requestedSubdomain !== null) {
                $this->rejectAuthenticatedLoginAttempt(
                    $request,
                    $user,
                    'platform_user_on_tenant_subdomain',
                );
            }

            return;
        }

        if ($tenantId === '') {
            return;
        }

        if ($requestedSubdomain === null) {
            $tenantLoginUrl = $this->tenantSubdomainService->buildTenantAbsoluteUrl($tenantId, '/login');
            if (is_string($tenantLoginUrl) && $tenantLoginUrl !== '') {
                $this->rejectAuthenticatedLoginAttempt(
                    $request,
                    $user,
                    'tenant_user_on_central_domain',
                );
            }

            return;
        }

        if ($requestedSubdomainTenantId !== $tenantId) {
            $this->rejectAuthenticatedLoginAttempt(
                $request,
                $user,
                'tenant_subdomain_mismatch',
            );
        }
    }

    private function rejectAuthenticatedLoginAttempt(Request $request, User $user, string $reason): void
    {
        $tenantId = trim((string) data_get($user, 'tenant_id', ''));

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::warning('auth.login.domain-mismatch', [
            'reason' => $reason,
            'user_id' => (string) data_get($user, 'id', ''),
            'tenant_id' => $tenantId !== '' ? $tenantId : null,
            'host' => trim((string) $request->getHost()),
            'ip' => trim((string) $request->ip()),
        ]);

        throw ValidationException::withMessages([
            'email' => self::GENERIC_LOGIN_ERROR_MESSAGE,
        ]);
    }

    private function isPlatformScopeUser(User $user): bool
    {
        return $this->userCan($user, 'platform.tenants.view')
            || $this->userCan($user, 'platform.tenants.manage')
            || $this->userCan($user, 'platform.billing.view')
            || $this->userCan($user, 'platform.billing.manage');
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
