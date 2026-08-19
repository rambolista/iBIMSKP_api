<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lupon_members', function (Blueprint $table): void {
            $table->id();
            $table->string('member_id', 40)->unique();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->string('position', 255);
            $table->date('date_appointed');
            $table->date('term_start');
            $table->date('term_end');
            $table->string('status', 40)->default('active');
            $table->text('appointment_notes')->nullable();
            $table->text('attendance_notes')->nullable();
            $table->text('documents_notes')->nullable();
            $table->text('history_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lupon_case_member', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lupon_member_id')->constrained('lupon_members')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('lupon_cases')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['lupon_member_id', 'case_id']);
        });

        Schema::create('lupon_hearing_member', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lupon_member_id')->constrained('lupon_members')->cascadeOnDelete();
            $table->foreignId('hearing_id')->constrained('lupon_hearings')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['lupon_member_id', 'hearing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lupon_hearing_member');
        Schema::dropIfExists('lupon_case_member');
        Schema::dropIfExists('lupon_members');
    }
};
