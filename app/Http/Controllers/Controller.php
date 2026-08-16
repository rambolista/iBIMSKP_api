<?php

namespace App\Http\Controllers;

use App\Models\User;

abstract class Controller
{
    protected function buildMenuPermissionsPayload(object $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $user->loadMissing('roles');

        $permissions = $user->roles->flatMap(function ($role) {
            return $role->menuPermissions()->get()->map(function ($menu) {
                return [
                    'menu_id' => $menu->id,
                    'slug' => $menu->slug,
                    'label' => $menu->label,
                    'url' => $menu->url,
                    'is_title' => (bool) $menu->is_title,
                    'can_view' => (bool) ($menu->pivot->can_view ?? false),
                    'can_add' => (bool) ($menu->pivot->can_add ?? false),
                    'can_edit' => (bool) ($menu->pivot->can_edit ?? false),
                    'can_delete' => (bool) ($menu->pivot->can_delete ?? false),
                ];
            });
        });

        $merged = [];
        foreach ($permissions as $permission) {
            $menuId = $permission['menu_id'];
            if (! array_key_exists($menuId, $merged)) {
                $merged[$menuId] = $permission;
                continue;
            }

            foreach (['can_view', 'can_add', 'can_edit', 'can_delete'] as $action) {
                $merged[$menuId][$action] = $merged[$menuId][$action] || $permission[$action];
            }
        }

        return array_values($merged);
    }

    protected function getAccessibleMenuIds(object $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        return collect($this->buildMenuPermissionsPayload($user))
            ->filter(fn ($menu) => ! empty($menu['can_view']))
            ->pluck('menu_id')
            ->unique()
            ->values()
            ->all();
    }

    protected function userHasPermission(object $user, string $routePath, string $action): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $normalizedRoutePath = $this->normalizeRoute($routePath);

        $permission = collect($this->buildMenuPermissionsPayload($user))->first(function ($menu) use ($normalizedRoutePath) {
            $menuUrl = $this->normalizeRoute($menu['url'] ?? '');
            $menuSlug = $this->normalizeRoute($menu['slug'] ?? '');
            $segments = explode('/', trim($normalizedRoutePath, '/'));
            $lastSegment = $this->normalizeRoute($normalizedRoutePath === '/' ? '' : ($segments[count($segments) - 1] ?? ''));

            return $menuUrl === $normalizedRoutePath
                || $menuSlug === $normalizedRoutePath
                || $menuSlug === $lastSegment
                || $menuUrl === $lastSegment;
        });

        return (bool) ($permission[$action] ?? false);
    }

    protected function normalizeRoute(?string $routePath): string
    {
        if ($routePath === null) {
            return '/';
        }

        $trimmed = trim((string) $routePath);
        if ($trimmed === '') {
            return '/';
        }

        return '/' . trim($trimmed, '/');
    }
}
