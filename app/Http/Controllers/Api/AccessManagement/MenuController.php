<?php

namespace App\Http\Controllers\Api\AccessManagement;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    /**
     * GET /api/access-management/menus
     * Return all menus ordered by sort_order (flat list).
     */
    public function index(Request $request): JsonResponse
    {
        $allMenus = Menu::orderBy('sort_order')->orderBy('id')->get();

        if ($request->boolean('all')) {
            if (! $this->userHasPermission($request->user(), '/apps/access-management', 'can_view')) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            return response()->json($allMenus);
        }

        $visibleIds = collect($this->getAccessibleMenuIds($request->user()))
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
        $menusById = $allMenus->keyBy('id');

        foreach (array_keys($visibleIds) as $menuId) {
            $parentId = $menusById->get($menuId)?->parent_id;

            while ($parentId && ! isset($visibleIds[$parentId])) {
                $visibleIds[$parentId] = true;
                $parentId = $menusById->get($parentId)?->parent_id;
            }
        }

        $menus = $allMenus
            ->filter(fn (Menu $menu) => isset($visibleIds[$menu->id]))
            ->values();

        return response()->json($menus);
    }

    /**
     * POST /api/access-management/menus
     * Create a new menu item.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->userHasPermission($user, '/apps/access-management', 'can_add')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'label'       => ['required', 'string', 'max:100'],
            'slug'        => ['required', 'string', 'max:120', 'unique:menus,slug'],
            'url'         => ['nullable', 'string', 'max:255'],
            'icon'        => ['nullable', 'string', 'max:60'],
            'parent_id'   => ['nullable', 'integer', 'exists:menus,id'],
            'sort_order'  => ['nullable', 'integer'],
            'is_title'    => ['nullable', 'boolean'],
            'is_active'   => ['nullable', 'boolean'],
            'is_disabled' => ['nullable', 'boolean'],
            'is_special'  => ['nullable', 'boolean'],
            'badge_text'  => ['nullable', 'string', 'max:30'],
            'badge_class' => ['nullable', 'string', 'max:100'],
        ]);

        $menu = Menu::create($data);
        $this->notifyMenuChange($request, $menu, 'created');

        return response()->json($menu, 201);
    }

    /**
     * PUT /api/access-management/menus/{menu}
     * Update an existing menu item.
     */
    public function update(Request $request, Menu $menu): JsonResponse
    {
        $user = $request->user();
        if (! $this->userHasPermission($user, '/apps/access-management', 'can_edit')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'label'       => ['sometimes', 'required', 'string', 'max:100'],
            'slug'        => ['sometimes', 'required', 'string', 'max:120', 'unique:menus,slug,' . $menu->id],
            'url'         => ['nullable', 'string', 'max:255'],
            'icon'        => ['nullable', 'string', 'max:60'],
            'parent_id'   => ['nullable', 'integer', 'exists:menus,id'],
            'sort_order'  => ['nullable', 'integer'],
            'is_title'    => ['nullable', 'boolean'],
            'is_active'   => ['nullable', 'boolean'],
            'is_disabled' => ['nullable', 'boolean'],
            'is_special'  => ['nullable', 'boolean'],
            'badge_text'  => ['nullable', 'string', 'max:30'],
            'badge_class' => ['nullable', 'string', 'max:100'],
        ]);

        $menu->update($data);
        $this->notifyMenuChange($request, $menu, 'updated');

        return response()->json($menu);
    }

    /**
     * DELETE /api/access-management/menus/{menu}
     * Delete a menu item (children cascade via FK).
     */
    public function destroy(Request $request, Menu $menu): JsonResponse
    {
        $user = $request->user();
        if (! $this->userHasPermission($user, '/apps/access-management', 'can_delete')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->notifyMenuChange($request, $menu, 'deleted');
        $menu->delete();

        return response()->json(['message' => 'Menu deleted.']);
    }

    private function notifyMenuChange(Request $request, Menu $menu, string $action): void
    {
        $actor = $request->user();
        $title = match ($action) {
            'created' => 'Menu created',
            'deleted' => 'Menu deleted',
            default => 'Menu updated',
        };

        User::query()
            ->where('status', 'active')
            ->eachById(function (User $user) use ($actor, $menu, $action, $title) {
                $user->notify(new SystemNotification(
                    title: $title,
                    message: sprintf('"%s" was %s by %s.', $menu->label, $action, $actor->name),
                    eventType: 'menu.' . $action,
                    icon: 'menu-2',
                    color: $action === 'deleted' ? 'danger' : ($action === 'created' ? 'success' : 'primary'),
                    actionUrl: '/apps/access-management',
                    metadata: ['menu_id' => $menu->id, 'actor_id' => $actor->id],
                ));
            });
    }

}
