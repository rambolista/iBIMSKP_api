<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->string('nationality', 100)->nullable()->default('Filipino')->change();
        });
    }

    public function down(): void
    {
        DB::table('residents')
            ->whereNull('nationality')
            ->update(['nationality' => 'Filipino']);

        Schema::table('residents', function (Blueprint $table): void {
            $table->string('nationality', 100)->nullable(false)->default('Filipino')->change();
        });
    }
};
