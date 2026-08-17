<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $menuTabs = [
            'resident-management:residents' => [
                ['key' => 'audit-history', 'label' => 'Audit History', 'icon' => 'history'],
            ],
            'resident-management:households' => [
                ['key' => 'audit-history', 'label' => 'Audit History', 'icon' => 'history'],
            ],
            'resident-management:puroks' => [
                ['key' => 'audit-history', 'label' => 'Audit History', 'icon' => 'history'],
            ],
            'barangay-services:requests' => [
                ['key' => 'overview', 'label' => 'Details', 'icon' => 'layout-dashboard'],
                ['key' => 'audit-history', 'label' => 'Audit History', 'icon' => 'history'],
            ],
            'barangay-services:types' => [
                ['key' => 'overview', 'label' => 'Details', 'icon' => 'layout-dashboard'],
                ['key' => 'audit-history', 'label' => 'Audit History', 'icon' => 'history'],
            ],
            'barangay-id' => [
                ['key' => 'overview', 'label' => 'Details', 'icon' => 'layout-dashboard'],
                ['key' => 'audit-history', 'label' => 'Audit History', 'icon' => 'history'],
            ],
        ];

        DB::transaction(function () use ($menuTabs, $now): void {
            foreach ($menuTabs as $menuSlug => $tabs) {
                $menuId = DB::table('menus')->where('slug', $menuSlug)->value('id');
                if (! $menuId) {
                    continue;
                }

                $maxSortOrder = (int) DB::table('menu_tabs')->where('menu_id', $menuId)->max('sort_order');
                $createdTabIds = [];

                foreach ($tabs as $index => $tab) {
                    $sortOrder = $tab['key'] === 'audit-history'
                        ? $maxSortOrder + 1
                        : $index;

                    DB::table('menu_tabs')->updateOrInsert(
                        ['menu_id' => $menuId, 'key' => $tab['key']],
                        [
                            'label' => $tab['label'],
                            'icon' => $tab['icon'],
                            'sort_order' => $sortOrder,
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

                    $tabId = DB::table('menu_tabs')
                        ->where('menu_id', $menuId)
                        ->where('key', $tab['key'])
                        ->value('id');

                    if ($tabId) {
                        $createdTabIds[] = $tabId;
                    }
                }

                $rolePermissions = DB::table('role_menu_permissions')
                    ->where('menu_id', $menuId)
                    ->where('can_view', true)
                    ->get();

                foreach ($rolePermissions as $rolePermission) {
                    foreach ($createdTabIds as $tabId) {
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
        $tabKeysByMenuSlug = [
            'resident-management:residents' => ['audit-history'],
            'resident-management:households' => ['audit-history'],
            'resident-management:puroks' => ['audit-history'],
            'barangay-services:requests' => ['overview', 'audit-history'],
            'barangay-services:types' => ['overview', 'audit-history'],
            'barangay-id' => ['overview', 'audit-history'],
        ];

        DB::transaction(function () use ($tabKeysByMenuSlug): void {
            foreach ($tabKeysByMenuSlug as $menuSlug => $tabKeys) {
                $menuId = DB::table('menus')->where('slug', $menuSlug)->value('id');
                if (! $menuId) {
                    continue;
                }

                $tabIds = DB::table('menu_tabs')
                    ->where('menu_id', $menuId)
                    ->whereIn('key', $tabKeys)
                    ->pluck('id');

                if ($tabIds->isNotEmpty()) {
                    DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
                }

                DB::table('menu_tabs')
                    ->where('menu_id', $menuId)
                    ->whereIn('key', $tabKeys)
                    ->delete();
            }
        });
    }
};
