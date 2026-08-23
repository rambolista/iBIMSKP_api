<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blotters', function (Blueprint $table) {
            $table->string('incident_type', 100)->nullable()->after('resident_id');
            $table->foreignId('respondent_resident_id')->nullable()->after('respondent_name')
                ->constrained('residents')->nullOnDelete();
            $table->text('witnesses')->nullable()->after('narrative');
            $table->string('responding_official', 255)->nullable()->after('action_taken');
            $table->timestamp('investigation_started_at')->nullable()->after('responding_official');
            $table->foreignId('referred_case_id')->nullable()->after('investigation_started_at')
                ->constrained('lupon_cases')->nullOnDelete();
            $table->string('referred_by', 255)->nullable()->after('referred_case_id');
            $table->text('referred_reason')->nullable()->after('referred_by');
            $table->timestamp('referred_at')->nullable()->after('referred_reason');
            $table->timestamp('closed_at')->nullable()->after('settled_at');
        });

        DB::table('blotters')->where('status', 'open')->update(['status' => 'new']);
        DB::table('blotters')->where('status', 'under_mediation')->update(['status' => 'investigation']);
        DB::table('blotters')->where('status', 'dismissed')->update(['status' => 'closed']);

        Schema::create('blotter_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blotter_id')->constrained('blotters')->cascadeOnDelete();
            $table->string('label', 150);
            $table->text('message');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blotter_logs');

        DB::table('blotters')->where('status', 'new')->update(['status' => 'open']);
        DB::table('blotters')->where('status', 'investigation')->update(['status' => 'under_mediation']);
        DB::table('blotters')->where('status', 'closed')->update(['status' => 'dismissed']);

        Schema::table('blotters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('respondent_resident_id');
            $table->dropConstrainedForeignId('referred_case_id');
            $table->dropColumn([
                'incident_type',
                'witnesses',
                'responding_official',
                'investigation_started_at',
                'referred_by',
                'referred_reason',
                'referred_at',
                'closed_at',
            ]);
        });
    }
};
