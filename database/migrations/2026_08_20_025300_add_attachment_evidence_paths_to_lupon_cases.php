<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lupon_cases', function (Blueprint $table): void {
            if (! Schema::hasColumn('lupon_cases', 'attachment_evidence_paths')) {
                $table->json('attachment_evidence_paths')->nullable()->after('evidence_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lupon_cases', function (Blueprint $table): void {
            if (Schema::hasColumn('lupon_cases', 'attachment_evidence_paths')) {
                $table->dropColumn('attachment_evidence_paths');
            }
        });
    }
};
