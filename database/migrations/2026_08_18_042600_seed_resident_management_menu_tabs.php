<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $tabsByMenuSlug = [
            'resident-management:residents' => [
                ['key' => 'overview', 'label' => 'Overview', 'icon' => 'layout-dashboard'],
                ['key' => 'personal', 'label' => 'Personal Information', 'icon' => 'user-circle'],
                ['key' => 'household', 'label' => 'Household & Address', 'icon' => 'home'],
                ['key' => 'occupation', 'label' => 'Occupation & Classification', 'icon' => 'briefcase'],
                ['key' => 'service-requests', 'label' => 'Service Requests', 'icon' => 'clipboard-list'],
                ['key' => 'barangay-ids', 'label' => 'Barangay IDs', 'icon' => 'id'],
                ['key' => 'records', 'label' => 'Related Records', 'icon' => 'history'],
            ],
            'resident-management:households' => [
                ['key' => 'overview', 'label' => 'Household Information', 'icon' => 'home'],
                ['key' => 'members', 'label' => 'Household Members', 'icon' => 'users'],
                ['key' => 'address', 'label' => 'Address & Socioeconomic', 'icon' => 'map-pin'],
                ['key' => 'history', 'label' => 'History', 'icon' => 'history'],
            ],
            'resident-management:puroks' => [
                ['key' => 'overview', 'label' => 'Purok Information', 'icon' => 'map-pin'],
                ['key' => 'statistics', 'label' => 'Statistics', 'icon' => 'chart-bar'],
                ['key' => 'residents', 'label' => 'Residents & Households', 'icon' => 'users-group'],
                ['key' => 'history', 'label' => 'History', 'icon' => 'history'],
            ],
        ];

        DB::transaction(function () use ($tabsByMenuSlug, $now): void {
            foreach ($tabsByMenuSlug as $menuSlug => $tabs) {
                $menuId = DB::table('menus')->where('slug', $menuSlug)->value('id');
                if (! $menuId) {
                    continue;
                }

                foreach ($tabs as $sortOrder => $tab) {
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
                }

                $tabIds = DB::table('menu_tabs')
                    ->where('menu_id', $menuId)
                    ->whereIn('key', array_column($tabs, 'key'))
                    ->pluck('id');

                $rolePermissions = DB::table('role_menu_permissions')
                    ->where('menu_id', $menuId)
                    ->where('can_view', true)
                    ->get();

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
        $tabsByMenuSlug = [
            'resident-management:residents' => ['overview', 'personal', 'household', 'occupation', 'service-requests', 'barangay-ids', 'records'],
            'resident-management:households' => ['overview', 'members', 'address', 'history'],
            'resident-management:puroks' => ['overview', 'statistics', 'residents', 'history'],
        ];

        DB::transaction(function () use ($tabsByMenuSlug): void {
            foreach ($tabsByMenuSlug as $menuSlug => $tabKeys) {
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
