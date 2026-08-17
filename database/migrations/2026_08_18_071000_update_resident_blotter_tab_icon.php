<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $menuId = DB::table('menus')
            ->where('slug', 'resident-management:residents')
            ->value('id');

        if ($menuId) {
            DB::table('menu_tabs')
                ->where('menu_id', $menuId)
                ->where('key', 'blotters')
                ->update([
                    'icon' => 'clipboard-text',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menus')
            ->where('slug', 'resident-management:residents')
            ->value('id');

        if ($menuId) {
            DB::table('menu_tabs')
                ->where('menu_id', $menuId)
                ->where('key', 'blotters')
                ->update([
                    'icon' => 'book-open',
                    'updated_at' => now(),
                ]);
        }
    }
};
