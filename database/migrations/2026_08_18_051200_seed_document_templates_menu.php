<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $servicesMenuId = DB::table('menus')->where('slug', 'barangay-services')->value('id');

            if (! $servicesMenuId) {
                return;
            }

            DB::table('menus')->updateOrInsert(
                ['slug' => 'barangay-services:document-templates'],
                [
                    'label' => 'Document Templates',
                    'url' => '/barangay-services/document-templates',
                    'icon' => 'template',
                    'parent_id' => $servicesMenuId,
                    'sort_order' => 2,
                    'is_title' => false,
                    'is_active' => true,
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

            $menuId = DB::table('menus')->where('slug', 'barangay-services:document-templates')->value('id');
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

            $rolePermissions = DB::table('role_menu_permissions')
                ->where('can_view', true)
                ->whereIn('role_id', DB::table('role_menu_permissions')->where('menu_id', $servicesMenuId)->pluck('role_id'))
                ->get();

            foreach ($rolePermissions as $rolePermission) {
                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role_id' => $rolePermission->role_id, 'menu_id' => $menuId],
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

            $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
            $roleIds = DB::table('role_menu_permissions')->where('menu_id', $menuId)->pluck('role_id');

            foreach ($roleIds as $roleId) {
                foreach ($tabIds as $tabId) {
                    DB::table('role_menu_tab_permissions')->updateOrInsert(
                        ['role_id' => $roleId, 'menu_tab_id' => $tabId],
                        [
                            'can_view' => true,
                            'can_add' => true,
                            'can_edit' => true,
                            'can_delete' => true,
                            'can_approve' => false,
                            'can_execute' => false,
                            'can_cancel' => false,
                            'can_reverse' => false,
                            'can_export' => true,
                            'can_print' => true,
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
            $menuId = DB::table('menus')->where('slug', 'barangay-services:document-templates')->value('id');
            if (! $menuId) {
                return;
            }

            DB::table('role_menu_permissions')->where('menu_id', $menuId)->delete();
            $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
            DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
            DB::table('menu_tabs')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        });
    }
};
