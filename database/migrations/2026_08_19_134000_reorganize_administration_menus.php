<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('menus')->updateOrInsert(
                ['slug' => 'administration'],
                [
                    'label' => 'Administration',
                    'url' => null,
                    'icon' => 'settings',
                    'parent_id' => null,
                    'sort_order' => 1,
                    'is_title' => true,
                    'is_active' => true,
                    'is_hidden' => false,
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
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $administrationId = DB::table('menus')->where('slug', 'administration')->value('id');
            if (! $administrationId) {
                return;
            }

            DB::table('menus')->whereIn('slug', [
                'pages:apps-access-management-menus',
                'pages:apps-access-management-roles',
                'pages:apps-access-management-users',
            ])->update([
                'parent_id' => $administrationId,
                'is_hidden' => false,
                'updated_at' => now(),
            ]);

            DB::table('menus')->where('slug', 'pages:apps-access-management-menus')->update([
                'sort_order' => 0,
                'updated_at' => now(),
            ]);

            DB::table('menus')->where('slug', 'pages:apps-access-management-roles')->update([
                'sort_order' => 1,
                'updated_at' => now(),
            ]);

            DB::table('menus')->where('slug', 'pages:apps-access-management-users')->update([
                'sort_order' => 2,
                'updated_at' => now(),
            ]);

            DB::table('menus')->where('slug', 'administration:document-logos')->update([
                'parent_id' => $administrationId,
                'sort_order' => 3,
                'is_hidden' => false,
                'updated_at' => now(),
            ]);

            DB::table('menus')->where('slug', 'pages:apps-access-management-customer-menus')->update([
                'is_hidden' => true,
                'updated_at' => now(),
            ]);

            $accessManagementId = DB::table('menus')->where('slug', 'pages:apps-access-management')->value('id');
            if ($accessManagementId) {
                $tabIds = DB::table('menu_tabs')->where('menu_id', $accessManagementId)->pluck('id');
                if ($tabIds->isNotEmpty()) {
                    DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
                    DB::table('menu_tabs')->whereIn('id', $tabIds)->delete();
                }

                DB::table('role_menu_permissions')->where('menu_id', $accessManagementId)->delete();
                DB::table('menus')->where('id', $accessManagementId)->delete();
            }
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

            $accessManagementId = DB::table('menus')->where('slug', 'pages:apps-access-management')->value('id');
            if ($accessManagementId) {
                DB::table('menus')->whereIn('slug', [
                    'pages:apps-access-management-menus',
                    'pages:apps-access-management-roles',
                    'pages:apps-access-management-users',
                ])->update([
                    'parent_id' => $accessManagementId,
                    'updated_at' => now(),
                ]);
            }

            DB::table('menus')->where('slug', 'pages:apps-access-management-customer-menus')->update([
                'is_hidden' => false,
                'updated_at' => now(),
            ]);
        });
    }
};
