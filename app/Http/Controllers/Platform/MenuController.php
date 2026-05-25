<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Menu\ReorderSystemMenuRequest;
use App\Http\Requests\Platform\Menu\StoreSystemMenuRequest;
use App\Http\Requests\Platform\Menu\ToggleSystemMenuStatusRequest;
use App\Http\Requests\Platform\Menu\UpdateSystemMenuRequest;
use App\Services\Platform\MenuManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(Request $request, MenuManagementService $menuManagementService): Response
    {
        return Inertia::render('Platform/MenuManagement', [
            'user' => $request->user()?->only('name', 'email'),
            ...$menuManagementService->buildPageData(),
        ]);
    }

    public function store(
        StoreSystemMenuRequest $request,
        MenuManagementService $menuManagementService,
    ): RedirectResponse {
        $menuManagementService->createSystemMenu($request->validated());

        return back()->with('status', 'Menu system baru berhasil ditambahkan.');
    }

    public function updateStatus(
        ToggleSystemMenuStatusRequest $request,
        int $menu,
        MenuManagementService $menuManagementService,
    ): RedirectResponse {
        $isActive = (bool) $request->validated('is_active');
        $menuManagementService->updateSystemMenuStatus($menu, $isActive);

        return back()->with(
            'status',
            $isActive
                ? 'Status menu berhasil diaktifkan.'
                : 'Menu dan semua submenu berhasil dinonaktifkan.',
        );
    }

    public function reorder(
        ReorderSystemMenuRequest $request,
        MenuManagementService $menuManagementService,
    ): RedirectResponse {
        $menuManagementService->reorderSystemMenu($request->validated());

        return back()->with('status', 'Sort menu berhasil ditukar.');
    }

    public function update(
        UpdateSystemMenuRequest $request,
        int $menu,
        MenuManagementService $menuManagementService,
    ): RedirectResponse {
        $menuManagementService->updateSystemMenu($menu, $request->validated());

        return back()->with('status', 'Menu system berhasil diperbarui.');
    }

    public function destroy(int $menu, MenuManagementService $menuManagementService): RedirectResponse
    {
        $menuManagementService->deleteSystemMenu($menu);

        return back()->with('status', 'Menu system berhasil dihapus.');
    }
}

