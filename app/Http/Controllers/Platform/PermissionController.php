<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SyncPlatformPermissionsRequest;
use App\Services\Platform\PlatformPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(Request $request, PlatformPermissionService $platformPermissionService): Response
    {
        return Inertia::render('Platform/Permissions', [
            'user' => $request->user()?->only('name', 'email'),
            ...$platformPermissionService->buildPageData($request),
        ]);
    }

    public function sync(
        SyncPlatformPermissionsRequest $request,
        PlatformPermissionService $platformPermissionService,
    ): RedirectResponse {
        $platformPermissionService->syncTemplateRolePermissions(
            $request->validated('role_permissions', []),
        );

        return back()->with('status', 'Template permission role berhasil disinkronkan ke semua tenant.');
    }
}

