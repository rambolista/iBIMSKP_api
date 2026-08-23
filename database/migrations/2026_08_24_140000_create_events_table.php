<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('event_type', 40)->nullable();
            $table->string('location', 255)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->boolean('is_all_day')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
