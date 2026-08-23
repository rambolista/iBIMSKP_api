<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CASHIERS_NAME = 'Cashiers';

    public function up(): void
    {
        DB::transaction(function (): void {
            $cashiersRoleId = DB::table('roles')->where('name', self::CASHIERS_NAME)->value('id');
            $treasurerRoleId = DB::table('roles')->where('name', 'Barangay Treasurer')->value('id');
            $userId = DB::table('users')->where('email', 'rjmoris@gmail.com')->value('id');

            if ($userId && $treasurerRoleId && ! DB::table('role_user')->where('user_id', $userId)->where('role_id', $treasurerRoleId)->exists()) {
                DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $treasurerRoleId]);
            }

            if (! $cashiersRoleId) {
                return;
            }

            if ($userId) {
                DB::table('role_user')->where('user_id', $userId)->where('role_id', $cashiersRoleId)->delete();
            }

            DB::table('role_menu_tab_permissions')->where('role_id', $cashiersRoleId)->delete();
            DB::table('role_menu_permissions')->where('role_id', $cashiersRoleId)->delete();
            DB::table('role_user')->where('role_id', $cashiersRoleId)->delete();
            DB::table('roles')->where('id', $cashiersRoleId)->delete();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $now = now();

            DB::table('roles')->updateOrInsert(
                ['name' => self::CASHIERS_NAME],
                [
                    'description' => 'Tagasingil',
                    'key_responsibilities' => "Kumain / Matulog / Mangolekta ng pera / Magutos / Umuwi ng maaga",
                    'icon' => 'adjustments-dollar',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $cashiersRoleId = DB::table('roles')->where('name', self::CASHIERS_NAME)->value('id');
            $treasurerRoleId = DB::table('roles')->where('name', 'Barangay Treasurer')->value('id');
            $userId = DB::table('users')->where('email', 'rjmoris@gmail.com')->value('id');

            if ($userId && $cashiersRoleId && ! DB::table('role_user')->where('user_id', $userId)->where('role_id', $cashiersRoleId)->exists()) {
                DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $cashiersRoleId]);
            }

            if ($userId && $treasurerRoleId) {
                DB::table('role_user')->where('user_id', $userId)->where('role_id', $treasurerRoleId)->delete();
            }
        });
    }
};
