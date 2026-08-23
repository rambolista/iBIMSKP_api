<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $mainId = DB::table('menus')->where('slug', 'main')->value('id');
            $administrationId = DB::table('menus')->where('slug', 'administration')->value('id');

            if (! $administrationId) {
                return;
            }

            DB::table('menus')
                ->where('slug', 'administration:dropdown-settings')
                ->where('parent_id', $administrationId)
                ->update(['sort_order' => 5, 'updated_at' => now()]);

            DB::table('menus')
                ->where('slug', 'user-activity')
                ->where('parent_id', $mainId)
                ->update(['parent_id' => $administrationId, 'sort_order' => 4, 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $mainId = DB::table('menus')->where('slug', 'main')->value('id');
            $administrationId = DB::table('menus')->where('slug', 'administration')->value('id');

            DB::table('menus')
                ->where('slug', 'user-activity')
                ->where('parent_id', $administrationId)
                ->update(['parent_id' => $mainId, 'sort_order' => 9, 'updated_at' => now()]);

            DB::table('menus')
                ->where('slug', 'administration:dropdown-settings')
                ->where('parent_id', $administrationId)
                ->update(['sort_order' => 4, 'updated_at' => now()]);
        });
    }
};
