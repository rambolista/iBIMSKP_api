<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_templates', function (Blueprint $table): void {
            $table->string('orientation', 10)->default('portrait')->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('id_templates', function (Blueprint $table): void {
            $table->dropColumn('orientation');
        });
    }
};
