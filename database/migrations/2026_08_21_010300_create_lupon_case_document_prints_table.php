<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lupon_case_document_prints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('lupon_cases')->cascadeOnDelete();
            $table->foreignId('document_template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->timestamp('printed_at');
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['case_id', 'document_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lupon_case_document_prints');
    }
};
