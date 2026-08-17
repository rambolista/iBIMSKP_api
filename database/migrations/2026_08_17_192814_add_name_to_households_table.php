<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            Schema::table('households', function (Blueprint $table): void {
                $table->string('name', 150)->nullable()->after('household_number');
            });

            DB::table('households')
                ->whereNull('name')
                ->update([
                    'name' => DB::raw("CONCAT('Household ', household_number)"),
                ]);

            Schema::table('households', function (Blueprint $table): void {
                $table->string('name', 150)->nullable(false)->change();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            Schema::table('households', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        });
    }
};
