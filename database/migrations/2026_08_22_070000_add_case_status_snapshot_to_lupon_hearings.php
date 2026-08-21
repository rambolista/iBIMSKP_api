<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lupon_hearings', function (Blueprint $table): void {
            if (! Schema::hasColumn('lupon_hearings', 'case_status_at_scheduling')) {
                $table->string('case_status_at_scheduling')->nullable()->after('location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lupon_hearings', function (Blueprint $table): void {
            if (Schema::hasColumn('lupon_hearings', 'case_status_at_scheduling')) {
                $table->dropColumn('case_status_at_scheduling');
            }
        });
    }
};
