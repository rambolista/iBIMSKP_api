<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangay_ids', function (Blueprint $table): void {
            $table->string('id_number', 30)->nullable()->change();
            $table->timestamp('verified_at')->nullable()->after('status');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->string('signature_path')->nullable()->after('photo_path');
            $table->foreignId('replacement_of_id')->nullable()->after('resident_id')->constrained('barangay_ids')->nullOnDelete();
            $table->unsignedTinyInteger('validity_years')->nullable()->after('expires_at');
            $table->date('lost_reported_at')->nullable()->after('validity_years');
            $table->text('lost_remarks')->nullable()->after('lost_reported_at');
            $table->text('cancel_reason')->nullable()->after('lost_remarks');
        });

        Schema::create('barangay_id_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barangay_id_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('message');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $statusMap = [
            'pending' => 'application',
            'issued' => 'released',
        ];
        foreach ($statusMap as $from => $to) {
            DB::table('barangay_ids')->where('status', $from)->update(['status' => $to]);
        }

        $now = now();
        DB::table('barangay_ids')->orderBy('id')->get(['id', 'applied_at'])->each(function ($row) use ($now): void {
            DB::table('barangay_id_logs')->insert([
                'barangay_id_id' => $row->id,
                'label' => 'Application filed',
                'message' => 'Filed at the front desk.',
                'actor_id' => null,
                'created_at' => $row->applied_at ?? $now,
                'updated_at' => $row->applied_at ?? $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_id_logs');

        Schema::table('barangay_ids', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('replacement_of_id');
            $table->dropColumn(['verified_at', 'signature_path', 'validity_years', 'lost_reported_at', 'lost_remarks', 'cancel_reason']);
        });
    }
};
