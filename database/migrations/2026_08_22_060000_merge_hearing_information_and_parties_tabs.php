<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'katarungang-pambarangay:hearings';

    public function up(): void
    {
        DB::transaction(function (): void {
            $menuId = DB::table('menus')->where('slug', self::SLUG)->value('id');
            if (! $menuId) {
                return;
            }

            $tabId = DB::table('menu_tabs')->where('menu_id', $menuId)->where('key', 'parties')->value('id');
            if (! $tabId) {
                return;
            }

            DB::table('role_menu_tab_permissions')->where('menu_tab_id', $tabId)->delete();
            DB::table('menu_tabs')->where('id', $tabId)->delete();
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

            DB::table('menu_tabs')->updateOrInsert(
                ['menu_id' => $menuId, 'key' => 'parties'],
                [
                    'label' => 'Parties',
                    'icon' => 'users',
                    'sort_order' => 1,
                    'is_active' => true,
                    'supports_view' => true,
                    'supports_add' => true,
                    'supports_edit' => true,
                    'supports_delete' => true,
                    'supports_approve' => false,
                    'supports_execute' => true,
                    'supports_cancel' => false,
                    'supports_reverse' => false,
                    'supports_export' => true,
                    'supports_print' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $tabId = DB::table('menu_tabs')->where('menu_id', $menuId)->where('key', 'parties')->value('id');

            $rolePermissions = DB::table('role_menu_permissions')
                ->where('menu_id', $menuId)
                ->where('can_view', true)
                ->get();

            foreach ($rolePermissions as $rolePermission) {
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
        });
    }
};
