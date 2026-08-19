<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lupon_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('case_number', 40)->unique();
            $table->date('date_filed');
            $table->foreignId('complainant_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->string('complainant_name')->nullable();
            $table->foreignId('respondent_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->string('respondent_name')->nullable();
            $table->string('nature', 255);
            $table->string('assigned_lupon')->nullable();
            $table->string('status', 40)->default('filed');
            $table->date('next_hearing_at')->nullable();
            $table->date('date_closed')->nullable();
            $table->text('complaint_details')->nullable();
            $table->text('case_timeline')->nullable();
            $table->text('hearing_notes')->nullable();
            $table->text('attendance_notes')->nullable();
            $table->text('mediation_notes')->nullable();
            $table->text('conciliation_notes')->nullable();
            $table->string('settlement_status', 40)->default('none');
            $table->date('settlement_date')->nullable();
            $table->text('settlement_agreement')->nullable();
            $table->text('settlement_terms')->nullable();
            $table->text('settlement_parties')->nullable();
            $table->text('settlement_witnesses')->nullable();
            $table->string('certificate_status', 40)->default('not_issued');
            $table->string('certificate_number', 60)->nullable();
            $table->date('certificate_issued_at')->nullable();
            $table->text('certificate_remarks')->nullable();
            $table->foreignId('related_blotter_id')->nullable()->constrained('blotters')->nullOnDelete();
            $table->text('documents_notes')->nullable();
            $table->text('evidence_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lupon_cases');
    }
};
