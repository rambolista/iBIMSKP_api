<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUGS = ['pages:dashboard-analytics', 'pages:dashboard-projects', 'dashboards'];

    public function up(): void
    {
        DB::transaction(function (): void {
            $menuIds = DB::table('menus')->whereIn('slug', self::SLUGS)->pluck('id');
            if ($menuIds->isEmpty()) {
                return;
            }

            $tabIds = DB::table('menu_tabs')->whereIn('menu_id', $menuIds)->pluck('id');
            DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
            DB::table('menu_tabs')->whereIn('menu_id', $menuIds)->delete();
            DB::table('role_menu_permissions')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menus')->whereIn('id', $menuIds)->delete();
        });
    }

    public function down(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $mainId = DB::table('menus')->where('slug', 'main')->value('id');
            if (! $mainId) {
                return;
            }

            DB::table('menus')->updateOrInsert(
                ['slug' => 'dashboards'],
                [
                    'label' => 'Dashboards',
                    'url' => null,
                    'icon' => 'dashboard',
                    'parent_id' => $mainId,
                    'sort_order' => 0,
                    'is_title' => false,
                    'is_active' => true,
                    'is_hidden' => false,
                    'is_disabled' => false,
                    'is_special' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $dashboardsId = DB::table('menus')->where('slug', 'dashboards')->value('id');
            if (! $dashboardsId) {
                return;
            }

            DB::table('menus')->updateOrInsert(
                ['slug' => 'pages:dashboard-analytics'],
                [
                    'label' => 'Analytics',
                    'url' => '/dashboard/analytics',
                    'icon' => null,
                    'parent_id' => $dashboardsId,
                    'sort_order' => 1,
                    'is_title' => false,
                    'is_active' => true,
                    'is_hidden' => false,
                    'is_disabled' => false,
                    'is_special' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            DB::table('menus')->updateOrInsert(
                ['slug' => 'pages:dashboard-projects'],
                [
                    'label' => 'Projects',
                    'url' => '/dashboard/projects',
                    'icon' => null,
                    'parent_id' => $dashboardsId,
                    'sort_order' => 2,
                    'is_title' => false,
                    'is_active' => true,
                    'is_hidden' => false,
                    'is_disabled' => false,
                    'is_special' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
            if (! $adminRoleId) {
                return;
            }

            $restoredIds = DB::table('menus')->whereIn('slug', self::SLUGS)->pluck('id');
            foreach ($restoredIds as $menuId) {
                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role_id' => $adminRoleId, 'menu_id' => $menuId],
                    ['can_view' => true, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        });
    }
};
