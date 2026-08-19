<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blotters', function (Blueprint $table): void {
            $table->json('evidence_paths')->nullable()->after('action_taken');
        });
    }

    public function down(): void
    {
        Schema::table('blotters', function (Blueprint $table): void {
            $table->dropColumn('evidence_paths');
        });
    }
};
