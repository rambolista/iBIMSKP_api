<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hearing_notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hearing_id')->constrained('lupon_hearings')->cascadeOnDelete();
            $table->date('notified_on');
            $table->timestamps();
            $table->unique(['user_id', 'hearing_id', 'notified_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hearing_notification_logs');
    }
};
