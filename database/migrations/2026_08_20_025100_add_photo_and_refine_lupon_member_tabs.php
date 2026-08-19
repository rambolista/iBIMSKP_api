<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lupon_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('lupon_members', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('resident_id');
            }
        });

        $menuId = DB::table('menus')
            ->where('slug', 'katarungang-pambarangay:lupon-members')
            ->value('id');

        if (! $menuId) {
            return;
        }

        DB::table('menu_tabs')
            ->where('menu_id', $menuId)
            ->where('key', 'assigned-cases')
            ->update([
                'key' => 'assignments',
                'label' => 'Assignments',
                'icon' => 'briefcase',
                'updated_at' => now(),
            ]);

        DB::table('menu_tabs')
            ->where('menu_id', $menuId)
            ->where('key', 'hearings')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $menuId = DB::table('menus')
            ->where('slug', 'katarungang-pambarangay:lupon-members')
            ->value('id');

        if ($menuId) {
            DB::table('menu_tabs')
                ->where('menu_id', $menuId)
                ->where('key', 'assignments')
                ->update([
                    'key' => 'assigned-cases',
                    'label' => 'Assigned Cases',
                    'icon' => 'briefcase',
                    'updated_at' => now(),
                ]);

            DB::table('menu_tabs')
                ->where('menu_id', $menuId)
                ->where('key', 'hearings')
                ->update([
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
        }

        Schema::table('lupon_members', function (Blueprint $table): void {
            if (Schema::hasColumn('lupon_members', 'photo_path')) {
                $table->dropColumn('photo_path');
            }
        });
    }
};
