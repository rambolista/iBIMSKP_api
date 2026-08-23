<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_templates', function (Blueprint $table): void {
            $table->boolean('show_contact_number')->default(true)->after('orientation');
            $table->boolean('show_barangay_address')->default(true)->after('orientation');
        });
    }

    public function down(): void
    {
        Schema::table('id_templates', function (Blueprint $table): void {
            $table->dropColumn(['show_contact_number', 'show_barangay_address']);
        });
    }
};
