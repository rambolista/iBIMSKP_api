<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $menuId = DB::table('menus')
                ->where('slug', 'resident-management:residents')
                ->value('id');

            if (! $menuId) {
                return;
            }

            DB::table('menu_tabs')
                ->where('menu_id', $menuId)
                ->whereIn('key', ['records', 'audit-history'])
                ->increment('sort_order');

            DB::table('menu_tabs')->updateOrInsert(
                ['menu_id' => $menuId, 'key' => 'blotters'],
                [
                    'label' => 'Blotter',
                    'icon' => 'clipboard-text',
                    'sort_order' => 6,
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
                    'supports_print' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $tabId = DB::table('menu_tabs')
                ->where('menu_id', $menuId)
                ->where('key', 'blotters')
                ->value('id');

            if (! $tabId) {
                return;
            }

            $rolePermissions = DB::table('role_menu_permissions')
                ->where('menu_id', $menuId)
                ->where('can_view', true)
                ->get();

            foreach ($rolePermissions as $rolePermission) {
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
                        'can_print' => (bool) $rolePermission->can_print,
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
            $menuId = DB::table('menus')
                ->where('slug', 'resident-management:residents')
                ->value('id');

            if (! $menuId) {
                return;
            }

            $tabId = DB::table('menu_tabs')
                ->where('menu_id', $menuId)
                ->where('key', 'blotters')
                ->value('id');

            if ($tabId) {
                DB::table('role_menu_tab_permissions')
                    ->where('menu_tab_id', $tabId)
                    ->delete();
                DB::table('menu_tabs')->where('id', $tabId)->delete();
            }

            DB::table('menu_tabs')
                ->where('menu_id', $menuId)
                ->whereIn('key', ['records', 'audit-history'])
                ->where('sort_order', '>', 0)
                ->decrement('sort_order');
        });
    }
};
