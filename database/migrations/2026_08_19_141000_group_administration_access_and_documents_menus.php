<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $administrationId = DB::table('menus')->where('slug', 'administration')->value('id');

            if (! $administrationId) {
                return;
            }

            DB::table('menus')->updateOrInsert(
                ['slug' => 'administration:access'],
                [
                    'label' => 'Access',
                    'url' => null,
                    'icon' => 'shield-lock',
                    'parent_id' => $administrationId,
                    'sort_order' => 0,
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
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            DB::table('menus')->updateOrInsert(
                ['slug' => 'administration:documents'],
                [
                    'label' => 'Documents',
                    'url' => null,
                    'icon' => 'file-text',
                    'parent_id' => $administrationId,
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
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $accessId = DB::table('menus')->where('slug', 'administration:access')->value('id');
            $documentsId = DB::table('menus')->where('slug', 'administration:documents')->value('id');

            if ($accessId) {
                DB::table('menus')->where('slug', 'pages:apps-access-management-menus')->update([
                    'label' => 'Menus',
                    'parent_id' => $accessId,
                    'sort_order' => 0,
                    'is_hidden' => false,
                    'updated_at' => $now,
                ]);

                DB::table('menus')->where('slug', 'pages:apps-access-management-roles')->update([
                    'label' => 'Roles',
                    'parent_id' => $accessId,
                    'sort_order' => 1,
                    'is_hidden' => false,
                    'updated_at' => $now,
                ]);

                DB::table('menus')->where('slug', 'pages:apps-access-management-users')->update([
                    'label' => 'Users',
                    'parent_id' => $accessId,
                    'sort_order' => 2,
                    'is_hidden' => false,
                    'updated_at' => $now,
                ]);
            }

            if ($documentsId) {
                DB::table('menus')->where('slug', 'administration:document-logos')->update([
                    'label' => 'Logos',
                    'parent_id' => $documentsId,
                    'sort_order' => 0,
                    'is_hidden' => false,
                    'updated_at' => $now,
                ]);

                DB::table('menus')->where('slug', 'barangay-services:document-templates')->update([
                    'label' => 'Templates',
                    'parent_id' => $documentsId,
                    'sort_order' => 1,
                    'is_hidden' => false,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $administrationId = DB::table('menus')->where('slug', 'administration')->value('id');
            $barangayServicesId = DB::table('menus')->where('slug', 'barangay-services')->value('id');

            if ($administrationId) {
                DB::table('menus')->where('slug', 'pages:apps-access-management-menus')->update([
                    'parent_id' => $administrationId,
                    'sort_order' => 0,
                    'updated_at' => $now,
                ]);

                DB::table('menus')->where('slug', 'pages:apps-access-management-roles')->update([
                    'parent_id' => $administrationId,
                    'sort_order' => 1,
                    'updated_at' => $now,
                ]);

                DB::table('menus')->where('slug', 'pages:apps-access-management-users')->update([
                    'parent_id' => $administrationId,
                    'sort_order' => 2,
                    'updated_at' => $now,
                ]);

                DB::table('menus')->where('slug', 'administration:document-logos')->update([
                    'label' => 'Document Logos',
                    'parent_id' => $administrationId,
                    'sort_order' => 3,
                    'updated_at' => $now,
                ]);
            }

            if ($barangayServicesId) {
                DB::table('menus')->where('slug', 'barangay-services:document-templates')->update([
                    'label' => 'Document Templates',
                    'parent_id' => $barangayServicesId,
                    'sort_order' => 2,
                    'updated_at' => $now,
                ]);
            }

            DB::table('menus')->whereIn('slug', ['administration:access', 'administration:documents'])->delete();
        });
    }
};
