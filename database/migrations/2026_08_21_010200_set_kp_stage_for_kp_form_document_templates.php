<?php

use App\Support\KpFormTemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (KpFormTemplateCatalog::definitions() as $definition) {
            DB::table('document_templates')
                ->where('code', $definition['code'])
                ->update([
                    'kp_stage' => KpFormTemplateCatalog::caseStage($definition['code']),
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('document_templates')
            ->whereIn('code', KpFormTemplateCatalog::codes())
            ->update(['kp_stage' => null]);
    }
};
