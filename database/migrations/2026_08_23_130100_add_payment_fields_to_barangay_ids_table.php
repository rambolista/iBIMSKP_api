<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangay_ids', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('unpaid')->after('status');
            $table->string('or_number', 60)->nullable()->after('payment_status');
            $table->date('paid_at')->nullable()->after('or_number');
        });
    }

    public function down(): void
    {
        Schema::table('barangay_ids', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'or_number', 'paid_at']);
        });
    }
};
