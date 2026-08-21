<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'resident-management:residents';

    public function up(): void
    {
        $menuId = DB::table('menus')->where('slug', self::SLUG)->value('id');
        if (! $menuId) {
            return;
        }

        DB::table('menu_tabs')
            ->where('menu_id', $menuId)
            ->where('key', 'records')
            ->update([
                'key' => 'cases',
                'label' => 'Cases',
                'icon' => 'gavel',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $menuId = DB::table('menus')->where('slug', self::SLUG)->value('id');
        if (! $menuId) {
            return;
        }

        DB::table('menu_tabs')
            ->where('menu_id', $menuId)
            ->where('key', 'cases')
            ->update([
                'key' => 'records',
                'label' => 'Related Records',
                'icon' => null,
                'updated_at' => now(),
            ]);
    }
};
