<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Best-effort backfill for hearings created before this column existed:
        // we don't know the case's true status at scheduling time, so use its
        // current status as a starting point rather than leaving it blank.
        DB::statement(
            'UPDATE lupon_hearings
             JOIN lupon_cases ON lupon_cases.id = lupon_hearings.case_id
             SET lupon_hearings.case_status_at_scheduling = lupon_cases.status
             WHERE lupon_hearings.case_status_at_scheduling IS NULL'
        );
    }

    public function down(): void
    {
        // Not reversible: the pre-backfill NULL values aren't recoverable.
    }
};
