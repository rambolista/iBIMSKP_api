<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lupon_hearings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('lupon_cases')->cascadeOnDelete();
            $table->date('hearing_date');
            $table->time('hearing_time')->nullable();
            $table->string('type', 40);
            $table->string('location')->nullable();
            $table->text('attendance_summary')->nullable();
            $table->text('result_summary')->nullable();
            $table->date('next_hearing_at')->nullable();
            $table->string('status', 40)->default('scheduled');
            $table->text('proceedings')->nullable();
            $table->text('next_action_notes')->nullable();
            $table->text('documents_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lupon_hearings');
    }
};
