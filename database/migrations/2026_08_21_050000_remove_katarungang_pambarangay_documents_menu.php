<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'katarungang-pambarangay:documents';

    private const TABS = [
        ['key' => 'overview', 'label' => 'Overview', 'icon' => 'layout-dashboard'],
        ['key' => 'case-documents', 'label' => 'Case Documents', 'icon' => 'files'],
        ['key' => 'notices-summons', 'label' => 'Notices & Summons', 'icon' => 'mail-forward'],
        ['key' => 'certificates', 'label' => 'Certificates', 'icon' => 'certificate'],
        ['key' => 'audit-logs', 'label' => 'Audit Logs', 'icon' => 'history'],
    ];

    public function up(): void
    {
        // menu_tabs, role_menu_permissions, and role_menu_tab_permissions all
        // cascade-delete from this row, since printable documents now live
        // entirely inside the Case detail page.
        DB::table('menus')->where('slug', self::SLUG)->delete();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $parentId = DB::table('menus')->where('slug', 'katarungang-pambarangay')->value('id');

            if (! $parentId) {
                return;
            }

            DB::table('menus')->updateOrInsert(
                ['slug' => self::SLUG],
                [
                    'label' => 'Documents',
                    'url' => '/katarungang-pambarangay/documents',
                    'icon' => 'files',
                    'parent_id' => $parentId,
                    'sort_order' => 4,
                    'is_title' => false,
                    'is_active' => true,
                    'is_hidden' => false,
                    'is_disabled' => false,
                    'is_special' => false,
                    'tab_layout' => 'horizontal',
                    'supports_view' => true,
                    'supports_add' => true,
                    'supports_edit' => true,
                    'supports_delete' => true,
                    'supports_approve' => false,
                    'supports_execute' => false,
                    'supports_cancel' => false,
                    'supports_reverse' => false,
                    'supports_export' => true,
                    'supports_print' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $menuId = DB::table('menus')->where('slug', self::SLUG)->value('id');

            foreach (self::TABS as $index => $tab) {
                DB::table('menu_tabs')->updateOrInsert(
                    ['menu_id' => $menuId, 'key' => $tab['key']],
                    [
                        'label' => $tab['label'],
                        'icon' => $tab['icon'],
                        'sort_order' => $index,
                        'is_active' => true,
                        'supports_view' => true,
                        'supports_add' => true,
                        'supports_edit' => true,
                        'supports_delete' => true,
                        'supports_approve' => false,
                        'supports_execute' => true,
                        'supports_cancel' => false,
                        'supports_reverse' => false,
                        'supports_export' => true,
                        'supports_print' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $parentRolePermissions = DB::table('role_menu_permissions')
                ->where('menu_id', $parentId)
                ->where('can_view', true)
                ->get();

            foreach ($parentRolePermissions as $rolePermission) {
                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role_id' => $rolePermission->role_id, 'menu_id' => $menuId],
                    [
                        'can_view' => true,
                        'can_add' => (bool) $rolePermission->can_add,
                        'can_edit' => (bool) $rolePermission->can_edit,
                        'can_delete' => (bool) $rolePermission->can_delete,
                        'can_approve' => (bool) $rolePermission->can_approve,
                        'can_execute' => (bool) $rolePermission->can_execute,
                        'can_cancel' => (bool) $rolePermission->can_cancel,
                        'can_reverse' => (bool) $rolePermission->can_reverse,
                        'can_export' => (bool) $rolePermission->can_export,
                        'can_print' => (bool) $rolePermission->can_print,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
                foreach ($tabIds as $tabId) {
                    DB::table('role_menu_tab_permissions')->updateOrInsert(
                        ['role_id' => $rolePermission->role_id, 'menu_tab_id' => $tabId],
                        [
                            'can_view' => true,
                            'can_add' => (bool) $rolePermission->can_add,
                            'can_edit' => (bool) $rolePermission->can_edit,
                            'can_delete' => (bool) $rolePermission->can_delete,
                            'can_approve' => (bool) $rolePermission->can_approve,
                            'can_execute' => (bool) $rolePermission->can_execute,
                            'can_cancel' => (bool) $rolePermission->can_cancel,
                            'can_reverse' => (bool) $rolePermission->can_reverse,
                            'can_export' => (bool) $rolePermission->can_export,
                            'can_print' => (bool) $rolePermission->can_print,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        });
    }
};
