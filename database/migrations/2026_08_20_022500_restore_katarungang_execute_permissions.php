<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
            if (! $adminRoleId) {
                return;
            }

            $menuIds = DB::table('menus')
                ->whereIn('slug', ['katarungang-pambarangay:cases', 'katarungang-pambarangay:hearings'])
                ->pluck('id');

            if ($menuIds->isEmpty()) {
                return;
            }

            DB::table('role_menu_permissions')
                ->where('role_id', $adminRoleId)
                ->whereIn('menu_id', $menuIds)
                ->update([
                    'can_execute' => true,
                    'updated_at' => $now,
                ]);

            $tabIds = DB::table('menu_tabs')->whereIn('menu_id', $menuIds)->pluck('id');
            if ($tabIds->isEmpty()) {
                return;
            }

            DB::table('role_menu_tab_permissions')
                ->where('role_id', $adminRoleId)
                ->whereIn('menu_tab_id', $tabIds)
                ->update([
                    'can_execute' => true,
                    'updated_at' => $now,
                ]);
        });
    }

    public function down(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
            $menuIds = DB::table('menus')
                ->whereIn('slug', ['katarungang-pambarangay:cases', 'katarungang-pambarangay:hearings'])
                ->pluck('id');

            if (! $adminRoleId || $menuIds->isEmpty()) {
                return;
            }

            DB::table('role_menu_permissions')
                ->where('role_id', $adminRoleId)
                ->whereIn('menu_id', $menuIds)
                ->update([
                    'can_execute' => false,
                    'updated_at' => $now,
                ]);

            $tabIds = DB::table('menu_tabs')->whereIn('menu_id', $menuIds)->pluck('id');
            if ($tabIds->isEmpty()) {
                return;
            }

            DB::table('role_menu_tab_permissions')
                ->where('role_id', $adminRoleId)
                ->whereIn('menu_tab_id', $tabIds)
                ->update([
                    'can_execute' => false,
                    'updated_at' => $now,
                ]);
        });
    }
};
