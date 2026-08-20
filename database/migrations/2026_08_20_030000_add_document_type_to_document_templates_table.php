<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->string('document_type', 30)->default('certificate')->after('logo_placements');
        });

        DB::table('document_templates')
            ->whereNull('document_type')
            ->update(['document_type' => 'certificate']);
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropColumn('document_type');
        });
    }
};
