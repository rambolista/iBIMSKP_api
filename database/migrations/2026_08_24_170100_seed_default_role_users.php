<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    private const USERS = [
        ['role' => 'Super Administrator', 'name' => 'Super Administrator', 'email' => 'superadmin@ibimskp.test', 'super_admin' => true],
        ['role' => 'Punong Barangay', 'name' => 'Punong Barangay', 'email' => 'punongbarangay@ibimskp.test', 'super_admin' => false],
        ['role' => 'Barangay Secretary', 'name' => 'Barangay Secretary', 'email' => 'secretary@ibimskp.test', 'super_admin' => false],
        ['role' => 'Lupon Secretary', 'name' => 'Lupon Secretary', 'email' => 'luponsecretary@ibimskp.test', 'super_admin' => false],
        ['role' => 'Barangay Treasurer', 'name' => 'Barangay Treasurer', 'email' => 'treasurer@ibimskp.test', 'super_admin' => false],
        ['role' => 'Barangay Staff', 'name' => 'Barangay Staff', 'email' => 'staff@ibimskp.test', 'super_admin' => false],
    ];

    public function up(): void
    {
        $now = now();
        $hashedPassword = Hash::make('Admin1234');

        DB::transaction(function () use ($now, $hashedPassword): void {
            foreach (self::USERS as $entry) {
                DB::table('users')->updateOrInsert(
                    ['email' => $entry['email']],
                    [
                        'name' => $entry['name'],
                        'password' => $hashedPassword,
                        'status' => 'active',
                        'super_admin' => $entry['super_admin'],
                        'email_verified_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $userId = DB::table('users')->where('email', $entry['email'])->value('id');
                $roleId = DB::table('roles')->where('name', $entry['role'])->value('id');

                if ($userId && $roleId && ! DB::table('role_user')->where('user_id', $userId)->where('role_id', $roleId)->exists()) {
                    DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $roleId]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $emails = array_column(self::USERS, 'email');
            $userIds = DB::table('users')->whereIn('email', $emails)->pluck('id');
            DB::table('role_user')->whereIn('user_id', $userIds)->delete();
            DB::table('users')->whereIn('id', $userIds)->delete();
        });
    }
};
