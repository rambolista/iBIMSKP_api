<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('resident_number', 30)->unique();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purok_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 30)->nullable();
            $table->date('birth_date');
            $table->string('sex', 20);
            $table->string('civil_status', 30)->nullable();
            $table->string('nationality', 100)->default('Filipino');
            $table->string('place_of_birth', 255)->nullable();
            $table->string('house_number', 100)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('mobile_number', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('occupation', 150)->nullable();
            $table->string('employer', 150)->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();
            $table->boolean('is_voter')->default(false);
            $table->boolean('is_senior_citizen')->default(false);
            $table->boolean('is_pwd')->default(false);
            $table->boolean('is_4ps')->default(false);
            $table->boolean('is_solo_parent')->default(false);
            $table->boolean('is_household_head')->default(false);
            $table->string('status', 20)->default('active');
            $table->date('registered_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['last_name', 'first_name', 'birth_date']);
            $table->index(['purok_id', 'status']);
            $table->index(['household_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
