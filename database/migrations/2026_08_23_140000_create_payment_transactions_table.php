<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 30)->unique();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->string('payor_name', 255)->nullable();
            $table->string('transaction_type', 100);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('or_number', 60)->nullable();
            $table->date('paid_at')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->text('void_reason')->nullable();
            $table->text('refund_reason')->nullable();
            $table->string('source_module', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_ref', 60)->nullable();
            $table->timestamps();

            $table->index(['status', 'transaction_type']);
            $table->index(['source_module', 'source_id']);
        });

        Schema::create('payment_transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->string('label', 150);
            $table->text('message');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transaction_logs');
        Schema::dropIfExists('payment_transactions');
    }
};
