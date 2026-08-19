<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lupon_pangkats', function (Blueprint $table): void {
            $table->id();
            $table->string('pangkat_id', 40)->unique();
            $table->foreignId('case_id')->constrained('lupon_cases')->cascadeOnDelete();
            $table->date('date_constituted');
            $table->text('members_summary')->nullable();
            $table->text('meeting_notes')->nullable();
            $table->text('attendance_notes')->nullable();
            $table->text('proceedings_notes')->nullable();
            $table->text('documents_notes')->nullable();
            $table->string('status', 40)->default('constituted');
            $table->text('remarks')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lupon_pangkats');
    }
};
