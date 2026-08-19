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
            $table->string('paper_size', 20)->default('a4')->after('logo_placements');
        });

        DB::table('document_templates')
            ->whereNull('paper_size')
            ->update(['paper_size' => 'a4']);
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropColumn('paper_size');
        });
    }
};
