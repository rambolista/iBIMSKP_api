<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_requests', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('service_requests', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_requests', 'signatory')) {
                $table->string('signatory')->nullable()->after('verified_by');
            }
            if (! Schema::hasColumn('service_requests', 'or_number')) {
                $table->string('or_number')->nullable()->after('signatory');
            }
            if (! Schema::hasColumn('service_requests', 'paid_at')) {
                $table->date('paid_at')->nullable()->after('or_number');
            }
            if (! Schema::hasColumn('service_requests', 'reject_reason')) {
                $table->text('reject_reason')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('service_requests', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('reject_reason');
            }
            if (! Schema::hasColumn('service_requests', 'released_to')) {
                $table->string('released_to')->nullable()->after('cancel_reason');
            }
        });

        Schema::create('service_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('message');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Best-effort backfill of existing rows onto the expanded pipeline, and an
        // initial "Request filed" log entry for each so the activity log isn't empty.
        DB::table('service_requests')->orderBy('id')->get()->each(function ($row): void {
            $newStatus = match (true) {
                $row->status === 'released' => 'released',
                $row->status === 'rejected' => 'rejected',
                $row->status === 'processing' && $row->payment_status === 'paid' => 'processing',
                $row->status === 'processing' && $row->payment_status === 'waived' => 'processing',
                $row->status === 'processing' => 'approved',
                default => 'pending',
            };

            DB::table('service_requests')->where('id', $row->id)->update(['status' => $newStatus]);

            DB::table('service_request_logs')->insert([
                'service_request_id' => $row->id,
                'label' => 'Request filed',
                'message' => 'Request record migrated to the new workflow pipeline.',
                'actor_id' => null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_logs');

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verified_at', 'signatory', 'or_number', 'paid_at', 'reject_reason', 'cancel_reason', 'released_to']);
        });
    }
};
