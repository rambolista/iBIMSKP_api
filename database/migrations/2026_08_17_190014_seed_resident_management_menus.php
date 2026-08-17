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
            DB::table('menus')->updateOrInsert(
                ['slug' => 'resident-management'],
                [
                    'label' => 'Residents',
                    'url' => null,
                    'icon' => 'users-group',
                    'parent_id' => $mainId,
                    'sort_order' => 2,
                    'is_title' => false,
                    'is_active' => true,
                    'is_disabled' => false,
                    'is_special' => false,
                    'supports_view' => true,
                    'supports_add' => false,
                    'supports_edit' => false,
                    'supports_delete' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $parentId = DB::table('menus')->where('slug', 'resident-management')->value('id');
            $menus = [
                ['slug' => 'resident-management:residents', 'label' => 'Residents', 'url' => '/residents', 'icon' => 'users', 'sort_order' => 0],
                ['slug' => 'resident-management:households', 'label' => 'Households', 'url' => '/residents/households', 'icon' => 'home', 'sort_order' => 1],
                ['slug' => 'resident-management:puroks', 'label' => 'Puroks', 'url' => '/residents/puroks', 'icon' => 'map-pin', 'sort_order' => 2],
            ];

            foreach ($menus as $menu) {
                DB::table('menus')->updateOrInsert(
                    ['slug' => $menu['slug']],
                    [
                        ...$menu,
                        'parent_id' => $parentId,
                        'is_title' => false,
                        'is_active' => true,
                        'is_disabled' => false,
                        'is_special' => false,
                        'supports_view' => true,
                        'supports_add' => true,
                        'supports_edit' => true,
                        'supports_delete' => true,
                        'supports_export' => true,
                        'supports_print' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
            if (! $adminRoleId) {
                return;
            }

            $menuIds = DB::table('menus')
                ->whereIn('slug', ['resident-management', ...array_column($menus, 'slug')])
                ->pluck('id');

            foreach ($menuIds as $menuId) {
                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role_id' => $adminRoleId, 'menu_id' => $menuId],
                    [
                        'can_view' => true,
                        'can_add' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                        'can_print' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        });
    }

    public function down(): void
    {
        $parentId = DB::table('menus')->where('slug', 'resident-management')->value('id');

        if ($parentId) {
            DB::table('menus')->where('id', $parentId)->delete();
        }
    }
};
