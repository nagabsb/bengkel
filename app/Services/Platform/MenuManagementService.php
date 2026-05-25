<?php

namespace App\Services\Platform;

use App\Models\Menu;
use App\Models\Tenant;
use App\Services\Menu\SystemMenuPermissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MenuManagementService
{
    public function __construct(
        private readonly SystemMenuPermissionService $systemMenuPermissionService,
    ) {
    }

    /**
     * @return array{menus: array<int, array<string, mixed>>, tenantsCount: int}
     */
    public function buildPageData(): array
    {
        $menuTree = [];
        if (Schema::hasTable('menus')) {
            $menus = Menu::query()
                ->whereNull('tenant_id')
                ->where('menu_type', 'system')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get([
                    'id',
                    'parent_id',
                    'menu_type',
                    'label',
                    'route',
                    'icon',
                    'sort_order',
                    'is_active',
                ])
                ->map(function (Menu $menu): array {
                    return [
                        'id' => (int) $menu->id,
                        'parent_id' => $menu->parent_id ? (int) $menu->parent_id : null,
                        'menu_type' => (string) ($menu->menu_type ?? 'system'),
                        'label' => (string) $menu->label,
                        'route' => $menu->route ? (string) $menu->route : null,
                        'icon' => (string) ($menu->icon ?: 'dashboard'),
                        'sort_order' => (int) ($menu->sort_order ?? 0),
                        'is_active' => (bool) $menu->is_active,
                    ];
                });

            $menuTree = $this->buildTree($menus);
        }

        $tenantsCount = Schema::hasTable('tenants')
            ? (int) Tenant::query()->count()
            : 0;

        return [
            'menus' => $menuTree,
            'tenantsCount' => $tenantsCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createSystemMenu(array $validated): void
    {
        $this->assertMenusTableReady('create_menu', 'Tabel menu belum siap.');

        $parentId = $this->normalizeParentId($validated['parent_id'] ?? null);
        $this->validateParentAsSystem($parentId);

        $label = trim((string) $validated['label']);
        $route = $this->normalizeNullableString($validated['route'] ?? null);

        $menu = Menu::query()->create([
            'tenant_id' => null,
            'parent_id' => $parentId,
            'menu_type' => 'system',
            'label' => $label,
            'route' => $route,
            'icon' => $this->normalizeNullableString($validated['icon'] ?? null) ?: 'dashboard',
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ]);

        $this->systemMenuPermissionService->syncMenuPermissionMap((int) $menu->id, $label, $route);
    }

    public function updateSystemMenuStatus(int $menuId, bool $isActive): void
    {
        $this->assertMenusTableReady('status_menu', 'Tabel menu belum siap.');

        $menu = $this->findSystemMenuOrFail($menuId, 'status_menu', 'Menu system tidak ditemukan.');

        if ($isActive) {
            $menu->forceFill(['is_active' => true])->save();

            return;
        }

        $idsToDeactivate = $this->collectDescendantIds($menu->id);
        Menu::query()
            ->whereIn('id', $idsToDeactivate)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function reorderSystemMenu(array $validated): void
    {
        $this->assertMenusTableReady('reorder_menu', 'Tabel menu belum siap.');

        $sourceId = (int) $validated['source_id'];
        $targetId = (int) $validated['target_id'];

        $menus = Menu::query()
            ->whereIn('id', [$sourceId, $targetId])
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->get(['id', 'parent_id', 'sort_order'])
            ->keyBy('id');

        if (! $menus->has($sourceId) || ! $menus->has($targetId)) {
            throw ValidationException::withMessages([
                'reorder_menu' => 'Ada menu yang tidak valid untuk ditukar.',
            ]);
        }

        $sourceMenu = $menus->get($sourceId);
        $targetMenu = $menus->get($targetId);

        $sourceParentId = $sourceMenu->parent_id === null ? null : (int) $sourceMenu->parent_id;
        $targetParentId = $targetMenu->parent_id === null ? null : (int) $targetMenu->parent_id;
        if ($sourceParentId !== $targetParentId) {
            throw ValidationException::withMessages([
                'reorder_menu' => 'Drag & drop hanya bisa menukar menu pada parent yang sama.',
            ]);
        }

        $expectedParentId = $this->normalizeParentId($validated['parent_id'] ?? null);
        if ($expectedParentId !== $sourceParentId) {
            throw ValidationException::withMessages([
                'reorder_menu' => 'Parent menu tidak sesuai saat menyimpan pertukaran.',
            ]);
        }

        $sourceSort = (int) ($sourceMenu->sort_order ?? 0);
        $targetSort = (int) ($targetMenu->sort_order ?? 0);

        DB::transaction(function () use ($sourceId, $targetId, $sourceSort, $targetSort): void {
            Menu::query()
                ->where('id', $sourceId)
                ->update([
                    'sort_order' => $targetSort,
                    'updated_at' => now(),
                ]);

            Menu::query()
                ->where('id', $targetId)
                ->update([
                    'sort_order' => $sourceSort,
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateSystemMenu(int $menuId, array $validated): void
    {
        $this->assertMenusTableReady('update_menu', 'Tabel menu belum siap.');

        $menu = $this->findSystemMenuOrFail($menuId, 'update_menu', 'Menu system tidak ditemukan.');

        $parentId = $this->normalizeParentId($validated['parent_id'] ?? null);
        if ($parentId !== null) {
            if ($parentId === $menu->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Parent menu tidak boleh dirinya sendiri.',
                ]);
            }

            $this->validateParentAsSystem($parentId);
            $this->assertNoCircularHierarchy((int) $menu->id, $parentId);
        }

        $label = trim((string) $validated['label']);
        $route = $this->normalizeNullableString($validated['route'] ?? null);

        $menu->forceFill([
            'parent_id' => $parentId,
            'label' => $label,
            'route' => $route,
            'icon' => $this->normalizeNullableString($validated['icon'] ?? null) ?: 'dashboard',
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ])->save();

        $this->systemMenuPermissionService->syncMenuPermissionMap((int) $menu->id, $label, $route);
    }

    public function deleteSystemMenu(int $menuId): void
    {
        $this->assertMenusTableReady('delete_menu', 'Tabel menu belum siap.');

        $menu = $this->findSystemMenuOrFail($menuId, 'delete_menu', 'Menu system tidak ditemukan.');

        $hasChildren = Menu::query()
            ->where('parent_id', $menu->id)
            ->exists();

        if ($hasChildren) {
            throw ValidationException::withMessages([
                'delete_menu' => 'Menu masih memiliki submenu. Hapus submenu terlebih dahulu.',
            ]);
        }

        $menuPermissionIds = $this->systemMenuPermissionService->collectMenuPermissionIds((int) $menu->id);

        DB::transaction(function () use ($menu, $menuPermissionIds): void {
            $menu->delete();
            $this->systemMenuPermissionService->deleteOrphanPermissions($menuPermissionIds);
        });
    }

    private function assertMenusTableReady(string $key, string $message): void
    {
        if (! Schema::hasTable('menus')) {
            throw ValidationException::withMessages([
                $key => $message,
            ]);
        }
    }

    private function findSystemMenuOrFail(int $menuId, string $errorKey, string $message): Menu
    {
        $menu = Menu::query()
            ->where('id', $menuId)
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->first();

        if (! $menu) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }

        return $menu;
    }

    private function validateParentAsSystem(?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parentMenu = Menu::query()
            ->where('id', $parentId)
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->first();

        if (! $parentMenu) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent menu harus menu system level platform.',
            ]);
        }
    }

    private function assertNoCircularHierarchy(int $menuId, int $parentId): void
    {
        $visitedIds = [];
        $cursorId = $parentId;

        while ($cursorId !== null) {
            if ($cursorId === $menuId) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Parent menu tidak valid karena membuat struktur melingkar.',
                ]);
            }

            if (in_array($cursorId, $visitedIds, true)) {
                break;
            }

            $visitedIds[] = $cursorId;
            $cursorParentId = Menu::query()->where('id', $cursorId)->value('parent_id');
            $cursorId = is_numeric($cursorParentId) ? (int) $cursorParentId : null;
        }
    }

    /**
     * @return array<int, int>
     */
    private function collectDescendantIds(int $rootMenuId): array
    {
        $rows = Menu::query()
            ->whereNull('tenant_id')
            ->where('menu_type', 'system')
            ->get(['id', 'parent_id']);

        $childrenByParent = [];
        foreach ($rows as $row) {
            $parentKey = $row->parent_id === null ? 0 : (int) $row->parent_id;
            $childrenByParent[$parentKey] ??= [];
            $childrenByParent[$parentKey][] = (int) $row->id;
        }

        $ids = [];
        $queue = [$rootMenuId];
        while (count($queue) > 0) {
            $currentId = array_shift($queue);
            if (in_array($currentId, $ids, true)) {
                continue;
            }

            $ids[] = $currentId;
            foreach ($childrenByParent[$currentId] ?? [] as $childId) {
                $queue[] = (int) $childId;
            }
        }

        return $ids;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $menus
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $menus): array
    {
        $menusByParent = $menus->groupBy(fn (array $menu): int => (int) ($menu['parent_id'] ?? 0));

        $build = function (int $parentId) use (&$build, $menusByParent): array {
            return collect($menusByParent->get($parentId, []))
                ->map(fn (array $menu): array => [
                    ...$menu,
                    'children' => $build((int) $menu['id']),
                ])
                ->values()
                ->all();
        };

        return $build(0);
    }

    private function normalizeParentId(mixed $parentId): ?int
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        return (int) $parentId;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}



