<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lupon_cases', function (Blueprint $table): void {
            $table->string('nature_other', 255)->nullable()->after('nature');
        });
    }

    public function down(): void
    {
        Schema::table('lupon_cases', function (Blueprint $table): void {
            $table->dropColumn('nature_other');
        });
    }
};
