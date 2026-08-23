<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $menuId = DB::table('menus')->where('slug', 'reports')->value('id');
            if (! $menuId) {
                return;
            }

            $tabs = [
                ['key' => 'resident', 'label' => 'Resident Reports', 'icon' => 'users', 'sort_order' => 0],
                ['key' => 'certificate', 'label' => 'Certificate Reports', 'icon' => 'certificate', 'sort_order' => 1],
                ['key' => 'lupon', 'label' => 'Lupon Reports', 'icon' => 'gavel', 'sort_order' => 2],
                ['key' => 'blotter', 'label' => 'Blotter Reports', 'icon' => 'clipboard-list', 'sort_order' => 3],
                ['key' => 'financial', 'label' => 'Financial Reports', 'icon' => 'report-money', 'sort_order' => 4],
            ];

            foreach ($tabs as $tab) {
                DB::table('menu_tabs')->updateOrInsert(
                    ['menu_id' => $menuId, 'key' => $tab['key']],
                    [
                        'label' => $tab['label'],
                        'icon' => $tab['icon'],
                        'sort_order' => $tab['sort_order'],
                        'is_active' => true,
                        'supports_view' => true,
                        'supports_add' => false,
                        'supports_edit' => false,
                        'supports_delete' => false,
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
                            'can_add' => false,
                            'can_edit' => false,
                            'can_delete' => false,
                            'can_approve' => false,
                            'can_execute' => false,
                            'can_cancel' => false,
                            'can_reverse' => false,
                            'can_export' => (bool) $rolePermission->can_export,
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
        DB::transaction(function (): void {
            $menuId = DB::table('menus')->where('slug', 'reports')->value('id');
            if (! $menuId) {
                return;
            }

            $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
            if ($tabIds->isNotEmpty()) {
                DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
            }

            DB::table('menu_tabs')->where('menu_id', $menuId)->delete();
        });
    }
};
