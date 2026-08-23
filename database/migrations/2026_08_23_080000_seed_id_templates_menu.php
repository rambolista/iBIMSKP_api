<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $documentsMenuId = DB::table('menus')->where('slug', 'administration:documents')->value('id');
            if (! $documentsMenuId) {
                return;
            }

            DB::table('menus')->updateOrInsert(
                ['slug' => 'administration:id-templates'],
                [
                    'label' => 'ID Templates',
                    'url' => '/apps/administration/id-templates',
                    'icon' => 'id-badge-2',
                    'parent_id' => $documentsMenuId,
                    'sort_order' => 2,
                    'is_title' => false,
                    'is_active' => true,
                    'is_hidden' => false,
                    'is_disabled' => false,
                    'is_special' => false,
                    'supports_view' => true,
                    'supports_add' => true,
                    'supports_edit' => true,
                    'supports_delete' => true,
                    'supports_export' => true,
                    'supports_print' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $menuId = DB::table('menus')->where('slug', 'administration:id-templates')->value('id');
            if (! $menuId) {
                return;
            }

            DB::table('menu_tabs')->updateOrInsert(
                ['menu_id' => $menuId, 'key' => 'overview'],
                [
                    'label' => 'Details',
                    'icon' => 'layout-dashboard',
                    'sort_order' => 0,
                    'is_active' => true,
                    'supports_view' => true,
                    'supports_add' => true,
                    'supports_edit' => true,
                    'supports_delete' => true,
                    'supports_approve' => false,
                    'supports_execute' => false,
                    'supports_cancel' => false,
                    'supports_reverse' => false,
                    'supports_export' => true,
                    'supports_print' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            DB::table('menu_tabs')->updateOrInsert(
                ['menu_id' => $menuId, 'key' => 'audit-history'],
                [
                    'label' => 'Audit History',
                    'icon' => 'history',
                    'sort_order' => 1,
                    'is_active' => true,
                    'supports_view' => true,
                    'supports_add' => true,
                    'supports_edit' => true,
                    'supports_delete' => true,
                    'supports_approve' => false,
                    'supports_execute' => false,
                    'supports_cancel' => false,
                    'supports_reverse' => false,
                    'supports_export' => true,
                    'supports_print' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $sourceMenuId = DB::table('menus')->where('slug', 'administration:document-logos')->value('id');
            $sourcePermissions = $sourceMenuId
                ? DB::table('role_menu_permissions')->where('menu_id', $sourceMenuId)->where('can_view', true)->get()
                : collect();

            if ($sourcePermissions->isEmpty()) {
                $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
                if ($adminRoleId) {
                    $sourcePermissions = collect([(object) [
                        'role_id' => $adminRoleId,
                        'can_add' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_approve' => false,
                        'can_execute' => false,
                        'can_cancel' => false,
                        'can_reverse' => false,
                        'can_export' => true,
                        'can_print' => true,
                    ]]);
                }
            }

            foreach ($sourcePermissions as $permission) {
                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role_id' => $permission->role_id, 'menu_id' => $menuId],
                    [
                        'can_view' => true,
                        'can_add' => (bool) $permission->can_add,
                        'can_edit' => (bool) $permission->can_edit,
                        'can_delete' => (bool) $permission->can_delete,
                        'can_approve' => (bool) $permission->can_approve,
                        'can_execute' => (bool) $permission->can_execute,
                        'can_cancel' => (bool) $permission->can_cancel,
                        'can_reverse' => (bool) $permission->can_reverse,
                        'can_export' => (bool) $permission->can_export,
                        'can_print' => (bool) $permission->can_print,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
            $rolePermissions = DB::table('role_menu_permissions')->where('menu_id', $menuId)->get();

            foreach ($rolePermissions as $permission) {
                foreach ($tabIds as $tabId) {
                    DB::table('role_menu_tab_permissions')->updateOrInsert(
                        ['role_id' => $permission->role_id, 'menu_tab_id' => $tabId],
                        [
                            'can_view' => (bool) $permission->can_view,
                            'can_add' => (bool) $permission->can_add,
                            'can_edit' => (bool) $permission->can_edit,
                            'can_delete' => (bool) $permission->can_delete,
                            'can_approve' => false,
                            'can_execute' => false,
                            'can_cancel' => false,
                            'can_reverse' => false,
                            'can_export' => (bool) $permission->can_export,
                            'can_print' => (bool) $permission->can_print,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $menuId = DB::table('menus')->where('slug', 'administration:id-templates')->value('id');
            if (! $menuId) {
                return;
            }

            $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
            DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
            DB::table('role_menu_permissions')->where('menu_id', $menuId)->delete();
            DB::table('menu_tabs')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        });
    }
};
