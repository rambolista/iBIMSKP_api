<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $types = [
            ['code' => 'BAR-CLEAR', 'name' => 'Barangay Clearance'],
            ['code' => 'CERT-RES', 'name' => 'Certificate of Residency'],
            ['code' => 'CERT-IND', 'name' => 'Certificate of Indigency'],
            ['code' => 'CERT-GMC', 'name' => 'Certificate of Good Moral Character'],
            ['code' => 'CERT-EMP', 'name' => 'Certificate of Employment'],
            ['code' => 'CERT-NI', 'name' => 'Certificate of No Income'],
            ['code' => 'CERT-NE', 'name' => 'Certificate of Non-Employment'],
            ['code' => 'CERT-LI', 'name' => 'Certificate of Low Income'],
            ['code' => 'OTHER-CERT', 'name' => 'Other Certification'],
        ];
        foreach ($types as $type) {
            DB::table('service_types')->updateOrInsert(['code' => $type['code']], [
                ...$type, 'fee' => 0, 'approval_required' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('service_types')->whereIn('code', ['BAR-CLEAR', 'CERT-RES', 'CERT-IND', 'CERT-GMC', 'CERT-EMP', 'CERT-NI', 'CERT-NE', 'CERT-LI', 'OTHER-CERT'])->delete();
    }
};
