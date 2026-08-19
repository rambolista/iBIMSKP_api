<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('menus')->where('slug', 'administration')->update([
                'parent_id' => null,
                'is_title' => true,
                'is_hidden' => false,
                'updated_at' => now(),
            ]);

            $administrationId = DB::table('menus')->where('slug', 'administration')->value('id');
            if (! $administrationId) {
                return;
            }

            DB::table('menus')->whereIn('slug', [
                'pages:apps-access-management-menus',
                'pages:apps-access-management-roles',
                'pages:apps-access-management-users',
                'administration:document-logos',
            ])->update([
                'parent_id' => $administrationId,
                'is_hidden' => false,
                'updated_at' => now(),
            ]);

            $accessManagementId = DB::table('menus')->where('slug', 'pages:apps-access-management')->value('id');
            if (! $accessManagementId) {
                return;
            }

            $tabIds = DB::table('menu_tabs')->where('menu_id', $accessManagementId)->pluck('id');
            if ($tabIds->isNotEmpty()) {
                DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
                DB::table('menu_tabs')->whereIn('id', $tabIds)->delete();
            }

            DB::table('role_menu_permissions')->where('menu_id', $accessManagementId)->delete();
            DB::table('menus')->where('id', $accessManagementId)->delete();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $appsId = DB::table('menus')->where('slug', 'apps')->value('id');
            DB::table('menus')->updateOrInsert(
                ['slug' => 'pages:apps-access-management'],
                [
                    'label' => 'Access Management',
                    'url' => null,
                    'icon' => 'shield-lock',
                    'parent_id' => $appsId,
                    'sort_order' => 1,
                    'is_title' => false,
                    'is_active' => true,
                    'is_hidden' => false,
                    'is_disabled' => false,
                    'is_special' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        });
    }
};
