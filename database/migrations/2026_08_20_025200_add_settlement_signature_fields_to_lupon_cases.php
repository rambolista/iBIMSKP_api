<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lupon_cases', function (Blueprint $table): void {
            if (! Schema::hasColumn('lupon_cases', 'settlement_signatures')) {
                $table->text('settlement_signatures')->nullable()->after('settlement_witnesses');
            }

            if (! Schema::hasColumn('lupon_cases', 'settlement_documents_notes')) {
                $table->text('settlement_documents_notes')->nullable()->after('settlement_signatures');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lupon_cases', function (Blueprint $table): void {
            if (Schema::hasColumn('lupon_cases', 'settlement_documents_notes')) {
                $table->dropColumn('settlement_documents_notes');
            }

            if (Schema::hasColumn('lupon_cases', 'settlement_signatures')) {
                $table->dropColumn('settlement_signatures');
            }
        });
    }
};
