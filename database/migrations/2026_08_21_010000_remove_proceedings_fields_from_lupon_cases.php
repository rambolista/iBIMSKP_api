<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['hearing_notes', 'attendance_notes', 'mediation_notes', 'conciliation_notes'];

    public function up(): void
    {
        Schema::table('lupon_cases', function (Blueprint $table): void {
            $existing = array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn('lupon_cases', $column));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }

    public function down(): void
    {
        Schema::table('lupon_cases', function (Blueprint $table): void {
            if (! Schema::hasColumn('lupon_cases', 'hearing_notes')) {
                $table->text('hearing_notes')->nullable()->after('case_timeline');
            }
            if (! Schema::hasColumn('lupon_cases', 'attendance_notes')) {
                $table->text('attendance_notes')->nullable()->after('hearing_notes');
            }
            if (! Schema::hasColumn('lupon_cases', 'mediation_notes')) {
                $table->text('mediation_notes')->nullable()->after('attendance_notes');
            }
            if (! Schema::hasColumn('lupon_cases', 'conciliation_notes')) {
                $table->text('conciliation_notes')->nullable()->after('mediation_notes');
            }
        });
    }
};
