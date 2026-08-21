<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEACTIVATED_KEYS = ['members', 'case', 'meetings', 'attendance', 'proceedings', 'documents'];

    public function up(): void
    {
        $menuId = DB::table('menus')
            ->where('slug', 'katarungang-pambarangay:pangkat')
            ->value('id');

        if (! $menuId) {
            return;
        }

        DB::table('menu_tabs')
            ->where('menu_id', $menuId)
            ->where('key', 'pangkat-information')
            ->update([
                'key' => 'overview',
                'label' => 'Overview',
                'updated_at' => now(),
            ]);

        DB::table('menu_tabs')
            ->where('menu_id', $menuId)
            ->whereIn('key', self::DEACTIVATED_KEYS)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $menuId = DB::table('menus')
            ->where('slug', 'katarungang-pambarangay:pangkat')
            ->value('id');

        if (! $menuId) {
            return;
        }

        DB::table('menu_tabs')
            ->where('menu_id', $menuId)
            ->where('key', 'overview')
            ->update([
                'key' => 'pangkat-information',
                'label' => 'Pangkat Information',
                'updated_at' => now(),
            ]);

        DB::table('menu_tabs')
            ->where('menu_id', $menuId)
            ->whereIn('key', self::DEACTIVATED_KEYS)
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
