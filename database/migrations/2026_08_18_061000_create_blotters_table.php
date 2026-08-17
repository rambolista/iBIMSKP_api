<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blotters', function (Blueprint $table): void {
            $table->id();
            $table->string('blotter_number', 30)->unique();
            $table->date('incident_date');
            $table->string('incident_time', 30)->nullable();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->string('complainant_name', 255)->nullable();
            $table->string('respondent_name', 255);
            $table->string('location', 255);
            $table->text('narrative');
            $table->text('action_taken')->nullable();
            $table->date('settled_at')->nullable();
            $table->string('status', 30)->default('open');
            $table->text('remarks')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['incident_date', 'status']);
            $table->index(['resident_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blotters');
    }
};

