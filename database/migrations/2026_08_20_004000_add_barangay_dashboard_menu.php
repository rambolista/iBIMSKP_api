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
                ['slug' => 'dashboard:barangay'],
                [
                    'label' => 'Dashboard',
                    'url' => '/dashboard/barangay',
                    'icon' => 'layout-dashboard',
                    'parent_id' => $mainId,
                    'sort_order' => 0,
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

            $menuId = DB::table('menus')->where('slug', 'dashboard:barangay')->value('id');
            if (! $menuId) {
                return;
            }

            $sourceMenuIds = DB::table('menus')
                ->whereIn('url', [
                    '/residents',
                    '/residents/households',
                    '/residents/puroks',
                    '/blotter',
                    '/barangay-services/requests',
                    '/barangay-id',
                ])
                ->pluck('id');

            $roleIds = DB::table('role_menu_permissions')
                ->whereIn('menu_id', $sourceMenuIds)
                ->where('can_view', true)
                ->pluck('role_id')
                ->when($adminRoleId, fn ($collection) => $collection->push($adminRoleId))
                ->unique()
                ->values();

            foreach ($roleIds as $roleId) {
                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $menuId],
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
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $menuId = DB::table('menus')->where('slug', 'dashboard:barangay')->value('id');
            if (! $menuId) {
                return;
            }

            DB::table('role_menu_permissions')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        });
    }
};
