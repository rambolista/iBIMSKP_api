<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['attendance_notes', 'documents_notes', 'history_notes'];

    public function up(): void
    {
        Schema::table('lupon_members', function (Blueprint $table): void {
            $existing = array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn('lupon_members', $column));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }

    public function down(): void
    {
        Schema::table('lupon_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('lupon_members', 'attendance_notes')) {
                $table->text('attendance_notes')->nullable()->after('appointment_notes');
            }
            if (! Schema::hasColumn('lupon_members', 'documents_notes')) {
                $table->text('documents_notes')->nullable()->after('attendance_notes');
            }
            if (! Schema::hasColumn('lupon_members', 'history_notes')) {
                $table->text('history_notes')->nullable()->after('documents_notes');
            }
        });
    }
};
