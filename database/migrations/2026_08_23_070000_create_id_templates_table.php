<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 40)->nullable()->unique();
            $table->string('background_type', 10)->default('color');
            $table->string('background_color', 7)->default('#1B3A5C');
            $table->string('background_image_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('text_color', 7)->default('#FFFFFF');
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        DB::table('id_templates')->insert([
            'name' => 'Default Barangay ID',
            'code' => 'IDT-DEFAULT',
            'background_type' => 'color',
            'background_color' => '#1B3A5C',
            'text_color' => '#FFFFFF',
            'is_default' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('id_templates');
    }
};
