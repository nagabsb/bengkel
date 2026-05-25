<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\User\StoreOwnerUserRequest;
use App\Http\Requests\Owner\User\UpdateOwnerUserRequest;
use App\Services\Owner\OwnerUserService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(
        Request $request,
        string $tenant,
        OwnerUserService $ownerUserService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        return Inertia::render('Owner/Users', [
            'user' => $request->user()?->only('name', 'email'),
            ...$ownerUserService->buildPageData(
                $request,
                $tenantId,
                $activeWorkshopId,
                $planResolver,
                $request->user(),
            ),
        ]);
    }

    public function store(
        StoreOwnerUserRequest $request,
        string $tenant,
        OwnerUserService $ownerUserService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerUserService->createUser($tenantId, $activeWorkshopId, $request->validated());

        return back()->with('status', 'User baru berhasil ditambahkan.');
    }

    public function update(
        UpdateOwnerUserRequest $request,
        string $tenant,
        string $user,
        OwnerUserService $ownerUserService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerUserService->updateUser($tenantId, $activeWorkshopId, $user, $request->validated());

        return back()->with('status', 'Data user berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        string $tenant,
        string $user,
        OwnerUserService $ownerUserService,
    ): RedirectResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $activeWorkshopId = (string) $request->attributes->get('tenant_workshop_id', $tenantId);

        $ownerUserService->deleteUser(
            $tenantId,
            $activeWorkshopId,
            $user,
            (string) ($request->user()?->getAuthIdentifier() ?? ''),
        );

        return back()->with('status', 'User berhasil dihapus.');
    }
}
