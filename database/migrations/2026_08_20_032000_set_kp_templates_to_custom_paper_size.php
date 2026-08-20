<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('document_type', 'kp_forms')
            ->update([
                'paper_size' => 'custom',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('document_templates')
            ->where('document_type', 'kp_forms')
            ->update([
                'paper_size' => 'a4',
                'updated_at' => now(),
            ]);
    }
};
