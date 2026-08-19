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

            if (! $mainId) {
                return;
            }

            $menus = [
                [
                    'slug' => 'katarungang-pambarangay',
                    'label' => 'Katarungang Pambarangay',
                    'url' => '/katarungang-pambarangay',
                    'icon' => 'scale',
                    'parent_id' => $mainId,
                    'sort_order' => 6,
                    'supports' => ['can_view' => true],
                ],
                [
                    'slug' => 'katarungang-pambarangay:cases',
                    'label' => 'Cases',
                    'url' => '/katarungang-pambarangay/cases',
                    'icon' => 'briefcase',
                    'parent_slug' => 'katarungang-pambarangay',
                    'sort_order' => 0,
                    'supports' => ['can_view' => true, 'can_add' => true, 'can_edit' => true, 'can_delete' => true, 'can_execute' => true, 'can_export' => true, 'can_print' => true],
                ],
                [
                    'slug' => 'katarungang-pambarangay:hearings',
                    'label' => 'Hearings',
                    'url' => '/katarungang-pambarangay/hearings',
                    'icon' => 'calendar-event',
                    'parent_slug' => 'katarungang-pambarangay',
                    'sort_order' => 1,
                    'supports' => ['can_view' => true, 'can_add' => true, 'can_edit' => true, 'can_delete' => true, 'can_execute' => true, 'can_export' => true, 'can_print' => true],
                ],
                [
                    'slug' => 'katarungang-pambarangay:pangkat',
                    'label' => 'Pangkat',
                    'url' => '/katarungang-pambarangay/pangkat',
                    'icon' => 'users-group',
                    'parent_slug' => 'katarungang-pambarangay',
                    'sort_order' => 2,
                    'supports' => ['can_view' => true, 'can_add' => true, 'can_edit' => true, 'can_delete' => true, 'can_export' => true, 'can_print' => true],
                ],
                [
                    'slug' => 'katarungang-pambarangay:lupon-members',
                    'label' => 'Lupon Members',
                    'url' => '/katarungang-pambarangay/lupon-members',
                    'icon' => 'user-shield',
                    'parent_slug' => 'katarungang-pambarangay',
                    'sort_order' => 3,
                    'supports' => ['can_view' => true, 'can_add' => true, 'can_edit' => true, 'can_delete' => true, 'can_export' => true, 'can_print' => true],
                ],
                [
                    'slug' => 'katarungang-pambarangay:documents',
                    'label' => 'Documents',
                    'url' => '/katarungang-pambarangay/documents',
                    'icon' => 'files',
                    'parent_slug' => 'katarungang-pambarangay',
                    'sort_order' => 4,
                    'supports' => ['can_view' => true, 'can_add' => true, 'can_edit' => true, 'can_delete' => true, 'can_export' => true, 'can_print' => true],
                ],
            ];

            foreach ($menus as $menu) {
                $parentId = $menu['parent_id'] ?? DB::table('menus')->where('slug', $menu['parent_slug'] ?? '')->value('id');
                $supports = $menu['supports'];

                DB::table('menus')->updateOrInsert(
                    ['slug' => $menu['slug']],
                    [
                        'label' => $menu['label'],
                        'url' => $menu['url'],
                        'icon' => $menu['icon'],
                        'parent_id' => $parentId,
                        'sort_order' => $menu['sort_order'],
                        'is_title' => false,
                        'is_active' => true,
                        'is_hidden' => false,
                        'is_disabled' => false,
                        'is_special' => false,
                        'tab_layout' => 'horizontal',
                        'supports_view' => (bool) ($supports['can_view'] ?? false),
                        'supports_add' => (bool) ($supports['can_add'] ?? false),
                        'supports_edit' => (bool) ($supports['can_edit'] ?? false),
                        'supports_delete' => (bool) ($supports['can_delete'] ?? false),
                        'supports_approve' => (bool) ($supports['can_approve'] ?? false),
                        'supports_execute' => (bool) ($supports['can_execute'] ?? false),
                        'supports_cancel' => (bool) ($supports['can_cancel'] ?? false),
                        'supports_reverse' => (bool) ($supports['can_reverse'] ?? false),
                        'supports_export' => (bool) ($supports['can_export'] ?? false),
                        'supports_print' => (bool) ($supports['can_print'] ?? false),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            if ($adminRoleId) {
                foreach ($menus as $menu) {
                    $menuId = DB::table('menus')->where('slug', $menu['slug'])->value('id');
                    if (! $menuId) {
                        continue;
                    }

                    $supports = $menu['supports'];
                    DB::table('role_menu_permissions')->updateOrInsert(
                        ['role_id' => $adminRoleId, 'menu_id' => $menuId],
                        [
                            'can_view' => (bool) ($supports['can_view'] ?? false),
                            'can_add' => (bool) ($supports['can_add'] ?? false),
                            'can_edit' => (bool) ($supports['can_edit'] ?? false),
                            'can_delete' => (bool) ($supports['can_delete'] ?? false),
                            'can_approve' => (bool) ($supports['can_approve'] ?? false),
                            'can_execute' => (bool) ($supports['can_execute'] ?? false),
                            'can_cancel' => (bool) ($supports['can_cancel'] ?? false),
                            'can_reverse' => (bool) ($supports['can_reverse'] ?? false),
                            'can_export' => (bool) ($supports['can_export'] ?? false),
                            'can_print' => (bool) ($supports['can_print'] ?? false),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }

            $tabsByMenuSlug = [
                'katarungang-pambarangay:cases' => [
                    ['key' => 'case-overview', 'label' => 'Case Overview', 'icon' => 'layout-dashboard'],
                    ['key' => 'parties', 'label' => 'Parties', 'icon' => 'users'],
                    ['key' => 'complaint', 'label' => 'Complaint', 'icon' => 'file-text'],
                    ['key' => 'case-timeline', 'label' => 'Case Timeline', 'icon' => 'timeline'],
                    ['key' => 'hearings', 'label' => 'Hearings', 'icon' => 'calendar-event'],
                    ['key' => 'lupon-pangkat', 'label' => 'Lupon / Pangkat', 'icon' => 'users-group'],
                    ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'user-check'],
                    ['key' => 'mediation-conciliation', 'label' => 'Mediation / Conciliation', 'icon' => 'message-2'],
                    ['key' => 'settlement', 'label' => 'Settlement', 'icon' => 'gavel'],
                    ['key' => 'documents', 'label' => 'Documents', 'icon' => 'files'],
                    ['key' => 'attachments-evidence', 'label' => 'Attachments / Evidence', 'icon' => 'paperclip'],
                    ['key' => 'certificates', 'label' => 'Certificates', 'icon' => 'certificate'],
                    ['key' => 'related-blotter', 'label' => 'Related Blotter', 'icon' => 'book'],
                    ['key' => 'activity-history', 'label' => 'Activity History', 'icon' => 'activity'],
                    ['key' => 'audit-logs', 'label' => 'Audit Logs', 'icon' => 'history'],
                ],
                'katarungang-pambarangay:hearings' => [
                    ['key' => 'hearing-information', 'label' => 'Hearing Information', 'icon' => 'calendar-event'],
                    ['key' => 'parties', 'label' => 'Parties', 'icon' => 'users'],
                    ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'user-check'],
                    ['key' => 'proceedings', 'label' => 'Proceedings', 'icon' => 'notes'],
                    ['key' => 'result', 'label' => 'Result', 'icon' => 'list-check'],
                    ['key' => 'next-action', 'label' => 'Next Action', 'icon' => 'arrow-right'],
                    ['key' => 'documents', 'label' => 'Documents', 'icon' => 'files'],
                    ['key' => 'audit-logs', 'label' => 'Audit Logs', 'icon' => 'history'],
                ],
                'katarungang-pambarangay:pangkat' => [
                    ['key' => 'pangkat-information', 'label' => 'Pangkat Information', 'icon' => 'users-group'],
                    ['key' => 'members', 'label' => 'Members', 'icon' => 'users'],
                    ['key' => 'case', 'label' => 'Case', 'icon' => 'briefcase'],
                    ['key' => 'meetings', 'label' => 'Meetings', 'icon' => 'calendar-time'],
                    ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'user-check'],
                    ['key' => 'proceedings', 'label' => 'Proceedings', 'icon' => 'notes'],
                    ['key' => 'documents', 'label' => 'Documents', 'icon' => 'files'],
                    ['key' => 'audit-logs', 'label' => 'Audit Logs', 'icon' => 'history'],
                ],
                'katarungang-pambarangay:lupon-members' => [
                    ['key' => 'personal-information', 'label' => 'Personal Information', 'icon' => 'user'],
                    ['key' => 'appointment', 'label' => 'Appointment', 'icon' => 'id-badge-2'],
                    ['key' => 'assigned-cases', 'label' => 'Assigned Cases', 'icon' => 'briefcase'],
                    ['key' => 'hearings', 'label' => 'Hearings', 'icon' => 'calendar-event'],
                    ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'user-check'],
                    ['key' => 'documents', 'label' => 'Documents', 'icon' => 'files'],
                    ['key' => 'history', 'label' => 'History', 'icon' => 'timeline'],
                    ['key' => 'audit-logs', 'label' => 'Audit Logs', 'icon' => 'history'],
                ],
                'katarungang-pambarangay:documents' => [
                    ['key' => 'overview', 'label' => 'Overview', 'icon' => 'layout-dashboard'],
                    ['key' => 'case-documents', 'label' => 'Case Documents', 'icon' => 'files'],
                    ['key' => 'notices-summons', 'label' => 'Notices & Summons', 'icon' => 'mail-forward'],
                    ['key' => 'certificates', 'label' => 'Certificates', 'icon' => 'certificate'],
                    ['key' => 'audit-logs', 'label' => 'Audit Logs', 'icon' => 'history'],
                ],
            ];

            foreach ($tabsByMenuSlug as $menuSlug => $tabs) {
                $menuId = DB::table('menus')->where('slug', $menuSlug)->value('id');
                if (! $menuId) {
                    continue;
                }

                foreach ($tabs as $index => $tab) {
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

                $tabIds = DB::table('menu_tabs')
                    ->where('menu_id', $menuId)
                    ->whereIn('key', array_column($tabs, 'key'))
                    ->pluck('id');

                $rolePermissions = DB::table('role_menu_permissions')
                    ->where('menu_id', $menuId)
                    ->where('can_view', true)
                    ->get();

                foreach ($rolePermissions as $rolePermission) {
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
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $menuSlugs = [
                'katarungang-pambarangay',
                'katarungang-pambarangay:cases',
                'katarungang-pambarangay:hearings',
                'katarungang-pambarangay:pangkat',
                'katarungang-pambarangay:lupon-members',
                'katarungang-pambarangay:documents',
            ];

            $menuIds = DB::table('menus')->whereIn('slug', $menuSlugs)->pluck('id');
            if ($menuIds->isEmpty()) {
                return;
            }

            $tabIds = DB::table('menu_tabs')->whereIn('menu_id', $menuIds)->pluck('id');
            if ($tabIds->isNotEmpty()) {
                DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
            }

            DB::table('menu_tabs')->whereIn('menu_id', $menuIds)->delete();
            DB::table('role_menu_permissions')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menus')->whereIn('id', $menuIds)->delete();
        });
    }
};
