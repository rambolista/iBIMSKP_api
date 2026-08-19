<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $parentMenuId = DB::table('menus')->where('slug', 'administration:dropdown-settings')->value('id');
            if (! $parentMenuId) {
                return;
            }

            DB::table('menus')
                ->where('id', $parentMenuId)
                ->update([
                    'label' => 'Dropdown Settings',
                    'url' => null,
                    'icon' => 'list-details',
                    'supports_add' => false,
                    'supports_edit' => false,
                    'supports_delete' => false,
                    'supports_approve' => false,
                    'supports_execute' => false,
                    'supports_cancel' => false,
                    'supports_reverse' => false,
                    'supports_export' => false,
                    'supports_print' => false,
                    'updated_at' => $now,
                ]);

            DB::table('menus')->updateOrInsert(
                ['slug' => 'administration:dropdown-settings:nature-of-case'],
                [
                    'label' => 'Nature of Case',
                    'url' => '/apps/administration/dropdown-settings/nature-of-case',
                    'icon' => 'list',
                    'parent_id' => $parentMenuId,
                    'sort_order' => 0,
                    'is_title' => false,
                    'is_active' => true,
                    'is_hidden' => false,
                    'is_disabled' => false,
                    'is_special' => false,
                    'supports_view' => true,
                    'supports_add' => true,
                    'supports_edit' => true,
                    'supports_delete' => true,
                    'supports_approve' => false,
                    'supports_execute' => false,
                    'supports_cancel' => false,
                    'supports_reverse' => false,
                    'supports_export' => true,
                    'supports_print' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $childMenuId = DB::table('menus')->where('slug', 'administration:dropdown-settings:nature-of-case')->value('id');
            if (! $childMenuId) {
                return;
            }

            $sourceRolePermissions = DB::table('role_menu_permissions')
                ->where('menu_id', $parentMenuId)
                ->where('can_view', true)
                ->get();

            foreach ($sourceRolePermissions as $permission) {
                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role_id' => $permission->role_id, 'menu_id' => $childMenuId],
                    [
                        'can_view' => true,
                        'can_add' => (bool) $permission->can_add,
                        'can_edit' => (bool) $permission->can_edit,
                        'can_delete' => (bool) $permission->can_delete,
                        'can_approve' => false,
                        'can_execute' => false,
                        'can_cancel' => false,
                        'can_reverse' => false,
                        'can_export' => (bool) $permission->can_export,
                        'can_print' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $childTabs = [
                ['key' => 'overview', 'label' => 'Overview', 'icon' => 'layout-dashboard', 'sort_order' => 0],
                ['key' => 'audit-history', 'label' => 'Audit History', 'icon' => 'history', 'sort_order' => 1],
            ];

            foreach ($childTabs as $tab) {
                DB::table('menu_tabs')->updateOrInsert(
                    ['menu_id' => $childMenuId, 'key' => $tab['key']],
                    [
                        'label' => $tab['label'],
                        'icon' => $tab['icon'],
                        'sort_order' => $tab['sort_order'],
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
                        'supports_print' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $childTabIds = DB::table('menu_tabs')->where('menu_id', $childMenuId)->pluck('id');
            $childRolePermissions = DB::table('role_menu_permissions')->where('menu_id', $childMenuId)->where('can_view', true)->get();
            foreach ($childRolePermissions as $permission) {
                foreach ($childTabIds as $tabId) {
                    DB::table('role_menu_tab_permissions')->updateOrInsert(
                        ['role_id' => $permission->role_id, 'menu_tab_id' => $tabId],
                        [
                            'can_view' => true,
                            'can_add' => (bool) $permission->can_add,
                            'can_edit' => (bool) $permission->can_edit,
                            'can_delete' => (bool) $permission->can_delete,
                            'can_approve' => false,
                            'can_execute' => false,
                            'can_cancel' => false,
                            'can_reverse' => false,
                            'can_export' => (bool) $permission->can_export,
                            'can_print' => false,
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
        $now = now();

        DB::transaction(function () use ($now): void {
            $parentMenuId = DB::table('menus')->where('slug', 'administration:dropdown-settings')->value('id');
            $childMenuId = DB::table('menus')->where('slug', 'administration:dropdown-settings:nature-of-case')->value('id');

            if ($childMenuId) {
                $childTabIds = DB::table('menu_tabs')->where('menu_id', $childMenuId)->pluck('id');
                if ($childTabIds->isNotEmpty()) {
                    DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $childTabIds)->delete();
                }
                DB::table('menu_tabs')->where('menu_id', $childMenuId)->delete();
                DB::table('role_menu_permissions')->where('menu_id', $childMenuId)->delete();
                DB::table('menus')->where('id', $childMenuId)->delete();
            }

            if ($parentMenuId) {
                DB::table('menus')
                    ->where('id', $parentMenuId)
                    ->update([
                        'url' => '/apps/administration/dropdown-settings',
                        'supports_add' => true,
                        'supports_edit' => true,
                        'supports_delete' => true,
                        'supports_export' => true,
                        'updated_at' => $now,
                    ]);
            }
        });
    }
};
