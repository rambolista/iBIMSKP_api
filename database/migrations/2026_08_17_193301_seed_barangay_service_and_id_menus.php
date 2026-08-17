<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        DB::transaction(function () use ($now): void {
            $mainId = DB::table('menus')->where('slug', 'main')->value('id');
            $menus = [
                ['slug' => 'barangay-services', 'label' => 'Barangay Services', 'url' => null, 'icon' => 'clipboard-list', 'parent_id' => $mainId, 'sort_order' => 3, 'supports_add' => false, 'supports_edit' => false, 'supports_delete' => false],
                ['slug' => 'barangay-services:requests', 'label' => 'Service Requests', 'url' => '/barangay-services/requests', 'icon' => 'file-description', 'parent_slug' => 'barangay-services', 'sort_order' => 0],
                ['slug' => 'barangay-services:types', 'label' => 'Service Types', 'url' => '/barangay-services/types', 'icon' => 'settings', 'parent_slug' => 'barangay-services', 'sort_order' => 1],
                ['slug' => 'barangay-id', 'label' => 'Barangay ID', 'url' => '/barangay-id', 'icon' => 'id', 'parent_id' => $mainId, 'sort_order' => 4],
            ];
            foreach ($menus as $menu) {
                $parentId = $menu['parent_id'] ?? DB::table('menus')->where('slug', $menu['parent_slug'])->value('id');
                unset($menu['parent_id'], $menu['parent_slug']);
                DB::table('menus')->updateOrInsert(['slug' => $menu['slug']], [
                    ...$menu, 'parent_id' => $parentId, 'is_title' => false, 'is_active' => true, 'is_disabled' => false, 'is_special' => false,
                    'supports_view' => true, 'supports_add' => $menu['supports_add'] ?? true, 'supports_edit' => $menu['supports_edit'] ?? true,
                    'supports_delete' => $menu['supports_delete'] ?? true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
            foreach (DB::table('menus')->whereIn('slug', array_column($menus, 'slug'))->pluck('id') as $menuId) {
                DB::table('role_menu_permissions')->updateOrInsert(['role_id' => $adminRoleId, 'menu_id' => $menuId], [
                    'can_view' => true, 'can_add' => true, 'can_edit' => true, 'can_delete' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menus')->whereIn('slug', ['barangay-services', 'barangay-id'])->delete();
    }
};
