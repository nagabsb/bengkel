<?php

use App\Http\Middleware\EnsureOwnerMenuAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeadersMiddleware::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/midtrans/notification',
        ]);

        $middleware->alias([
            'tenant.context' => SetTenantContext::class,
            'owner.menu.access' => EnsureOwnerMenuAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($response->getStatusCode() !== 403 || $request->expectsJson()) {
                return $response;
            }

            return Inertia::render('Errors/Forbidden', [
                'statusCode' => 403,
                'title' => 'Akses Ditolak',
                'message' => 'Anda tidak memiliki izin untuk mengakses halaman ini.',
            ])
                ->toResponse($request)
                ->setStatusCode(403);
        });
    })->create();
