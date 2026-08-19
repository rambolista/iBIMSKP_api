<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $administrationId = DB::table('menus')->where('slug', 'administration')->value('id');
            $sourceMenuId = DB::table('menus')->where('slug', 'administration:document-logos')->value('id');

            if (! $administrationId) {
                return;
            }

            DB::table('menus')->updateOrInsert(
                ['slug' => 'administration:dropdown-settings'],
                [
                    'label' => 'Dropdown Settings',
                    'url' => '/apps/administration/dropdown-settings',
                    'icon' => 'list-details',
                    'parent_id' => $administrationId,
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

            $menuId = DB::table('menus')->where('slug', 'administration:dropdown-settings')->value('id');
            if (! $menuId) {
                return;
            }

            DB::table('menu_tabs')->updateOrInsert(
                ['menu_id' => $menuId, 'key' => 'overview'],
                [
                    'label' => 'Overview',
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
                    'supports_print' => false,
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
                    'supports_print' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $rolePermissions = $sourceMenuId
                ? DB::table('role_menu_permissions')->where('menu_id', $sourceMenuId)->where('can_view', true)->get()
                : collect();

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
            $menuRolePermissions = DB::table('role_menu_permissions')->where('menu_id', $menuId)->where('can_view', true)->get();
            foreach ($menuRolePermissions as $rolePermission) {
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

            $natureOptions = [
                'Property dispute',
                'Boundary dispute',
                'Monetary/debt dispute',
                'Noise disturbance',
                'Verbal altercation',
                'Physical altercation',
                'Property damage',
                'Family dispute',
                'Neighbor dispute',
                'Business dispute',
                'Other',
            ];

            foreach ($natureOptions as $index => $name) {
                DB::table('dropdown_settings')->updateOrInsert(
                    ['category' => 'Nature of Case', 'name' => $name],
                    [
                        'sort_order' => $index,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $menuId = DB::table('menus')->where('slug', 'administration:dropdown-settings')->value('id');

            DB::table('dropdown_settings')->where('category', 'Nature of Case')->delete();

            if (! $menuId) {
                return;
            }

            $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
            if ($tabIds->isNotEmpty()) {
                DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
                DB::table('menu_tabs')->whereIn('id', $tabIds)->delete();
            }

            DB::table('role_menu_permissions')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        });
    }
};
