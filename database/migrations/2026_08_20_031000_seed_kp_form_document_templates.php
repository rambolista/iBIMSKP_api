<?php

use App\Support\KpFormTemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            foreach (KpFormTemplateCatalog::definitions() as $definition) {
                DB::table('document_templates')->updateOrInsert(
                    ['code' => $definition['code']],
                    KpFormTemplateCatalog::payload($definition, $now)
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('document_templates')
            ->whereIn('code', KpFormTemplateCatalog::codes())
            ->delete();
    }
};
