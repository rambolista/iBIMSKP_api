<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENUS = [
        'administration:barangay-setup' => [
            'label' => 'Barangay Setup',
            'url' => '/apps/administration/barangay-setup',
            'icon' => 'map-pin',
            'sort_order' => 1,
        ],
        'administration:barangay-officials' => [
            'label' => 'Barangay Officials',
            'url' => '/apps/administration/barangay-officials',
            'icon' => 'users-group',
            'sort_order' => 2,
        ],
    ];

    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $administrationId = DB::table('menus')->where('slug', 'administration')->value('id');
            if (! $administrationId) {
                return;
            }

            $menuIds = [];

            foreach (self::MENUS as $slug => $menu) {
                DB::table('menus')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'label' => $menu['label'],
                        'url' => $menu['url'],
                        'icon' => $menu['icon'],
                        'parent_id' => $administrationId,
                        'sort_order' => $menu['sort_order'],
                        'is_title' => false,
                        'is_active' => true,
                        'is_hidden' => false,
                        'is_disabled' => false,
                        'is_special' => false,
                        'supports_view' => true,
                        'supports_add' => $slug === 'administration:barangay-officials',
                        'supports_edit' => true,
                        'supports_delete' => $slug === 'administration:barangay-officials',
                        'supports_export' => true,
                        'supports_print' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $menuIds[$slug] = DB::table('menus')->where('slug', $slug)->value('id');
            }

            $officialsMenuId = $menuIds['administration:barangay-officials'] ?? null;
            if ($officialsMenuId) {
                DB::table('menu_tabs')->updateOrInsert(
                    ['menu_id' => $officialsMenuId, 'key' => 'overview'],
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
                    ['menu_id' => $officialsMenuId, 'key' => 'audit-history'],
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
            }

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

            foreach ($menuIds as $menuId) {
                if (! $menuId) {
                    continue;
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
            }

            if ($officialsMenuId) {
                $tabIds = DB::table('menu_tabs')->where('menu_id', $officialsMenuId)->pluck('id');
                $rolePermissions = DB::table('role_menu_permissions')->where('menu_id', $officialsMenuId)->get();

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
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            foreach (array_keys(self::MENUS) as $slug) {
                $menuId = DB::table('menus')->where('slug', $slug)->value('id');
                if (! $menuId) {
                    continue;
                }

                $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
                DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
                DB::table('role_menu_permissions')->where('menu_id', $menuId)->delete();
                DB::table('menu_tabs')->where('menu_id', $menuId)->delete();
                DB::table('menus')->where('id', $menuId)->delete();
            }
        });
    }
};
