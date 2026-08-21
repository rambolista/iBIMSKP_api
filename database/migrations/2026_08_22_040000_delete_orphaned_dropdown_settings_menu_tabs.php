<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'administration:dropdown-settings';

    private const TABS = [
        ['key' => 'overview', 'label' => 'Overview', 'sort_order' => 0],
        ['key' => 'audit-history', 'label' => 'Audit History', 'sort_order' => 1],
    ];

    public function up(): void
    {
        // This menu row is a redirect-only nav grouping (url is null); the
        // actual page (/apps/administration/dropdown-settings/nature-of-case)
        // is served by a different menu entirely, so these tabs can never be
        // matched by ResourceDetailsPage and are unreachable.
        DB::transaction(function (): void {
            $menuId = DB::table('menus')->where('slug', self::SLUG)->value('id');
            if (! $menuId) {
                return;
            }

            $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
            if ($tabIds->isEmpty()) {
                return;
            }

            DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
            DB::table('menu_tabs')->whereIn('id', $tabIds)->delete();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $menuId = DB::table('menus')->where('slug', self::SLUG)->value('id');
            if (! $menuId) {
                return;
            }

            foreach (self::TABS as $tab) {
                DB::table('menu_tabs')->updateOrInsert(
                    ['menu_id' => $menuId, 'key' => $tab['key']],
                    [
                        'label' => $tab['label'],
                        'icon' => null,
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
                        'supports_export' => false,
                        'supports_print' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $tabIds = DB::table('menu_tabs')
                ->where('menu_id', $menuId)
                ->whereIn('key', array_column(self::TABS, 'key'))
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
        });
    }
};
