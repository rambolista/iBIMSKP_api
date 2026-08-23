<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NEW_ORDER = [
        'administration:barangay-setup' => 2,
        'administration:barangay-officials' => 3,
        'administration:dropdown-settings' => 4,
    ];

    private const OLD_ORDER = [
        'administration:barangay-setup' => 1,
        'administration:barangay-officials' => 2,
        'administration:dropdown-settings' => 2,
    ];

    public function up(): void
    {
        foreach (self::NEW_ORDER as $slug => $sortOrder) {
            DB::table('menus')->where('slug', $slug)->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::OLD_ORDER as $slug => $sortOrder) {
            DB::table('menus')->where('slug', $slug)->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
        }
    }
};
