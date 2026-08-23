<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $mainId = DB::table('menus')->where('slug', 'main')->value('id');
            $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

            DB::table('menus')->updateOrInsert(
                ['slug' => 'events'],
                [
                    'label' => 'Event Scheduler',
                    'url' => '/events',
                    'icon' => 'calendar-event',
                    'parent_id' => $mainId,
                    'sort_order' => 9,
                    'is_title' => false,
                    'is_active' => true,
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

            $menuId = DB::table('menus')->where('slug', 'events')->value('id');
            if ($menuId && $adminRoleId) {
                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role_id' => $adminRoleId, 'menu_id' => $menuId],
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
                        'can_print' => false,
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
            $menuId = DB::table('menus')->where('slug', 'events')->value('id');
            if (! $menuId) {
                return;
            }

            DB::table('role_menu_permissions')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        });
    }
};
