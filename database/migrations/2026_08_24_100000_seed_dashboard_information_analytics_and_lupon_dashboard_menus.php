<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MAIN_SIBLING_SHIFT = [
        295 => 3, // Residents 2 -> 3
        299 => 4, // Barangay Services 3 -> 4
        302 => 5, // Barangay ID 4 -> 5
        304 => 6, // Blotter 5 -> 6
        309 => 7, // Katarungang Pambarangay 6 -> 7
        320 => 8, // Payment & Treasurer 7 -> 8
    ];

    private const KP_SIBLING_SHIFT = [
        310 => 1, // Cases 0 -> 1
        311 => 2, // Hearings 1 -> 2
        312 => 3, // Pangkat 2 -> 3
        313 => 4, // Lupon Members 3 -> 4
    ];

    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            foreach (self::MAIN_SIBLING_SHIFT as $menuId => $sortOrder) {
                DB::table('menus')->where('id', $menuId)->update(['sort_order' => $sortOrder]);
            }
            foreach (self::KP_SIBLING_SHIFT as $menuId => $sortOrder) {
                DB::table('menus')->where('id', $menuId)->update(['sort_order' => $sortOrder]);
            }

            $mainId = DB::table('menus')->where('slug', 'main')->value('id');
            $kpId = DB::table('menus')->where('slug', 'katarungang-pambarangay')->value('id');
            $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

            $newMenus = [
                ['slug' => 'dashboard:information', 'label' => 'Dashboard Information', 'url' => '/dashboard/information', 'icon' => 'report-analytics', 'parent_id' => $mainId, 'sort_order' => 1],
                ['slug' => 'dashboard:analytics', 'label' => 'Dashboard Analytics', 'url' => '/dashboard/analytics', 'icon' => 'chart-bar', 'parent_id' => $mainId, 'sort_order' => 2],
                ['slug' => 'katarungang-pambarangay:dashboard', 'label' => 'Lupon Dashboard', 'url' => '/katarungang-pambarangay/dashboard', 'icon' => 'layout-dashboard', 'parent_id' => $kpId, 'sort_order' => 0],
            ];

            foreach ($newMenus as $menu) {
                DB::table('menus')->updateOrInsert(
                    ['slug' => $menu['slug']],
                    [
                        'label' => $menu['label'],
                        'url' => $menu['url'],
                        'icon' => $menu['icon'],
                        'parent_id' => $menu['parent_id'],
                        'sort_order' => $menu['sort_order'],
                        'is_title' => false,
                        'is_active' => true,
                        'is_disabled' => false,
                        'is_special' => false,
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

                $menuId = DB::table('menus')->where('slug', $menu['slug'])->value('id');
                if ($menuId && $adminRoleId) {
                    DB::table('role_menu_permissions')->updateOrInsert(
                        ['role_id' => $adminRoleId, 'menu_id' => $menuId],
                        [
                            'can_view' => true,
                            'can_add' => false,
                            'can_edit' => false,
                            'can_delete' => false,
                            'can_approve' => false,
                            'can_execute' => false,
                            'can_cancel' => false,
                            'can_reverse' => false,
                            'can_export' => false,
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
            $slugs = ['dashboard:information', 'dashboard:analytics', 'katarungang-pambarangay:dashboard'];
            $menuIds = DB::table('menus')->whereIn('slug', $slugs)->pluck('id');

            DB::table('role_menu_permissions')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menus')->whereIn('id', $menuIds)->delete();

            foreach (self::MAIN_SIBLING_SHIFT as $menuId => $sortOrder) {
                DB::table('menus')->where('id', $menuId)->update(['sort_order' => $sortOrder - 1]);
            }
            foreach (self::KP_SIBLING_SHIFT as $menuId => $sortOrder) {
                DB::table('menus')->where('id', $menuId)->update(['sort_order' => $sortOrder - 1]);
            }
        });
    }
};
