<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'katarungang-pambarangay:cases';

    private const TABS = [
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
    ];

    public function up(): void
    {
        // The Cases menu is served entirely by bespoke pages
        // (cases/details.jsx, cases/form.jsx) which never read these
        // database-seeded tabs, so they only clutter Menu/Role editing.
        DB::transaction(function (): void {
            $menuId = DB::table('menus')->where('slug', self::SLUG)->value('id');
            if (! $menuId) {
                return;
            }

            $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
            if ($tabIds->isEmpty()) {
                return;
            }

            DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
            DB::table('menu_tabs')->whereIn('id', $tabIds)->delete();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $menuId = DB::table('menus')->where('slug', self::SLUG)->value('id');
            if (! $menuId) {
                return;
            }

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

            $tabIds = DB::table('menu_tabs')
                ->where('menu_id', $menuId)
                ->whereIn('key', array_column(self::TABS, 'key'))
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
        });
    }
};
