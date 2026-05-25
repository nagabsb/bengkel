<?php

namespace App\Providers;

use App\Support\Tenant\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);

        // Redirect storage ke /tmp untuk Vercel (filesystem read-only di serverless)
        if ($storagePath = env('APP_STORAGE_PATH')) {
            $this->app->useStoragePath($storagePath);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale((string) config('app.locale'));

        RateLimiter::for('login', function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('web', function (Request $request): Limit {
            $tenantScope = (string) ($request->user()?->tenant_id ?? $request->ip());

            return Limit::perMinute(60)->by($tenantScope);
        });
    }
}
