<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Application\UpdateApplicationSettingRequest;
use App\Services\Platform\PlatformBrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationSettingController extends Controller
{
    public function index(Request $request, PlatformBrandingService $platformBrandingService): Response
    {
        return Inertia::render('Platform/ApplicationSettings', [
            'user' => $request->user()?->only('name', 'email'),
            ...$platformBrandingService->buildPageData(),
        ]);
    }

    public function update(
        UpdateApplicationSettingRequest $request,
        PlatformBrandingService $platformBrandingService,
    ): RedirectResponse {
        $platformBrandingService->updateBranding($request->validated());

        return back()->with('status', 'Pengaturan aplikasi berhasil diperbarui.');
    }
}
