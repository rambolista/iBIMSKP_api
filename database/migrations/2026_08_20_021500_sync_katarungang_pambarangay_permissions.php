<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $sourceMenuId = DB::table('menus')->where('slug', 'blotter')->value('id');
            $parentMenuId = DB::table('menus')->where('slug', 'katarungang-pambarangay')->value('id');
            $childMenuIds = DB::table('menus')
                ->whereIn('slug', [
                    'katarungang-pambarangay:cases',
                    'katarungang-pambarangay:hearings',
                    'katarungang-pambarangay:pangkat',
                    'katarungang-pambarangay:lupon-members',
                    'katarungang-pambarangay:documents',
                ])
                ->pluck('id', 'slug');

            if (! $sourceMenuId || ! $parentMenuId || $childMenuIds->isEmpty()) {
                return;
            }

            $sourcePermissions = DB::table('role_menu_permissions')
                ->where('menu_id', $sourceMenuId)
                ->where('can_view', true)
                ->get();

            foreach ($sourcePermissions as $permission) {
                $existingParentPermission = DB::table('role_menu_permissions')
                    ->where('role_id', $permission->role_id)
                    ->where('menu_id', $parentMenuId)
                    ->first();
                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role_id' => $permission->role_id, 'menu_id' => $parentMenuId],
                    [
                        'can_view' => true,
                        'can_add' => (bool) ($existingParentPermission->can_add ?? false),
                        'can_edit' => (bool) ($existingParentPermission->can_edit ?? false),
                        'can_delete' => (bool) ($existingParentPermission->can_delete ?? false),
                        'can_approve' => (bool) ($existingParentPermission->can_approve ?? false),
                        'can_execute' => (bool) ($existingParentPermission->can_execute ?? false),
                        'can_cancel' => (bool) ($existingParentPermission->can_cancel ?? false),
                        'can_reverse' => (bool) ($existingParentPermission->can_reverse ?? false),
                        'can_export' => (bool) ($existingParentPermission->can_export ?? false),
                        'can_print' => (bool) ($existingParentPermission->can_print ?? false),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                foreach ($childMenuIds as $menuId) {
                    $existingChildPermission = DB::table('role_menu_permissions')
                        ->where('role_id', $permission->role_id)
                        ->where('menu_id', $menuId)
                        ->first();
                    DB::table('role_menu_permissions')->updateOrInsert(
                        ['role_id' => $permission->role_id, 'menu_id' => $menuId],
                        [
                            'can_view' => true,
                            'can_add' => (bool) ($existingChildPermission->can_add ?? false) || (bool) $permission->can_add,
                            'can_edit' => (bool) ($existingChildPermission->can_edit ?? false) || (bool) $permission->can_edit,
                            'can_delete' => (bool) ($existingChildPermission->can_delete ?? false) || (bool) $permission->can_delete,
                            'can_approve' => (bool) ($existingChildPermission->can_approve ?? false) || (bool) $permission->can_approve,
                            'can_execute' => (bool) ($existingChildPermission->can_execute ?? false) || (bool) $permission->can_execute,
                            'can_cancel' => (bool) ($existingChildPermission->can_cancel ?? false) || (bool) $permission->can_cancel,
                            'can_reverse' => (bool) ($existingChildPermission->can_reverse ?? false) || (bool) $permission->can_reverse,
                            'can_export' => (bool) ($existingChildPermission->can_export ?? false) || (bool) $permission->can_export,
                            'can_print' => (bool) ($existingChildPermission->can_print ?? false) || (bool) $permission->can_print,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }

            foreach ($childMenuIds as $menuId) {
                $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
                $rolePermissions = DB::table('role_menu_permissions')->where('menu_id', $menuId)->where('can_view', true)->get();

                foreach ($rolePermissions as $rolePermission) {
                    foreach ($tabIds as $tabId) {
                        DB::table('role_menu_tab_permissions')->updateOrInsert(
                            ['role_id' => $rolePermission->role_id, 'menu_tab_id' => $tabId],
                            [
                                'can_view' => true,
                                'can_add' => (bool) $rolePermission->can_add,
                                'can_edit' => (bool) $rolePermission->can_edit,
                                'can_delete' => (bool) $rolePermission->can_delete,
                                'can_approve' => (bool) $rolePermission->can_approve,
                                'can_execute' => (bool) $rolePermission->can_execute,
                                'can_cancel' => (bool) $rolePermission->can_cancel,
                                'can_reverse' => (bool) $rolePermission->can_reverse,
                                'can_export' => (bool) $rolePermission->can_export,
                                'can_print' => (bool) $rolePermission->can_print,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]
                        );
                    }
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $parentMenuId = DB::table('menus')->where('slug', 'katarungang-pambarangay')->value('id');
            $childMenuIds = DB::table('menus')
                ->whereIn('slug', [
                    'katarungang-pambarangay:cases',
                    'katarungang-pambarangay:hearings',
                    'katarungang-pambarangay:pangkat',
                    'katarungang-pambarangay:lupon-members',
                    'katarungang-pambarangay:documents',
                ])
                ->pluck('id');
            $sourceRoleIds = DB::table('role_menu_permissions')
                ->where('menu_id', DB::table('menus')->where('slug', 'blotter')->value('id'))
                ->where('can_view', true)
                ->pluck('role_id');

            if ($childMenuIds->isNotEmpty()) {
                $tabIds = DB::table('menu_tabs')->whereIn('menu_id', $childMenuIds)->pluck('id');
                DB::table('role_menu_tab_permissions')
                    ->whereIn('role_id', $sourceRoleIds)
                    ->whereIn('menu_tab_id', $tabIds)
                    ->delete();
                DB::table('role_menu_permissions')
                    ->whereIn('role_id', $sourceRoleIds)
                    ->whereIn('menu_id', $childMenuIds)
                    ->delete();
            }

            if ($parentMenuId) {
                DB::table('role_menu_permissions')
                    ->whereIn('role_id', $sourceRoleIds)
                    ->where('menu_id', $parentMenuId)
                    ->where('can_add', false)
                    ->where('can_edit', false)
                    ->where('can_delete', false)
                    ->delete();
            }
        });
    }
};
