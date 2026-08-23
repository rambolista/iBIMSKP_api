<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangay_officials', function (Blueprint $table): void {
            $table->foreignId('resident_id')->nullable()->after('name')->constrained('residents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('barangay_officials', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resident_id');
        });
    }
};
