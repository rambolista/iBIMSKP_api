<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['meeting_notes', 'attendance_notes', 'proceedings_notes', 'documents_notes'];

    public function up(): void
    {
        Schema::table('lupon_pangkats', function (Blueprint $table): void {
            $existing = array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn('lupon_pangkats', $column));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }

    public function down(): void
    {
        Schema::table('lupon_pangkats', function (Blueprint $table): void {
            if (! Schema::hasColumn('lupon_pangkats', 'meeting_notes')) {
                $table->text('meeting_notes')->nullable()->after('members_summary');
            }
            if (! Schema::hasColumn('lupon_pangkats', 'attendance_notes')) {
                $table->text('attendance_notes')->nullable()->after('meeting_notes');
            }
            if (! Schema::hasColumn('lupon_pangkats', 'proceedings_notes')) {
                $table->text('proceedings_notes')->nullable()->after('attendance_notes');
            }
            if (! Schema::hasColumn('lupon_pangkats', 'documents_notes')) {
                $table->text('documents_notes')->nullable()->after('proceedings_notes');
            }
        });
    }
};
