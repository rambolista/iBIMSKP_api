<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Menu id => [supports_* columns to enable, can_* columns to grant Admin].
     * Both lists are always identical here — every capability being newly
     * supported on a menu is also granted to Admin in the same pass, since
     * Admin already has can_edit=true everywhere these actions previously
     * lived and must not lose access when they move to a distinct capability.
     */
    private const MENU_CAPABILITIES = [
        300 => ['approve', 'cancel'], // barangay-services:requests
        302 => ['approve', 'cancel'], // barangay-id
        304 => ['cancel', 'reverse'], // blotter
        320 => ['cancel', 'reverse'], // payments
        310 => ['approve'],           // katarungang-pambarangay:cases
    ];

    public function up(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
        $now = now();

        DB::transaction(function () use ($adminRoleId, $now): void {
            foreach (self::MENU_CAPABILITIES as $menuId => $capabilities) {
                $supportsColumns = [];
                $canColumns = [];
                foreach ($capabilities as $capability) {
                    $supportsColumns["supports_{$capability}"] = true;
                    $canColumns["can_{$capability}"] = true;
                }

                DB::table('menus')->where('id', $menuId)->update($supportsColumns);

                if ($adminRoleId) {
                    DB::table('role_menu_permissions')
                        ->where('role_id', $adminRoleId)
                        ->where('menu_id', $menuId)
                        ->update([...$canColumns, 'updated_at' => $now]);
                }
            }
        });
    }

    public function down(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
        $now = now();

        DB::transaction(function () use ($adminRoleId, $now): void {
            foreach (self::MENU_CAPABILITIES as $menuId => $capabilities) {
                $supportsColumns = [];
                $canColumns = [];
                foreach ($capabilities as $capability) {
                    $supportsColumns["supports_{$capability}"] = false;
                    $canColumns["can_{$capability}"] = false;
                }

                DB::table('menus')->where('id', $menuId)->update($supportsColumns);

                if ($adminRoleId) {
                    DB::table('role_menu_permissions')
                        ->where('role_id', $adminRoleId)
                        ->where('menu_id', $menuId)
                        ->update([...$canColumns, 'updated_at' => $now]);
                }
            }
        });
    }
};
