<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'type',
        'attendance_summary',
        'result_summary',
        'next_hearing_at',
        'status',
        'proceedings',
        'next_action_notes',
        'documents_notes',
        'remarks',
    ];

    public function up(): void
    {
        Schema::table('lupon_hearings', function (Blueprint $table): void {
            $existing = array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn('lupon_hearings', $column));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }

    public function down(): void
    {
        Schema::table('lupon_hearings', function (Blueprint $table): void {
            if (! Schema::hasColumn('lupon_hearings', 'type')) {
                $table->string('type')->nullable()->after('hearing_time');
            }
            if (! Schema::hasColumn('lupon_hearings', 'attendance_summary')) {
                $table->text('attendance_summary')->nullable()->after('location');
            }
            if (! Schema::hasColumn('lupon_hearings', 'result_summary')) {
                $table->text('result_summary')->nullable()->after('attendance_summary');
            }
            if (! Schema::hasColumn('lupon_hearings', 'next_hearing_at')) {
                $table->date('next_hearing_at')->nullable()->after('result_summary');
            }
            if (! Schema::hasColumn('lupon_hearings', 'status')) {
                $table->string('status')->nullable()->after('next_hearing_at');
            }
            if (! Schema::hasColumn('lupon_hearings', 'proceedings')) {
                $table->text('proceedings')->nullable()->after('status');
            }
            if (! Schema::hasColumn('lupon_hearings', 'next_action_notes')) {
                $table->text('next_action_notes')->nullable()->after('proceedings');
            }
            if (! Schema::hasColumn('lupon_hearings', 'documents_notes')) {
                $table->text('documents_notes')->nullable()->after('next_action_notes');
            }
            if (! Schema::hasColumn('lupon_hearings', 'remarks')) {
                $table->text('remarks')->nullable()->after('documents_notes');
            }
        });
    }
};
