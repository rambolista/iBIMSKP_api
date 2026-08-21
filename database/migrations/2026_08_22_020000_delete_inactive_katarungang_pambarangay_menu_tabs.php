<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABS_BY_MENU_SLUG = [
        'katarungang-pambarangay:pangkat' => [
            ['key' => 'members', 'label' => 'Members', 'icon' => 'users', 'sort_order' => 1],
            ['key' => 'case', 'label' => 'Case', 'icon' => 'briefcase', 'sort_order' => 2],
            ['key' => 'meetings', 'label' => 'Meetings', 'icon' => 'calendar-time', 'sort_order' => 3],
            ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'user-check', 'sort_order' => 4],
            ['key' => 'proceedings', 'label' => 'Proceedings', 'icon' => 'notes', 'sort_order' => 5],
            ['key' => 'documents', 'label' => 'Documents', 'icon' => 'files', 'sort_order' => 6],
        ],
        'katarungang-pambarangay:lupon-members' => [
            ['key' => 'hearings', 'label' => 'Hearings', 'icon' => 'calendar-event', 'sort_order' => 3],
            ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'user-check', 'sort_order' => 4],
            ['key' => 'documents', 'label' => 'Documents', 'icon' => 'files', 'sort_order' => 5],
            ['key' => 'history', 'label' => 'History', 'icon' => 'timeline', 'sort_order' => 6],
        ],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::TABS_BY_MENU_SLUG as $menuSlug => $tabs) {
                $menuId = DB::table('menus')->where('slug', $menuSlug)->value('id');
                if (! $menuId) {
                    continue;
                }

                $keys = array_column($tabs, 'key');
                $tabIds = DB::table('menu_tabs')
                    ->where('menu_id', $menuId)
                    ->whereIn('key', $keys)
                    ->where('is_active', false)
                    ->pluck('id');

                if ($tabIds->isEmpty()) {
                    continue;
                }

                DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
                DB::table('menu_tabs')->whereIn('id', $tabIds)->delete();
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $now = now();

            foreach (self::TABS_BY_MENU_SLUG as $menuSlug => $tabs) {
                $menuId = DB::table('menus')->where('slug', $menuSlug)->value('id');
                if (! $menuId) {
                    continue;
                }

                foreach ($tabs as $tab) {
                    DB::table('menu_tabs')->updateOrInsert(
                        ['menu_id' => $menuId, 'key' => $tab['key']],
                        [
                            'label' => $tab['label'],
                            'icon' => $tab['icon'],
                            'sort_order' => $tab['sort_order'],
                            'is_active' => false,
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
            }
        });
    }
};
