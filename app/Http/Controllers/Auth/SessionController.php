<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\DashboardRedirectService;
use App\Services\Auth\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Index');
    }

    public function login(
        LoginRequest $request,
        SessionService $sessionService,
        DashboardRedirectService $dashboardRedirectService,
    ): RedirectResponse {
        $result = $sessionService->login($request, $dashboardRedirectService);

        return redirect()->to($result['redirect']);
    }

    public function logout(Request $request, SessionService $sessionService): RedirectResponse
    {
        $sessionService->logout($request);

        return redirect()->route('login');
    }
}
