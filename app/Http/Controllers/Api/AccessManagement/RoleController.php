<?php

namespace App\Http\Controllers\Api\AccessManagement;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /** GET /access-management/roles */
    public function index(): JsonResponse
    {
        $roles = Role::withCount('users')->orderBy('name')->get();

        return response()->json($roles);
    }

    /** POST /access-management/roles */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->userHasPermission($user, '/apps/access-management/roles', 'can_add')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:80', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'key_responsibilities' => ['nullable', 'string', 'max:5000'],
            'icon' => ['nullable', 'string', 'max:120'],
            'user_ids'    => ['nullable', 'array'],
            'user_ids.*'  => ['integer', 'exists:users,id'],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'key_responsibilities' => $data['key_responsibilities'] ?? null,
            'icon' => $this->normalizeRoleIcon($data['icon'] ?? null),
        ]);

        if (array_key_exists('user_ids', $data)) {
            $role->users()->sync($data['user_ids'] ?? []);
        }
        $role->loadCount('users');

        return response()->json($role, 201);
    }

    /** PUT /access-management/roles/{role} */
    public function update(Request $request, Role $role): JsonResponse
    {
        $user = $request->user();
        if (! $this->userHasPermission($user, '/apps/access-management/roles', 'can_edit')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:80', 'unique:roles,name,' . $role->id],
            'description' => ['nullable', 'string', 'max:255'],
            'key_responsibilities' => ['nullable', 'string', 'max:5000'],
            'icon' => ['nullable', 'string', 'max:120'],
            'user_ids'    => ['nullable', 'array'],
            'user_ids.*'  => ['integer', 'exists:users,id'],
        ]);

        $role->update([
            'name' => $data['name'] ?? $role->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
            'key_responsibilities' => array_key_exists('key_responsibilities', $data) ? $data['key_responsibilities'] : $role->key_responsibilities,
            'icon' => array_key_exists('icon', $data) ? $this->normalizeRoleIcon($data['icon']) : $role->icon,
        ]);

        if (array_key_exists('user_ids', $data)) {
            $role->users()->sync($data['user_ids'] ?? []);
        }
        $role->loadCount('users');

        return response()->json($role);
    }

    /** DELETE /access-management/roles/{role} */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $user = $request->user();
        if (! $this->userHasPermission($user, '/apps/access-management/roles', 'can_delete')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    /**
     * GET /access-management/roles/{role}/menu-permissions
     *
     * Returns every menu with the current permission flags for this role.
     * Menus without an explicit record default to all-false.
     */
    public function menuPermissions(Role $role): JsonResponse
    {
        // Build a quick lookup: menu_id => pivot row
        $existing = $role->menuPermissions()
            ->get()
            ->keyBy('id');   // keyed by menu_id

        $menus = Menu::orderBy('sort_order')->orderBy('id')->get()->map(function ($menu) use ($existing) {
            $pivot = $existing->get($menu->id)?->pivot;

            return [
                'menu_id'    => $menu->id,
                'label'      => $menu->label,
                'slug'       => $menu->slug,
                'is_title'   => $menu->is_title,
                'parent_id'  => $menu->parent_id,
                'can_view'   => (bool) ($pivot->can_view   ?? false),
                'can_add'    => (bool) ($pivot->can_add    ?? false),
                'can_edit'   => (bool) ($pivot->can_edit   ?? false),
                'can_delete' => (bool) ($pivot->can_delete ?? false),
            ];
        });

        return response()->json($menus);
    }

    /**
     * POST /access-management/roles/{role}/menu-permissions
     *
     * Payload: { permissions: [{ menu_id, can_view, can_add, can_edit, can_delete }] }
     *
     * Uses sync-with-pivot to replace all existing permission rows.
     */
    public function saveMenuPermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions'              => ['required', 'array'],
            'permissions.*.menu_id'    => ['required', 'integer', 'exists:menus,id'],
            'permissions.*.can_view'   => ['boolean'],
            'permissions.*.can_add'    => ['boolean'],
            'permissions.*.can_edit'   => ['boolean'],
            'permissions.*.can_delete' => ['boolean'],
        ]);

        // Build sync array: menu_id => pivot columns
        $sync = [];
        foreach ($request->input('permissions') as $perm) {
            $sync[$perm['menu_id']] = [
                'can_view'   => (bool) ($perm['can_view']   ?? false),
                'can_add'    => (bool) ($perm['can_add']    ?? false),
                'can_edit'   => (bool) ($perm['can_edit']   ?? false),
                'can_delete' => (bool) ($perm['can_delete'] ?? false),
            ];
        }

        $role->menuPermissions()->sync($sync);

        return response()->json(['message' => 'Permissions saved.']);
    }

    private function normalizeRoleIcon(mixed $icon): string
    {
        if (! is_string($icon)) {
            return 'shield';
        }

        $trimmed = trim($icon);

        return $trimmed !== '' ? $trimmed : 'shield';
    }
}
