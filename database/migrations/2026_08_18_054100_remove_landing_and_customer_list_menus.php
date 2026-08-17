<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->deleteMenuBySlug('pages:apps-customers');
            $this->deleteMenuBySlug('customers');
            $this->deleteMenuBySlug('pages:landing');
        });
    }

    public function down(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $mainId = DB::table('menus')->where('slug', 'main')->value('id');

            if ($mainId) {
                DB::table('menus')->updateOrInsert(
                    ['slug' => 'pages:landing'],
                    [
                        'label' => 'Landing',
                        'url' => '/landing',
                        'icon' => 'rocket',
                        'parent_id' => $mainId,
                        'sort_order' => 1,
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
            }

            DB::table('menus')->updateOrInsert(
                ['slug' => 'customers'],
                [
                    'label' => 'Customers',
                    'url' => null,
                    'icon' => null,
                    'parent_id' => null,
                    'sort_order' => 100,
                    'is_title' => true,
                    'is_active' => true,
                    'is_disabled' => false,
                    'is_special' => false,
                    'supports_view' => false,
                    'supports_add' => false,
                    'supports_edit' => false,
                    'supports_delete' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $customersTitleId = DB::table('menus')->where('slug', 'customers')->value('id');
            if (! $customersTitleId) {
                return;
            }

            DB::table('menus')->updateOrInsert(
                ['slug' => 'pages:apps-customers'],
                [
                    'label' => 'Customer List',
                    'url' => '/apps/customers',
                    'icon' => 'users',
                    'parent_id' => $customersTitleId,
                    'sort_order' => 0,
                    'is_title' => false,
                    'is_active' => true,
                    'is_disabled' => false,
                    'is_special' => false,
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
        });
    }

    private function deleteMenuBySlug(string $slug): void
    {
        $menuId = DB::table('menus')->where('slug', $slug)->value('id');
        if (! $menuId) {
            return;
        }

        $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id');
        if ($tabIds->isNotEmpty()) {
            DB::table('role_menu_tab_permissions')->whereIn('menu_tab_id', $tabIds)->delete();
            DB::table('menu_tabs')->whereIn('id', $tabIds)->delete();
        }

        DB::table('role_menu_permissions')->where('menu_id', $menuId)->delete();
        DB::table('menus')->where('id', $menuId)->delete();
    }
};
