<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('remarks');
        });
        Schema::table('barangay_ids', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('barangay_ids', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });
        Schema::table('residents', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });
    }
};
