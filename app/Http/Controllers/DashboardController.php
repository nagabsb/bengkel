<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboardService): Response|RedirectResponse
    {
        $data = $dashboardService->buildDefaultDashboard($request);

        if ($data['target'] !== route('dashboard', absolute: false)) {
            return redirect()->to($data['target']);
        }

        return Inertia::render('Dashboard/Index', [
            'user' => $data['user'],
        ]);
    }
}

