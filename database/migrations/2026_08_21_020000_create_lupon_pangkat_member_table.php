<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lupon_pangkat_member', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lupon_member_id')->constrained('lupon_members')->cascadeOnDelete();
            $table->foreignId('pangkat_id')->constrained('lupon_pangkats')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['lupon_member_id', 'pangkat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lupon_pangkat_member');
    }
};
